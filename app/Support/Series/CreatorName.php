<?php

namespace App\Support\Series;

use Normalizer;

/**
 * Normalizes creator names as library catalogues write them.
 *
 * NDL: `三浦, 建太郎, 1966-2021` → `三浦建太郎` (family name first, no space, life dates
 * dropped). Western inverted form: `Dinniman, Matt` → `Matt Dinniman`. A trailing role
 * such as `著` or `原作` is removed.
 */
final class CreatorName
{
    private const string ROLES = '著|作|画|原作|漫画|作画|文|絵|訳|編|監修|イラスト';

    public static function normalize(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $name = Normalizer::normalize($name, Normalizer::FORM_KC) ?: $name;
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        // Life dates: ", 1966-2021", ", 1966-", ", -2021", " (1966-2021)".
        $name = (string) preg_replace('/(?:,\s*|\s*\()\d{0,4}\s*-\s*\d{0,4}\)?$/u', '', $name);
        // Trailing role: "三浦建太郎 著", "三浦建太郎 原作".
        $name = trim((string) preg_replace('/\s+(?:'.self::ROLES.')$/u', '', $name));

        $parts = array_values(array_filter(
            array_map(trim(...), explode(',', $name)),
            fn (string $part) => $part !== '',
        ));

        if ($parts === []) {
            return null;
        }

        if (count($parts) === 1) {
            return $parts[0];
        }

        [$family, $given] = $parts;

        return self::isCjk($family.$given)
            ? $family.$given
            : "{$given} {$family}";
    }

    private static function isCjk(string $text): bool
    {
        return (bool) preg_match('/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]/u', $text);
    }
}
