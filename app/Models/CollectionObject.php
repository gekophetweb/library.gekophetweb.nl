<?php

namespace App\Models;

use App\Enums\OwnershipStatus;
use Database\Factories\CollectionObjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One physical item in a user's collection (guidelines §3.1): owned, or on the wishlist.
 * Category-specific data lives in bookDetail / musicDetail / gameDetail.
 */
#[Table('objects')]
#[Fillable([
    'category_id', 'sub_category_id', 'barcode', 'ownership', 'name', 'name_romanized', 'name_english',
    'publisher_id', 'release_date', 'serie_id',
])]
class CollectionObject extends Model
{
    /** @use HasFactory<CollectionObjectFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['release_date' => 'date', 'ownership' => OwnershipStatus::class];
    }

    /** @param  Builder<self>  $query */
    #[Scope]
    protected function ownedBy(Builder $query, User $user): void
    {
        $query->where($this->qualifyColumn('user_id'), $user->id);
    }

    /**
     * Items the user has, not the ones they only want.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function owned(Builder $query): void
    {
        $query->where($this->qualifyColumn('ownership'), OwnershipStatus::Owned->value);
    }

    /** @param  Builder<self>  $query */
    #[Scope]
    protected function wishlisted(Builder $query): void
    {
        $query->where($this->qualifyColumn('ownership'), OwnershipStatus::Wishlist->value);
    }

    public function isOwned(): bool
    {
        return $this->ownership === OwnershipStatus::Owned;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function subCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'sub_category_id');
    }

    /** @return BelongsTo<Publisher, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class);
    }

    /** @return BelongsTo<Series, $this> */
    public function serie(): BelongsTo
    {
        return $this->belongsTo(Series::class, 'serie_id');
    }

    /** @return BelongsToMany<Language, $this> */
    public function languages(): BelongsToMany
    {
        return $this->belongsToMany(Language::class, 'object_language', 'object_id', 'language_id');
    }

    /** @return BelongsToMany<Genre, $this> */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'genre_object', 'object_id', 'genre_id');
    }

    /** @return HasOne<BookDetail, $this> */
    public function bookDetail(): HasOne
    {
        return $this->hasOne(BookDetail::class, 'object_id');
    }

    /** @return HasOne<MusicDetail, $this> */
    public function musicDetail(): HasOne
    {
        return $this->hasOne(MusicDetail::class, 'object_id');
    }

    /** @return HasOne<GameDetail, $this> */
    public function gameDetail(): HasOne
    {
        return $this->hasOne(GameDetail::class, 'object_id');
    }
}
