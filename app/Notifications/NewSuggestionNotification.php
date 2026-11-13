<?php

namespace App\Notifications;

use App\Models\Suggestion;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** To admins: someone sent a suggestion. */
class NewSuggestionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Suggestion $suggestion) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New suggestion from '.$this->suggestion->user->name)
            ->line($this->suggestion->user->name.' sent a suggestion:')
            ->line('“'.Str::limit($this->suggestion->body, 500).'”')
            ->action('Open the suggestion', route('suggestions.show', $this->suggestion))
            ->line('You can turn these e-mails off in your settings.');
    }
}
