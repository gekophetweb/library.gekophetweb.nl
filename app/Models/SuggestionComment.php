<?php

namespace App\Models;

use Database\Factories\SuggestionCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One message in a suggestion's thread (plan suggestion-comments). */
#[Fillable(['body'])]
class SuggestionComment extends Model
{
    /** @use HasFactory<SuggestionCommentFactory> */
    use HasFactory;

    /** @return BelongsTo<Suggestion, $this> */
    public function suggestion(): BelongsTo
    {
        return $this->belongsTo(Suggestion::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Who wrote it, as $viewer may see it. Admins see names; everyone else sees roles, so
     * a public thread never gives away who sent the suggestion or who replied.
     */
    public function authorLabelFor(User $viewer): string
    {
        return match (true) {
            $viewer->is_admin => $this->user->name.($this->user->is_admin ? ' (admin)' : ''),
            $this->user_id === $viewer->id => 'You',
            $this->user->is_admin => 'Admin',
            $this->user_id === $this->suggestion->user_id => 'Sender',
            default => 'Another user',
        };
    }

    /** The author, or any admin (moderation). */
    public function canBeDeletedBy(User $user): bool
    {
        return $user->is_admin || $this->user_id === $user->id;
    }
}
