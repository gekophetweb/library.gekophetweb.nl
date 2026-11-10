<?php

namespace App\Support\Series;

use App\Support\Isbn;

/**
 * One volume of a series as listed by an external source.
 *
 * A special edition (e.g. NDL `特装版`) is its own entry next to the regular edition of
 * the same volume number, so owning one does not count as owning the other.
 */
final readonly class SeriesVolumeData
{
    /**
     * @param  list<Isbn>  $isbns  The ISBNs of this edition (reprints share one entry).
     * @param  string|null  $releaseDate  ISO date, as precise as the source: `1990`, `1990-12` or `1990-12-01`.
     * @param  string|null  $edition  The source's edition label; null for the regular edition.
     */
    public function __construct(
        public VolumeNumber $volume,
        public ?string $title = null,
        public array $isbns = [],
        public ?string $releaseDate = null,
        public ?string $edition = null,
    ) {}

    public function isSpecialEdition(): bool
    {
        return $this->edition !== null;
    }

    public function hasIsbn(Isbn $isbn): bool
    {
        foreach ($this->isbns as $known) {
            if ($known->equals($isbn)) {
                return true;
            }
        }

        return false;
    }
}
