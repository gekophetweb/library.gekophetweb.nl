<?php

namespace App\Notifications;

use App\Models\Suggestion;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** To the sender: an admin changed the status of their suggestion or replied to it. */
class SuggestionUpdatedNotification extends Notification implements ShouldQueue
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
        $mail = (new MailMessage)
            ->subject('Your suggestion is now: '.$this->suggestion->status->label())
            ->line('The admins looked at your suggestion:')
            ->line('“'.Str::limit($this->suggestion->body, 200).'”')
            ->line('Status: '.$this->suggestion->status->label());

        if ($this->suggestion->admin_reply) {
            $mail->line('Their reply: “'.Str::limit($this->suggestion->admin_reply, 1000).'”');
        }

        return $mail
            ->action('Open the suggestion', route('suggestions.show', $this->suggestion))
            ->line('You can turn these e-mails off in your settings.');
    }
}
