<?php

namespace App\Notifications;

use App\Models\SuggestionComment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * To the people in a suggestion's thread: a new comment. The author is named the way
 * the recipient would see them on the page (SuggestionComment::authorLabelFor), so a
 * board thread stays anonymous in e-mail too.
 */
class NewSuggestionCommentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly SuggestionComment $comment) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $author = $this->comment->authorLabelFor($notifiable);

        return (new MailMessage)
            ->subject('New comment on a suggestion')
            ->line("{$author} commented on “".Str::limit($this->comment->suggestion->body, 80).'”:')
            ->line('“'.Str::limit($this->comment->body, 1000).'”')
            ->action('Open the thread', route('suggestions.show', $this->comment->suggestion).'#comment-'.$this->comment->id)
            ->line('You can turn these e-mails off in your settings.');
    }
}
