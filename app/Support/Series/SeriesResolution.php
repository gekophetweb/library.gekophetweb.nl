<?php

namespace App\Support\Series;

/**
 * Everything the provider chain found for one ISBN, best match first.
 * Lower-confidence matches are kept so the UI can offer them as suggestions.
 */
final readonly class SeriesResolution
{
    /** @param  list<SeriesMatch>  $matches */
    public function __construct(public array $matches = []) {}

    public function best(): ?SeriesMatch
    {
        return $this->matches[0] ?? null;
    }

    /** True when the best match may be applied without asking the user. */
    public function isConfident(): bool
    {
        return $this->best()?->confidence === Confidence::High;
    }

    public function isEmpty(): bool
    {
        return $this->matches === [];
    }
}
