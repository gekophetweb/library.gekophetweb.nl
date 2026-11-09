<?php

namespace App\Support\Series;

use App\Support\Isbn;

/** One volume of a series as listed by an external source. */
final readonly class SeriesVolumeData
{
    /**
     * @param  list<Isbn>  $isbns  Every edition's ISBN (regular + special editions).
     * @param  string|null  $releaseDate  ISO date, as precise as the source: `1990`, `1990-12` or `1990-12-01`.
     */
    public function __construct(
        public VolumeNumber $volume,
        public ?string $title = null,
        public array $isbns = [],
        public ?string $releaseDate = null,
    ) {}

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
