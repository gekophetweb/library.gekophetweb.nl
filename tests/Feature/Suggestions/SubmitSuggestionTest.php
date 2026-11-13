<?php

use App\Enums\SuggestionStatus;
use App\Models\Suggestion;
use App\Models\User;

it('shows the form and a nav link to signed-in users', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertSee(route('suggestions.index'));

    $this->get(route('suggestions.index'))
        ->assertOk()
        ->assertSee('action="'.route('suggestions.store').'"', false);
});

it('stores a suggestion as new for the signed-in user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('suggestions.store'), ['body' => 'Please add a dark mode toggle.'])
        ->assertRedirect(route('suggestions.index'))
        ->assertSessionHas('status');

    $suggestion = Suggestion::sole();
    expect($suggestion->user_id)->toBe($user->id)
        ->and($suggestion->body)->toBe('Please add a dark mode toggle.')
        ->and($suggestion->status)->toBe(SuggestionStatus::New)
        ->and($suggestion->admin_reply)->toBeNull();
});

it('ignores a status or reply the user tries to set', function () {
    $this->actingAs(User::factory()->create())->post(route('suggestions.store'), [
        'body' => 'Please add a dark mode toggle.',
        'status' => 'done',
        'admin_reply' => 'Sure!',
    ]);

    expect(Suggestion::sole())
        ->status->toBe(SuggestionStatus::New)
        ->admin_reply->toBeNull();
});

it('validates the body', function (mixed $body) {
    $this->actingAs(User::factory()->create())
        ->post(route('suggestions.store'), ['body' => $body])
        ->assertSessionHasErrors('body');

    expect(Suggestion::count())->toBe(0);
})->with([
    'missing' => [null],
    'too short' => ['hi'],
    'too long' => [str_repeat('a', 5001)],
]);

it('lists only the user’s own suggestions, with status and reply', function () {
    $user = User::factory()->create();
    Suggestion::factory()->for($user)->create([
        'body' => 'My own idea',
        'status' => SuggestionStatus::Planned,
        'admin_reply' => 'Coming next month.',
    ]);
    Suggestion::factory()->create(['body' => 'Someone else’s idea']);

    $this->actingAs($user)->get(route('suggestions.index'))
        ->assertOk()
        ->assertSee('My own idea')
        ->assertSee('Planned')
        ->assertSee('Coming next month.')
        ->assertDontSee('Someone else’s idea');
});

it('throttles submissions', function () {
    $user = User::factory()->create();

    foreach (range(1, 10) as $i) {
        $this->actingAs($user)->post(route('suggestions.store'), ['body' => "Suggestion number {$i}"]);
    }

    $this->post(route('suggestions.store'), ['body' => 'One suggestion too many'])->assertTooManyRequests();
    expect(Suggestion::count())->toBe(10);
});

it('sends guests to the login page', function () {
    $this->get(route('suggestions.index'))->assertRedirect(route('login'));
    $this->post(route('suggestions.store'), ['body' => 'Anonymous idea here'])->assertRedirect(route('login'));

    expect(Suggestion::count())->toBe(0);
});
