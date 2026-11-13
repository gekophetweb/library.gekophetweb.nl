<?php

namespace App\Models;

use App\Models\Concerns\SearchesNames;
use App\Support\Series\SeriesReference;
use Database\Factories\SeriesFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A user's series. `external_source` + `external_id` tie it to the series found by the
 * series lookup (plan series-detection-from-isbn §3), unique per user.
 */
#[Fillable([
    'name', 'name_romanized', 'name_english', 'artist_id', 'publisher_id',
    'external_source', 'external_id', 'synced_at',
])]
class Series extends Model
{
    /** @use HasFactory<SeriesFactory> */
    use HasFactory, SearchesNames;

    protected function casts(): array
    {
        return ['synced_at' => 'datetime'];
    }

    /** @param  Builder<self>  $query */
    #[Scope]
    protected function ownedBy(Builder $query, User $user): void
    {
        $query->where($this->qualifyColumn('user_id'), $user->id);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Artist, $this> */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    /** @return BelongsTo<Publisher, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class);
    }

    /** @return HasMany<CollectionObject, $this> */
    public function objects(): HasMany
    {
        return $this->hasMany(CollectionObject::class, 'serie_id');
    }

    /** Known volumes, by number, each regular edition before its special editions. @return HasMany<SeriesVolume, $this> */
    public function volumes(): HasMany
    {
        return $this->hasMany(SeriesVolume::class)->orderBy('volume_sort')->orderBy('edition');
    }

    /** Sources whose volume list can be fetched (SeriesLookup::volumes()). */
    public const array SYNCABLE_SOURCES = ['ndl', 'wikidata'];

    public function isSyncable(): bool
    {
        return in_array($this->external_source, self::SYNCABLE_SOURCES, true) && filled($this->external_id);
    }

    /**
     * The external series this one is linked to, rebuilt for SeriesLookup::volumes(). Built
     * from the stored external ID, not the (user-editable) name: NDL's ID is
     * "title|creator|publisher", which is exactly what its volume search needs.
     */
    public function reference(): ?SeriesReference
    {
        if (! filled($this->external_source) || ! filled($this->external_id)) {
            return null;
        }

        if ($this->external_source === 'ndl') {
            [$title, $creator, $publisher] = array_pad(explode('|', $this->external_id, 3), 3, '');

            return new SeriesReference('ndl', $this->external_id, $title, creator: $creator ?: null, publisher: $publisher ?: null);
        }

        return new SeriesReference(
            $this->external_source, $this->external_id, $this->name, $this->name_romanized, $this->name_english,
            creator: $this->artist?->name, publisher: $this->publisher?->name,
        );
    }
}
