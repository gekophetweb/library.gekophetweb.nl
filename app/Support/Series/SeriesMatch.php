<?php

namespace App\Support\Series;

/** A provider's answer to "which series is this book, and which volume?". */
final readonly class SeriesMatch
{
    public function __construct(
        public SeriesReference $series,
        public ?VolumeNumber $volume,
        public Confidence $confidence,
        /** Provider that produced the match, shown to the user ("from NDL"). */
        public string $source,
    ) {}
}
