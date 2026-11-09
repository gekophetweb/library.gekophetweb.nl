<?php

namespace App\Support\Series;

/**
 * Identifies a series at an external source.
 *
 * `externalId` is the source's own ID where it has one (a Wikidata QID). NDL has no
 * series ID, so NdlSeriesLookup builds a stable key from title, creator and publisher.
 */
final readonly class SeriesReference
{
    public function __construct(
        public string $source,
        public string $externalId,
        public string $name,
        public ?string $nameRomanized = null,
        public ?string $nameEnglish = null,
        /** Reading of the original name, e.g. NDL's kana transcription. */
        public ?string $nameTranscription = null,
        public ?string $creator = null,
        public ?string $publisher = null,
    ) {}

    /** Cache / dedupe key, e.g. `wikidata:Q131612237`. */
    public function key(): string
    {
        return "{$this->source}:{$this->externalId}";
    }
}
