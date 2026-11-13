<?php

use App\Models\Suggestion;
use App\Models\SuggestionComment;
use App\Models\User;
use App\Notifications\NewSuggestionCommentNotification;
use App\Notifications\NewSuggestionNotification;
use App\Notifications\SuggestionUpdatedNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->sender = User::factory()->create(['name' => 'Secret Sender']);
    $this->admin = User::factory()->admin()->create();
    $this->quietAdmin = User::factory()->admin()->create(['suggestion_emails' => false]);
});

it('mails admins who opted in about a new suggestion', function () {
    $this->actingAs($this->sender)->post(route('suggestions.store'), ['body' => 'Please add a dark mode toggle.']);

    Notification::assertSentTo($this->admin, NewSuggestionNotification::class, function ($notification, $channels) {
        $mail = $notification->toMail($this->admin);

        return $channels === ['mail'] && str_contains($mail->subject, 'Secret Sender');
    });
    Notification::assertNotSentTo([$this->quietAdmin, $this->sender], NewSuggestionNotification::class);
});

it('does not mail an admin about their own suggestion', function () {
    $this->actingAs($this->admin)->post(route('suggestions.store'), ['body' => 'An idea from an admin.']);

    Notification::assertNothingSentTo($this->admin);
});

it('mails the sender when the status or reply changes, and only then', function () {
    $suggestion = Suggestion::factory()->for($this->sender)->create();
    $this->actingAs($this->admin);

    $this->patch(route('admin.suggestions.update', $suggestion), ['status' => 'planned', 'admin_reply' => 'Next month.']);
    Notification::assertSentToTimes($this->sender, SuggestionUpdatedNotification::class, 1);

    // Only the board flag changes: nothing new for the sender.
    $this->patch(route('admin.suggestions.update', $suggestion), ['status' => 'planned', 'admin_reply' => 'Next month.', 'is_public' => 1]);
    Notification::assertSentToTimes($this->sender, SuggestionUpdatedNotification::class, 1);

    $mail = (new SuggestionUpdatedNotification($suggestion->fresh()))->toMail($this->sender);
    expect($mail->subject)->toBe('Your suggestion is now: Planned')
        ->and(collect($mail->introLines)->join(' '))->toContain('Next month.');
});

it('mails the thread about a comment, but not its author or opted-out users', function () {
    $suggestion = Suggestion::factory()->for($this->sender)->create();
    $suggestion->forceFill(['is_public' => true])->save();
    $earlier = User::factory()->create();
    SuggestionComment::factory()->for($suggestion)->for($earlier)->create();
    $bystander = User::factory()->create();
    $author = User::factory()->create(['name' => 'Olga Other']);

    $this->actingAs($author)->post(route('suggestions.comments.store', $suggestion), ['body' => 'Me too!']);

    Notification::assertSentTo([$this->sender, $this->admin, $earlier], NewSuggestionCommentNotification::class);
    Notification::assertNotSentTo([$author, $bystander, $this->quietAdmin], NewSuggestionCommentNotification::class);

    // The sender sees a role, not the commenter's name, in the e-mail too.
    $mail = (new NewSuggestionCommentNotification(SuggestionComment::query()->latest('id')->first()))->toMail($this->sender);
    expect(collect($mail->introLines)->join(' '))->toContain('Another user')->not->toContain('Olga Other');
});

it('lets a user turn suggestion e-mails off and on', function () {
    $this->actingAs($this->sender)->get(route('settings.edit'))->assertSee('E-mail me about suggestions');

    $this->put(route('settings.notifications'))->assertRedirect(route('settings.edit').'#notifications');
    expect($this->sender->fresh()->suggestion_emails)->toBeFalse();

    $this->put(route('settings.notifications'), ['suggestion_emails' => 1]);
    expect($this->sender->fresh()->suggestion_emails)->toBeTrue();
});
