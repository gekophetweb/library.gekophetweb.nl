<?php

namespace App\Models;

use App\Models\Concerns\SearchesNames;
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
}
