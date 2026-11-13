<?php

use App\Enums\SuggestionStatus;
use App\Models\Suggestion;
use App\Models\User;

it('shows every suggestion to an admin with its sender, newest first', function () {
    $admin = User::factory()->admin()->create();
    $sender = User::factory()->create(['name' => 'Sender Person', 'email' => 'sender@example.com']);
    Suggestion::factory()->for($sender)->create(['body' => 'Older idea', 'created_at' => now()->subDay()]);
    Suggestion::factory()->create(['body' => 'Newer idea']);

    $this->actingAs($admin)->get(route('admin.suggestions.index'))
        ->assertOk()
        ->assertSee('Sender Person')
        ->assertSee('sender@example.com')
        ->assertSeeInOrder(['Newer idea', 'Older idea']);
});

it('filters on status', function () {
    $admin = User::factory()->admin()->create();
    Suggestion::factory()->create(['body' => 'A fresh idea']);
    Suggestion::factory()->create(['body' => 'A finished idea', 'status' => SuggestionStatus::Done]);

    $this->actingAs($admin)->get(route('admin.suggestions.index', ['status' => 'done']))
        ->assertSee('A finished idea')
        ->assertDontSee('A fresh idea');

    $this->get(route('admin.suggestions.index', ['status' => 'bogus']))->assertSessionHasErrors('status');
});

it('paginates at 25 suggestions', function () {
    $admin = User::factory()->admin()->create();
    Suggestion::factory()->count(30)->create();

    $this->actingAs($admin)->get(route('admin.suggestions.index'))
        ->assertViewHas('suggestions', fn ($suggestions) => $suggestions->count() === 25 && $suggestions->total() === 30);
});

it('lets an admin set the status and a reply', function () {
    $admin = User::factory()->admin()->create();
    $suggestion = Suggestion::factory()->create();

    $this->actingAs($admin)
        ->patch(route('admin.suggestions.update', $suggestion), ['status' => 'planned', 'admin_reply' => 'Good one, on the list.'])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect($suggestion->fresh())
        ->status->toBe(SuggestionStatus::Planned)
        ->admin_reply->toBe('Good one, on the list.');
});

it('clears an empty reply and rejects an unknown status', function () {
    $admin = User::factory()->admin()->create();
    $suggestion = Suggestion::factory()->create(['admin_reply' => 'Old reply']);

    $this->actingAs($admin)->patch(route('admin.suggestions.update', $suggestion), ['status' => 'done', 'admin_reply' => '']);
    expect($suggestion->fresh()->admin_reply)->toBeNull();

    $this->patch(route('admin.suggestions.update', $suggestion), ['status' => 'whatever'])->assertSessionHasErrors('status');
    expect($suggestion->fresh()->status)->toBe(SuggestionStatus::Done);
});

it('shows admins a nav link with the count of new suggestions', function () {
    Suggestion::factory()->count(2)->create();
    Suggestion::factory()->create(['status' => SuggestionStatus::Done]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('home'))
        ->assertSee(route('admin.suggestions.index'))
        ->assertSee('data-new-suggestions="2"', false);
});

it('forbids the admin page and updates to non-admins and hides the link', function () {
    $user = User::factory()->create();
    $suggestion = Suggestion::factory()->create(['body' => 'Not yours to read']);

    $this->actingAs($user)->get(route('admin.suggestions.index'))->assertForbidden();
    $this->patch(route('admin.suggestions.update', $suggestion), ['status' => 'done'])->assertForbidden();
    $this->get(route('home'))->assertDontSee(route('admin.suggestions.index'));

    expect($suggestion->fresh()->status)->toBe(SuggestionStatus::New);
});

it('sends guests to the login page', function () {
    $this->get(route('admin.suggestions.index'))->assertRedirect(route('login'));
});
