<?php

use App\Models\Suggestion;
use App\Models\SuggestionComment;
use App\Models\User;

beforeEach(function () {
    $this->sender = User::factory()->create(['name' => 'Secret Sender']);
    $this->admin = User::factory()->admin()->create(['name' => 'Ada Admin']);
    $this->other = User::factory()->create(['name' => 'Olga Other']);
    $this->suggestion = Suggestion::factory()->for($this->sender)->create(['body' => 'Please add tags']);
});

it('lets the sender and admins discuss a private suggestion', function () {
    $this->actingAs($this->sender)
        ->post(route('suggestions.comments.store', $this->suggestion), ['body' => 'Any news?'])
        ->assertRedirect();
    $this->actingAs($this->admin)
        ->post(route('suggestions.comments.store', $this->suggestion), ['body' => 'Working on it.']);

    $this->actingAs($this->sender)->get(route('suggestions.show', $this->suggestion))
        ->assertOk()
        ->assertSeeInOrder(['Any news?', 'Working on it.'])
        ->assertSee('You')
        ->assertSee('Admin')
        ->assertDontSee('Ada Admin');

    $this->actingAs($this->admin)->get(route('suggestions.show', $this->suggestion))
        ->assertSee('Secret Sender')
        ->assertSee('Ada Admin (admin)');
});

it('hides a private suggestion and its thread from other users', function () {
    $this->actingAs($this->other);

    $this->get(route('suggestions.show', $this->suggestion))->assertNotFound();
    $this->post(route('suggestions.comments.store', $this->suggestion), ['body' => 'Let me in'])->assertNotFound();
    expect(SuggestionComment::query()->count())->toBe(0);
});

it('opens the thread of a board suggestion to everyone, without names', function () {
    $this->suggestion->forceFill(['is_public' => true])->save();
    SuggestionComment::factory()->for($this->suggestion)->for($this->sender)->create(['body' => 'From the sender']);

    $this->actingAs($this->other)
        ->post(route('suggestions.comments.store', $this->suggestion), ['body' => 'Me too!'])
        ->assertRedirect();

    $this->get(route('suggestions.show', $this->suggestion))
        ->assertOk()
        ->assertSee('Sender')
        ->assertDontSee('Secret Sender')
        ->assertSee('Me too!');

    $this->actingAs($this->sender)->get(route('suggestions.show', $this->suggestion))
        ->assertSee('Another user')
        ->assertDontSee('Olga Other');
});

it('lets authors and admins delete comments, nobody else', function () {
    $this->suggestion->forceFill(['is_public' => true])->save();
    $comment = SuggestionComment::factory()->for($this->suggestion)->for($this->other)->create();

    $this->actingAs($this->sender)->delete(route('suggestions.comments.destroy', [$this->suggestion, $comment]))->assertForbidden();
    $this->actingAs($this->other)->delete(route('suggestions.comments.destroy', [$this->suggestion, $comment]))->assertRedirect();
    expect(SuggestionComment::query()->count())->toBe(0);

    $again = SuggestionComment::factory()->for($this->suggestion)->for($this->other)->create();
    $this->actingAs($this->admin)->delete(route('suggestions.comments.destroy', [$this->suggestion, $again]))->assertRedirect();
    expect(SuggestionComment::query()->count())->toBe(0);
});

it('refuses a comment of another suggestion through this one\'s URL', function () {
    $foreign = SuggestionComment::factory()->for($this->other)->create();

    $this->actingAs($this->admin)
        ->delete(route('suggestions.comments.destroy', [$this->suggestion, $foreign]))
        ->assertNotFound();
});

it('validates the comment and shows counts on the lists', function () {
    $this->actingAs($this->sender)
        ->post(route('suggestions.comments.store', $this->suggestion), ['body' => ''])
        ->assertSessionHasErrors('body');

    SuggestionComment::factory()->count(2)->for($this->suggestion)->for($this->sender)->create();

    $this->get(route('suggestions.index'))->assertSee('2 comments')->assertSee(route('suggestions.show', $this->suggestion));
    $this->actingAs($this->admin)->get(route('admin.suggestions.index'))->assertSee('2 comments');
});
