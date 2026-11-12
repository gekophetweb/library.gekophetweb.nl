<?php

namespace App\Models;

use App\Models\Concerns\SearchesNames;
use Database\Factories\ArtistFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A book author or music artist, shared across users. Shared rows are not a shared
 * library: a user only ever sees the artists reachable through their own items and series.
 */
#[Fillable(['name', 'name_romanized', 'name_english'])]
class Artist extends Model
{
    /** @use HasFactory<ArtistFactory> */
    use HasFactory, SearchesNames;

    /**
     * Artists linked to one of the user's items (book author, music artist) or series.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function reachableBy(Builder $query, User $user): void
    {
        $owned = fn (Builder $details) => $details->whereHas('object', fn (Builder $object) => $object->ownedBy($user));

        $query->where(fn (Builder $query) => $query
            ->whereHas('bookDetails', $owned)
            ->orWhereHas('musicDetails', $owned)
            ->orWhereHas('series', fn (Builder $series) => $series->ownedBy($user)));
    }

    /** @return BelongsToMany<Language, $this> */
    public function languages(): BelongsToMany
    {
        return $this->belongsToMany(Language::class);
    }

    /** @return HasMany<BookDetail, $this> */
    public function bookDetails(): HasMany
    {
        return $this->hasMany(BookDetail::class);
    }

    /** @return HasMany<MusicDetail, $this> */
    public function musicDetails(): HasMany
    {
        return $this->hasMany(MusicDetail::class);
    }

    /** @return HasMany<Series, $this> */
    public function series(): HasMany
    {
        return $this->hasMany(Series::class);
    }
}
