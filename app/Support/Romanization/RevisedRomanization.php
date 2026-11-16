<?php

namespace App\Support\Romanization;

/**
 * Hangul → Revised Romanization of Korean: `한국어` → `hangugeo`, `박물관` → `bangmulgwan`.
 * Applies the sound changes that matter most in titles and names, within a word: a final
 * consonant moving onto a following silent ㅇ, nasalization before ㄴ / ㅁ, and the ㄹ
 * rules (`ll`, ㄹ → n after ㅁ / ㅇ). Hanja cannot be read, so text with Han gives null.
 * Anything that is not Hangul is kept. Output is lower case; callers capitalize.
 */
final class RevisedRomanization
{
    private const array INITIALS = ['g', 'kk', 'n', 'd', 'tt', 'r', 'm', 'b', 'pp', 's', 'ss', '', 'j', 'jj', 'ch', 'k', 't', 'p', 'h'];

    private const array VOWELS = ['a', 'ae', 'ya', 'yae', 'eo', 'e', 'yeo', 'ye', 'o', 'wa', 'wae', 'oe', 'yo', 'u', 'wo', 'we', 'wi', 'yu', 'eu', 'ui', 'i'];

    /** Final consonant as written before another consonant or at the end of a word. */
    private const array FINALS = ['', 'k', 'k', 'k', 'n', 'n', 'n', 't', 'l', 'k', 'm', 'l', 'l', 'l', 'p', 'l', 'm', 'p', 'p', 't', 't', 'ng', 't', 't', 'k', 't', 'p', 't'];

    /**
     * A final before a silent ㅇ: what stays, and which initial (index) moves over; null =
     * nothing moves (ㅎ is silent there). ㅇ itself (21) never moves.
     *
     * @var array<int, array{string, ?int}>
     */
    private const array LIAISON = [
        1 => ['', 0], 2 => ['', 1], 3 => ['k', 9], 4 => ['', 2], 5 => ['n', 12], 6 => ['n', null], 7 => ['', 3],
        8 => ['', 5], 9 => ['l', 0], 10 => ['l', 6], 11 => ['l', 7], 12 => ['l', 9], 13 => ['l', 16], 14 => ['l', 17],
        15 => ['l', null], 16 => ['', 6], 17 => ['', 7], 18 => ['p', 9], 19 => ['', 9], 20 => ['', 10],
        22 => ['', 12], 23 => ['', 14], 24 => ['', 15], 25 => ['', 16], 26 => ['', 17], 27 => ['', null],
    ];

    public static function fromHangul(string $text): ?string
    {
        if (Hepburn::hasKanji($text) || ! preg_match('/\p{Hangul}/u', $text)) {
            return null;
        }

        return (string) preg_replace_callback('/[\x{AC00}-\x{D7A3}]+/u', fn (array $word) => self::word($word[0]), $text);
    }

    private static function word(string $word): string
    {
        $syllables = array_map(function (string $char) {
            $index = mb_ord($char) - 0xAC00;

            return ['initial' => intdiv($index, 588), 'vowel' => intdiv($index % 588, 28), 'final' => $index % 28];
        }, mb_str_split($word));

        $out = '';

        // An index loop: a liaison changes the next syllable's initial before it is read.
        for ($i = 0, $n = count($syllables); $i < $n; $i++) {
            $syllable = $syllables[$i];
            $initial = self::INITIALS[$syllable['initial']];
            $previous = $syllables[$i - 1]['final'] ?? 0;

            // ㄹ after ㄹ or ㄴ is "l" (ll); after ㅁ / ㅇ it is pronounced (and written) "n".
            // ㄴ after ㄹ is "l" too.
            // An initial moved over from the previous final (레벨업 → [레베럽]) is kept as it is.
            $moved = isset($syllable['moved']);

            if (! $moved && $i > 0 && $syllable['initial'] === 5) {
                $initial = match ($previous) {
                    4, 8 => 'l',
                    16, 21 => 'n',
                    default => $initial,
                };
            } elseif (! $moved && $i > 0 && $syllable['initial'] === 2 && $previous === 8) {
                $initial = 'l';
            }

            $final = self::FINALS[$syllable['final']];
            $next = $syllables[$i + 1] ?? null;

            if ($next !== null && $syllable['final'] !== 0) {
                if ($next['initial'] === 11 && isset(self::LIAISON[$syllable['final']])) {
                    [$final, $moved] = self::LIAISON[$syllable['final']];

                    if ($moved !== null) {
                        $syllables[$i + 1]['initial'] = $moved;
                        $syllables[$i + 1]['moved'] = true;
                    }
                } elseif (in_array($next['initial'], [2, 6], true)) {
                    // Nasalization before ㄴ / ㅁ.
                    $final = match ($final) {
                        'k' => 'ng',
                        't' => 'n',
                        'p' => 'm',
                        default => $final,
                    };
                } elseif ($next['initial'] === 5 && $final === 'n') {
                    $final = 'l';
                }
            }

            $out .= $initial.self::VOWELS[$syllable['vowel']].$final;
        }

        return $out;
    }
}
