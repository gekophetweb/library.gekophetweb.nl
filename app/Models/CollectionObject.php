<?php

namespace App\Models;

use App\Enums\OwnershipStatus;
use App\Enums\SearchScope;
use App\Models\Concerns\SearchesNames;
use App\Support\Isbn;
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
    use HasFactory, SearchesNames;

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

    /**
     * The item's own titles, its series' titles, or its exact barcode (as typed, or as the
     * normalised ISBN-13).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function matchingTitle(Builder $query, string $term): void
    {
        $barcodes = array_unique(array_filter([str_replace(' ', '', $term), Isbn::tryFrom($term)?->value]));

        $query->where(fn (Builder $query) => $query
            ->whereNameLike($term)
            ->orWhereHas('serie', fn (Builder $series) => $series->whereNameLike($term))
            ->orWhereIn($this->qualifyColumn('barcode'), $barcodes));
    }

    /**
     * A book's author, a record's artist, or the artist of the item's series.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function matchingCreator(Builder $query, string $term): void
    {
        $artist = fn (Builder $artists) => $artists->whereNameLike($term);

        $query->where(fn (Builder $query) => $query
            ->whereHas('bookDetail.artist', $artist)
            ->orWhereHas('musicDetail.artist', $artist)
            ->orWhereHas('serie.artist', $artist));
    }

    /** @param  Builder<self>  $query */
    #[Scope]
    protected function matchingSearch(Builder $query, string $term, SearchScope $scope = SearchScope::All): void
    {
        $query->where(fn (Builder $query) => match ($scope) {
            SearchScope::Title => $query->matchingTitle($term),
            SearchScope::Creator => $query->matchingCreator($term),
            SearchScope::All => $query->matchingTitle($term)->orWhere(fn (Builder $query) => $query->matchingCreator($term)),
        });
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
