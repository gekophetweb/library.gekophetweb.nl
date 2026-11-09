<?php

namespace App\Support\Series;

use Normalizer;

/**
 * Last-resort fallback: split a book title such as `ベルセルク 41`, `ONE PIECE 1`,
 * `Berserk Volume 1` or `Title, Vol. 3` into a series title and a volume number.
 *
 * Requires a separator (space or punctuation) before a bare number, so titles like
 * `Catch-22` or `1984` are not mistaken for volumes.
 */
final class TitleVolumeParser
{
    /** @var list<string> */
    private const array PATTERNS = [
        // Title (12) / Title（12）
        '/^(?<title>.+?)\s*\(\s*(?<volume>\d+(?:\.\d+)?)\s*\)$/u',
        // ベルセルク第12巻 / ベルセルク 12巻 — the 巻 marks the volume, no separator needed.
        '/^(?<title>.+?)\s*第?\s*(?<volume>\d+(?:\.\d+)?)\s*巻$/u',
        // Title, Vol. 3 / Title Volume 1 / Title #3 / Title Book 2
        '/^(?<title>.+?)[\s.,:;\-–—]*\s(?:vol(?:ume)?\.?|no\.|book)\s*(?<volume>\d+(?:\.\d+)?)$/iu',
        '/^(?<title>.+?)\s*\#\s*(?<volume>\d+(?:\.\d+)?)$/u',
        // ベルセルク 41 / ベルセルク. 1 / ONE PIECE 1
        '/^(?<title>.+?)(?:[.,:;]\s*|\s+)(?<volume>\d+(?:\.\d+)?)$/u',
    ];

    public function parse(?string $title): ?TitleVolume
    {
        if ($title === null) {
            return null;
        }

        $normalized = trim((string) preg_replace('/\s+/u', ' ', Normalizer::normalize($title, Normalizer::FORM_KC) ?: $title));

        foreach (self::PATTERNS as $pattern) {
            if (! preg_match($pattern, $normalized, $matches)) {
                continue;
            }

            $seriesTitle = trim($matches['title'], " \t.,:;-–—");

            if ($seriesTitle === '') {
                continue;
            }

            return new TitleVolume($seriesTitle, VolumeNumber::fromNumeric($matches['volume']));
        }

        return null;
    }
}
