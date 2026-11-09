<?php

namespace App\Support\Series;

use Normalizer;

/** Text normalization shared by the series providers. */
final class Text
{
    /** NFKC + collapsed whitespace: `ＹＯＵＮＧ　ＡＮＩＭＡＬ` → `YOUNG ANIMAL`. */
    public static function clean(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $text = Normalizer::normalize($text, Normalizer::FORM_KC) ?: $text;
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return $text === '' ? null : $text;
    }

    /** Comparison key: cleaned, lower-cased, without spaces. */
    public static function key(?string $text): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', '', (string) self::clean($text)));
    }

    public static function isLatin(string $text): bool
    {
        return ! preg_match('/[^\p{Latin}\p{Common}\p{Inherited}]/u', $text);
    }

    /**
     * Catalogue dates → ISO, keeping the source's precision:
     * `1990.12` → `1990-12`, `２０２５．９` → `2025-09`, `2020-09-21T00:00:00Z` → `2020-09-21`.
     */
    public static function date(?string $date): ?string
    {
        $date = self::clean($date);

        if ($date === null || ! preg_match('/(\d{4})(?:[.\-\/](\d{1,2}))?(?:[.\-\/](\d{1,2}))?/', $date, $m)) {
            return null;
        }

        return implode('-', array_filter([
            $m[1],
            isset($m[2]) && $m[2] !== '' ? str_pad($m[2], 2, '0', STR_PAD_LEFT) : null,
            isset($m[3]) && $m[3] !== '' ? str_pad($m[3], 2, '0', STR_PAD_LEFT) : null,
        ]));
    }
}
