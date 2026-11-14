<?php

use App\Models\CollectionObject;
use App\Models\Suggestion;
use App\Models\SuggestionComment;
use App\Models\User;
use App\Notifications\SuggestionUpdatedNotification;
use App\Support\Suggestions\SuggestionMailer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->user = User::factory()->create(['email' => 'reader@example.test']);
    $this->admin = User::factory()->admin()->create();
});

it('deletes the account and its collection after typing the e-mail address', function () {
    CollectionObject::factory()->for($this->user)->count(2)->create();
    $this->user->createToken('phone');
    $other = CollectionObject::factory()->create();

    $this->actingAs($this->user)->get(route('settings.edit'))->assertSee('Delete my account');

    $this->delete(route('settings.account.destroy'), ['confirm_email' => ' Reader@Example.test '])
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'Your account has been deleted.');

    $this->assertGuest();
    expect(User::query()->whereKey($this->user->id)->exists())->toBeFalse()
        ->and(CollectionObject::query()->pluck('id')->all())->toBe([$other->id])
        ->and(DB::table('personal_access_tokens')->count())->toBe(0);
});

it('keeps the account when the typed address does not match', function () {
    $this->actingAs($this->user)->delete(route('settings.account.destroy'), ['confirm_email' => 'someone@example.test'])
        ->assertSessionHasErrorsIn('deleteAccount', 'confirm_email');

    $this->assertAuthenticatedAs($this->user);
    expect($this->user->exists())->toBeTrue();
});

it('never lets the last admin delete their account', function () {
    $this->actingAs($this->admin)->delete(route('settings.account.destroy'), ['confirm_email' => $this->admin->email])
        ->assertSessionHasErrorsIn('deleteAccount', 'confirm_email');
    expect($this->admin->exists())->toBeTrue();

    User::factory()->admin()->create();
    $this->delete(route('settings.account.destroy'), ['confirm_email' => $this->admin->email])->assertRedirect(route('login'));
    expect(User::query()->whereKey($this->admin->id)->exists())->toBeFalse();
});

it('keeps suggestions and comments of a deleted account as [deleted user], without their votes', function () {
    $suggestion = Suggestion::factory()->for($this->user)->create(['body' => 'Add a dark mode']);
    $suggestion->forceFill(['is_public' => true])->save();
    $theirComment = SuggestionComment::factory()->for($suggestion)->for($this->user)->create(['body' => 'Still wanted']);
    $other = User::factory()->create();
    $otherSuggestion = Suggestion::factory()->for($other)->create();
    $otherSuggestion->forceFill(['is_public' => true])->save();
    $otherSuggestion->voters()->attach($this->user);

    $this->actingAs($this->user)->delete(route('settings.account.destroy'), ['confirm_email' => $this->user->email]);

    expect($suggestion->refresh()->user_id)->toBeNull()
        ->and($suggestion->user->name)->toBe('[deleted user]')
        ->and($theirComment->refresh()->user_id)->toBeNull()
        ->and($otherSuggestion->voters()->count())->toBe(0);

    // Admins see the name slot, others the role label: both say [deleted user], never "Sender".
    $this->actingAs($this->admin)->get(route('admin.suggestions.index'))->assertOk()->assertSee('[deleted user]');
    $this->get(route('suggestions.show', $suggestion))->assertOk()->assertSee('[deleted user]')->assertSee('Still wanted');
    $this->actingAs($other)->get(route('suggestions.show', $suggestion))->assertOk()
        ->assertSee('Add a dark mode')
        ->assertSee('[deleted user]')
        ->assertDontSee('>Sender<', false);
});

it('mails nobody about a deleted sender\'s suggestion', function () {
    Notification::fake();
    $suggestion = Suggestion::factory()->for($this->user)->create();
    $this->actingAs($this->user)->delete(route('settings.account.destroy'), ['confirm_email' => $this->user->email]);

    $suggestion->refresh()->forceFill(['status' => 'done', 'admin_reply' => 'Built it'])->save();
    app(SuggestionMailer::class)->updated($suggestion, $this->admin);

    Notification::assertNothingSent();
    Notification::assertNotSentTo(User::all(), SuggestionUpdatedNotification::class);
});
