<?php

namespace App\Models;

use Database\Factories\SeriesVolumeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One known volume of a series. A special edition is its own row next to the regular
 * edition (edition '' = regular), so owning one does not count as owning the other.
 */
#[Fillable(['volume_number', 'volume_sort', 'edition', 'title', 'isbns', 'release_date'])]
class SeriesVolume extends Model
{
    /** @use HasFactory<SeriesVolumeFactory> */
    use HasFactory;

    protected $attributes = ['edition' => ''];

    protected function casts(): array
    {
        return ['volume_sort' => 'decimal:2', 'isbns' => 'array'];
    }

    /** @return BelongsTo<Series, $this> */
    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    public function isSpecialEdition(): bool
    {
        return $this->edition !== '';
    }
}
