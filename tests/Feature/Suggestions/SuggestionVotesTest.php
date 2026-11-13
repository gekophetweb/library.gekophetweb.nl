<?php

use App\Enums\SuggestionStatus;
use App\Models\Suggestion;
use App\Models\User;

beforeEach(function () {
    $this->sender = User::factory()->create(['name' => 'Secret Sender', 'email' => 'secret@example.com']);
    $this->voter = User::factory()->create();
    $this->public = Suggestion::factory()->for($this->sender)->create(['body' => 'A public idea']);
    $this->public->forceFill(['is_public' => true])->save();
    $this->private = Suggestion::factory()->for($this->sender)->create(['body' => 'A private idea']);
});

it('lets an admin put a suggestion on the board and take it off', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->patch(route('admin.suggestions.update', $this->private), ['status' => 'planned', 'is_public' => 1])->assertRedirect();
    expect($this->private->fresh()->is_public)->toBeTrue();

    $this->patch(route('admin.suggestions.update', $this->private), ['status' => 'planned'])->assertRedirect();
    expect($this->private->fresh()->is_public)->toBeFalse();
});

it('never lets the sender make their own suggestion public', function () {
    $this->actingAs($this->sender)->post(route('suggestions.store'), ['body' => 'Sneaky public one', 'is_public' => 1]);

    expect(Suggestion::query()->where('body', 'Sneaky public one')->sole()->is_public)->toBeFalse();
});

it('shows only public suggestions on the board, without the sender', function () {
    $this->actingAs($this->voter)->get(route('suggestions.board'))
        ->assertOk()
        ->assertSee('A public idea')
        ->assertDontSee('A private idea')
        ->assertDontSee('Secret Sender')
        ->assertDontSee('secret@example.com');
});

it('lets a user vote once and take the vote back', function () {
    $this->actingAs($this->voter);

    $this->post(route('suggestions.vote', $this->public))->assertRedirect();
    $this->post(route('suggestions.vote', $this->public))->assertRedirect();
    expect($this->public->voters()->count())->toBe(1);

    $this->get(route('suggestions.board'))->assertSee('data-votes="1"', false)->assertSee('data-unvote', false);

    $this->delete(route('suggestions.unvote', $this->public))->assertRedirect();
    expect($this->public->voters()->count())->toBe(0);
});

it('refuses votes on private suggestions and on your own', function () {
    $this->actingAs($this->voter)->post(route('suggestions.vote', $this->private))->assertNotFound();
    $this->actingAs($this->sender)->post(route('suggestions.vote', $this->public))->assertForbidden();

    expect($this->public->voters()->count())->toBe(0);
});

it('orders the board by votes', function () {
    $popular = Suggestion::factory()->create(['body' => 'The popular idea']);
    $popular->forceFill(['is_public' => true])->save();
    $popular->voters()->attach(User::factory()->count(2)->create());
    $this->public->voters()->attach($this->voter);

    $this->actingAs($this->voter)->get(route('suggestions.board'))
        ->assertSeeInOrder(['The popular idea', 'A public idea']);
});

it('shows the sender and admins the vote count, and lets admins sort by it', function () {
    $this->public->voters()->attach($this->voter);
    $quiet = Suggestion::factory()->create(['body' => 'A quiet newer idea', 'status' => SuggestionStatus::New]);

    $this->actingAs($this->sender)->get(route('suggestions.index'))->assertSee('On the board · 1 vote');

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.suggestions.index', ['sort' => 'votes']))
        ->assertSeeInOrder(['A public idea', 'A quiet newer idea']);
    $this->get(route('admin.suggestions.index', ['sort' => 'loudest']))->assertSessionHasErrors('sort');
});
