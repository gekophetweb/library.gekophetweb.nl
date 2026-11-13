<?php

namespace App\Models;

use Database\Factories\LibraryShareFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * A read-only link to a user's library, one category or one series, for people without
 * an account (plan share-library-read-only). Revoking is deleting the row. With a
 * `recipient_email` it is personal: it opens only for the account with that address
 * (plan share-with-account).
 *
 * The token is stored in plaintext on purpose: the owner re-copies the link from
 * settings, and anyone with database access already has the library it exposes.
 *
 * `exposes()` is the single gate: route-model binding is global, so it is the only thing
 * between a token and someone else's data.
 */
#[Fillable(['label', 'expires_at', 'recipient_email'])]
#[Hidden(['token'])]
class LibraryShare extends Model
{
    /** @use HasFactory<LibraryShareFactory> */
    use HasFactory, Prunable;

    public const int TOKEN_LENGTH = 32;

    protected static function booted(): void
    {
        static::creating(function (self $share) {
            $share->token ??= Str::random(self::TOKEN_LENGTH);
        });
    }

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    /** @param  Builder<self>  $query */
    #[Scope]
    protected function usable(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /** Shares made for $user's e-mail address (plan share-with-account). @param  Builder<self>  $query */
    #[Scope]
    protected function sharedWith(Builder $query, User $user): void
    {
        $query->where('recipient_email', Str::lower($user->email));
    }

    /** For one person only, rather than anyone with the link. */
    public function isPersonal(): bool
    {
        return $this->recipient_email !== null;
    }

    /**
     * Whether $viewer may open this share. A link share opens for anyone; a personal one
     * only for the signed-in account with the recipient's e-mail address.
     */
    public function admits(?User $viewer): bool
    {
        return ! $this->isPersonal()
            || ($viewer !== null && hash_equals($this->recipient_email, Str::lower($viewer->email)));
    }

    /** Lapsed a month ago or more: nobody can use them and the owner has had time to see it. */
    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<', now()->subMonth());
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isUsable(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function url(): string
    {
        return route('share.index', ['shareToken' => $this->token]);
    }

    public function scopedCategory(): ?Category
    {
        return $this->shareable instanceof Category ? $this->shareable : null;
    }

    public function scopedSeries(): ?Series
    {
        return $this->shareable instanceof Series ? $this->shareable : null;
    }

    public function isWholeLibrary(): bool
    {
        return $this->shareable_type === null;
    }

    public function scopeLabel(): string
    {
        return match (true) {
            $this->scopedCategory() !== null => 'Category: '.$this->scopedCategory()->name,
            $this->scopedSeries() !== null => 'Series: '.$this->scopedSeries()->name,
            default => 'Whole library',
        };
    }

    /**
     * The owner's items this link may show — every share listing builds on this, never
     * on auth()->user(), so a page cannot widen past the link's scope by accident.
     *
     * @return Builder<CollectionObject>
     */
    public function objectsQuery(): Builder
    {
        $query = CollectionObject::query()->ownedBy($this->user);

        if ($category = $this->scopedCategory()) {
            $query->where(fn (Builder $query) => $query
                ->where('objects.category_id', $category->id)
                ->orWhere('objects.sub_category_id', $category->id));
        } elseif ($series = $this->scopedSeries()) {
            $query->where('objects.serie_id', $series->id);
        }

        return $query;
    }

    /** Whether this link lets a viewer see $model. */
    public function exposes(Model $model): bool
    {
        return match (true) {
            $model instanceof CollectionObject => $model->user_id === $this->user_id
                && $this->objectsQuery()->whereKey($model->id)->exists(),
            $model instanceof Series => $model->user_id === $this->user_id
                && ($this->isWholeLibrary()
                    || $this->scopedSeries()?->is($model)
                    || ($this->scopedCategory() !== null && $this->objectsQuery()->where('objects.serie_id', $model->id)->exists())),
            // Categories are shared rows: a top-level one is visible when the link covers it.
            $model instanceof Category => $model->isTopLevel()
                && ($this->isWholeLibrary() || $this->scopedCategory()?->is($model)),
            default => false,
        };
    }
}
