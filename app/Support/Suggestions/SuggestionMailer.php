<?php

namespace App\Support\Suggestions;

use App\Models\Suggestion;
use App\Models\SuggestionComment;
use App\Models\User;
use App\Notifications\NewSuggestionCommentNotification;
use App\Notifications\NewSuggestionNotification;
use App\Notifications\SuggestionUpdatedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Who hears about what in the suggestion box (plan suggestion-notifications). Nobody is
 * mailed about their own action, and a user with `suggestion_emails` off gets nothing.
 */
class SuggestionMailer
{
    /** A new suggestion: every admin but the sender. */
    public function created(Suggestion $suggestion): void
    {
        $this->send(
            $this->admins()->reject(fn (User $admin) => $admin->id === $suggestion->user_id),
            new NewSuggestionNotification($suggestion),
        );
    }

    /** An admin changed the status or the reply: tell the sender. */
    public function updated(Suggestion $suggestion, User $by): void
    {
        if (! $suggestion->wasChanged(['status', 'admin_reply']) || $suggestion->user_id === $by->id) {
            return;
        }

        $this->send(collect([$suggestion->user]), new SuggestionUpdatedNotification($suggestion));
    }

    /** A comment: the sender, the admins and everyone who commented before, but not its author. */
    public function commented(SuggestionComment $comment): void
    {
        $suggestion = $comment->suggestion;
        $earlier = User::query()->whereIn('id', $suggestion->comments()->select('user_id'))->get();

        $this->send(
            collect([$suggestion->user])->merge($this->admins())->merge($earlier)
                ->unique('id')
                ->reject(fn (User $user) => $user->id === $comment->user_id)
                // Someone who commented on a board suggestion that has since been taken
                // off it can no longer see the thread.
                ->filter(fn (User $user) => $suggestion->isVisibleTo($user)),
            new NewSuggestionCommentNotification($comment),
        );
    }

    /** @return Collection<int, User> */
    private function admins(): Collection
    {
        return User::query()->where('is_admin', true)->get();
    }

    /** @param  Collection<int, User>  $users */
    private function send(Collection $users, object $notification): void
    {
        $users = $users->filter(fn (User $user) => $user->suggestion_emails)->values();

        if ($users->isNotEmpty()) {
            Notification::send($users, $notification);
        }
    }
}
