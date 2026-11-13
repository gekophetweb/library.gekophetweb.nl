<?php

use App\Models\Suggestion;
use App\Models\SuggestionComment;
use App\Models\User;
use App\Notifications\NewSuggestionNotification;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Notification::fake();
    $this->user = User::factory()->create();
    $this->sender = User::factory()->create(['name' => 'Secret Sender', 'email' => 'secret@example.com']);
    $this->public = Suggestion::factory()->for($this->sender)->create(['body' => 'A public idea']);
    $this->public->forceFill(['is_public' => true])->save();
    $this->private = Suggestion::factory()->for($this->sender)->create(['body' => 'A private idea']);
    Sanctum::actingAs($this->user);
});

it('sends a suggestion and lists only your own', function () {
    User::factory()->admin()->create();

    $this->postJson('/api/v1/suggestions', ['body' => 'Please add a dark mode.'])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Please add a dark mode.')
        ->assertJsonPath('data.status', 'new')
        ->assertJsonPath('data.is_mine', true)
        ->assertJsonPath('data.on_board', false);
    Notification::assertSentTimes(NewSuggestionNotification::class, 1);

    $this->getJson('/api/v1/suggestions')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.body', 'Please add a dark mode.');

    $this->postJson('/api/v1/suggestions', ['body' => 'short'])->assertUnprocessable()->assertJsonValidationErrors('body');
});

it('lists the board without the sender, with votes', function () {
    $this->public->voters()->attach(User::factory()->create());

    $response = $this->getJson('/api/v1/suggestions/board')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.body', 'A public idea')
        ->assertJsonPath('data.0.votes_count', 1)
        ->assertJsonPath('data.0.voted', false)
        ->assertJsonPath('data.0.can_vote', true);

    expect($response->getContent())->not->toContain('Secret Sender')->not->toContain('secret@example.com');
});

it('votes and unvotes', function () {
    $this->postJson("/api/v1/suggestions/{$this->public->id}/vote")
        ->assertOk()->assertJsonPath('data.votes_count', 1)->assertJsonPath('data.voted', true);
    $this->deleteJson("/api/v1/suggestions/{$this->public->id}/vote")
        ->assertOk()->assertJsonPath('data.votes_count', 0)->assertJsonPath('data.voted', false);

    $this->postJson("/api/v1/suggestions/{$this->private->id}/vote")->assertNotFound();

    Sanctum::actingAs($this->sender);
    $this->postJson("/api/v1/suggestions/{$this->public->id}/vote")->assertForbidden();
});

it('shows a visible suggestion with its thread and hides the rest', function () {
    SuggestionComment::factory()->for($this->public)->for($this->sender)->create(['body' => 'From the sender']);

    $this->getJson("/api/v1/suggestions/{$this->public->id}")
        ->assertOk()
        ->assertJsonPath('data.comments.0.body', 'From the sender')
        ->assertJsonPath('data.comments.0.author', 'Sender')
        ->assertJsonPath('data.comments.0.can_delete', false);

    $this->getJson("/api/v1/suggestions/{$this->private->id}")->assertNotFound();
});

it('adds and deletes your own comment', function () {
    $id = $this->postJson("/api/v1/suggestions/{$this->public->id}/comments", ['body' => 'Me too!'])
        ->assertCreated()
        ->assertJsonPath('data.author', 'You')
        ->json('data.id');

    $this->postJson("/api/v1/suggestions/{$this->private->id}/comments", ['body' => 'Let me in'])->assertNotFound();

    $other = SuggestionComment::factory()->for($this->public)->for($this->sender)->create();
    $this->deleteJson("/api/v1/suggestions/{$this->public->id}/comments/{$other->id}")->assertForbidden();
    $this->deleteJson("/api/v1/suggestions/{$this->public->id}/comments/{$id}")->assertNoContent();

    expect(SuggestionComment::query()->pluck('id')->all())->toBe([$other->id]);
});
