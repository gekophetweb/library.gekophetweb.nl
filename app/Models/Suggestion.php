<?php

namespace App\Models;

use App\Enums\SuggestionStatus;
use Database\Factories\SuggestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A message in the suggestion box (plan suggestion-box). The sender sees their own;
 * admins see all of them and set `status`, `admin_reply` and `is_public`, which are
 * therefore not fillable from the user's form. A public one is on the board, where every
 * user can read it (without the sender's name) and vote on it (plan suggestion-votes).
 */
#[Fillable(['body'])]
class Suggestion extends Model
{
    /** @use HasFactory<SuggestionFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'new',
        'is_public' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SuggestionStatus::class,
            'is_public' => 'boolean',
        ];
    }

    /** @param  Builder<self>  $query */
    #[Scope]
    protected function onBoard(Builder $query): void
    {
        $query->where('is_public', true);
    }

    /** Adds `votes_count` and, for $user, `voted` (0 / 1). @param  Builder<self>  $query */
    #[Scope]
    protected function withVotesFor(Builder $query, User $user): void
    {
        $query->withCount(['voters as votes_count', 'voters as voted' => fn (Builder $query) => $query->whereKey($user->id)]);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function voters(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'suggestion_votes')->withTimestamps();
    }

    /** Oldest first. @return HasMany<SuggestionComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(SuggestionComment::class)->oldest()->oldest('id');
    }

    /** Sender, admins, and — once it is on the board — everyone. */
    public function isVisibleTo(User $user): bool
    {
        return $this->is_public || $user->is_admin || $this->user_id === $user->id;
    }

    /** Votes are for other people's public suggestions. */
    public function canBeVotedOnBy(User $user): bool
    {
        return $this->is_public && $this->user_id !== $user->id;
    }
}
