<?php

namespace App\Support\Series;

/**
 * How sure a provider is that a book belongs to a series.
 *
 * High: the source states series and volume as structured data (NDL dcndl:volume,
 * a Wikidata P179 statement). The manager's chain stops at the first High match.
 * Medium: structured series, but the volume was parsed from text or is unknown.
 * Low: only a name — a suggestion for the user to confirm.
 */
enum Confidence: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function rank(): int
    {
        return match ($this) {
            self::High => 3,
            self::Medium => 2,
            self::Low => 1,
        };
    }

    public function isAtLeast(self $other): bool
    {
        return $this->rank() >= $other->rank();
    }
}
