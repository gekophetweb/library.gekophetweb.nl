<?php

namespace App\Support\Romanization;

/**
 * Kana → modified Hepburn with macrons: `ケンタロウ` → `kentarō`, `ワンピース` → `wanpīsu`,
 * `マッチャ` → `matcha`, `ゲンイン` → `gen'in`. Kanji cannot be read without a dictionary,
 * so text with kanji gives null; catalogues supply a kana reading for those (Rakuten's
 * `titleKana` / `authorKana`, NDL's transcriptions). Anything that is not kana is kept.
 * Output is lower case; callers capitalize.
 *
 * Long vowels `ou`, `oo` and `uu` become `ō` / `ū`; a reading has no word boundaries, so
 * a vowel pair across two words in one kana run (rare) is joined too.
 */
final class Hepburn
{
    private const array DIGRAPHS = [
        'キャ' => 'kya', 'キュ' => 'kyu', 'キョ' => 'kyo', 'シャ' => 'sha', 'シュ' => 'shu', 'ショ' => 'sho', 'シェ' => 'she',
        'チャ' => 'cha', 'チュ' => 'chu', 'チョ' => 'cho', 'チェ' => 'che', 'ニャ' => 'nya', 'ニュ' => 'nyu', 'ニョ' => 'nyo',
        'ヒャ' => 'hya', 'ヒュ' => 'hyu', 'ヒョ' => 'hyo', 'ミャ' => 'mya', 'ミュ' => 'myu', 'ミョ' => 'myo',
        'リャ' => 'rya', 'リュ' => 'ryu', 'リョ' => 'ryo', 'ギャ' => 'gya', 'ギュ' => 'gyu', 'ギョ' => 'gyo',
        'ジャ' => 'ja', 'ジュ' => 'ju', 'ジョ' => 'jo', 'ジェ' => 'je', 'ヂャ' => 'ja', 'ヂュ' => 'ju', 'ヂョ' => 'jo',
        'ビャ' => 'bya', 'ビュ' => 'byu', 'ビョ' => 'byo', 'ピャ' => 'pya', 'ピュ' => 'pyu', 'ピョ' => 'pyo',
        'ファ' => 'fa', 'フィ' => 'fi', 'フェ' => 'fe', 'フォ' => 'fo', 'フュ' => 'fyu',
        'ティ' => 'ti', 'トゥ' => 'tu', 'ディ' => 'di', 'ドゥ' => 'du', 'デュ' => 'dyu', 'テュ' => 'tyu',
        'ウィ' => 'wi', 'ウェ' => 'we', 'ウォ' => 'wo', 'ヴァ' => 'va', 'ヴィ' => 'vi', 'ヴェ' => 've', 'ヴォ' => 'vo', 'ヴュ' => 'vyu',
        'ツァ' => 'tsa', 'ツィ' => 'tsi', 'ツェ' => 'tse', 'ツォ' => 'tso', 'イェ' => 'ye', 'クァ' => 'kwa', 'グァ' => 'gwa',
    ];

    private const array SINGLE = [
        'ア' => 'a', 'イ' => 'i', 'ウ' => 'u', 'エ' => 'e', 'オ' => 'o',
        'カ' => 'ka', 'キ' => 'ki', 'ク' => 'ku', 'ケ' => 'ke', 'コ' => 'ko',
        'サ' => 'sa', 'シ' => 'shi', 'ス' => 'su', 'セ' => 'se', 'ソ' => 'so',
        'タ' => 'ta', 'チ' => 'chi', 'ツ' => 'tsu', 'テ' => 'te', 'ト' => 'to',
        'ナ' => 'na', 'ニ' => 'ni', 'ヌ' => 'nu', 'ネ' => 'ne', 'ノ' => 'no',
        'ハ' => 'ha', 'ヒ' => 'hi', 'フ' => 'fu', 'ヘ' => 'he', 'ホ' => 'ho',
        'マ' => 'ma', 'ミ' => 'mi', 'ム' => 'mu', 'メ' => 'me', 'モ' => 'mo',
        'ヤ' => 'ya', 'ユ' => 'yu', 'ヨ' => 'yo',
        'ラ' => 'ra', 'リ' => 'ri', 'ル' => 'ru', 'レ' => 're', 'ロ' => 'ro',
        'ワ' => 'wa', 'ヰ' => 'i', 'ヱ' => 'e', 'ヲ' => 'o',
        'ガ' => 'ga', 'ギ' => 'gi', 'グ' => 'gu', 'ゲ' => 'ge', 'ゴ' => 'go',
        'ザ' => 'za', 'ジ' => 'ji', 'ズ' => 'zu', 'ゼ' => 'ze', 'ゾ' => 'zo',
        'ダ' => 'da', 'ヂ' => 'ji', 'ヅ' => 'zu', 'デ' => 'de', 'ド' => 'do',
        'バ' => 'ba', 'ビ' => 'bi', 'ブ' => 'bu', 'ベ' => 'be', 'ボ' => 'bo',
        'パ' => 'pa', 'ピ' => 'pi', 'プ' => 'pu', 'ペ' => 'pe', 'ポ' => 'po', 'ヴ' => 'vu',
        'ァ' => 'a', 'ィ' => 'i', 'ゥ' => 'u', 'ェ' => 'e', 'ォ' => 'o', 'ャ' => 'ya', 'ュ' => 'yu', 'ョ' => 'yo',
        'ヮ' => 'wa', 'ヵ' => 'ka', 'ヶ' => 'ke',
    ];

    private const array MACRONS = ['a' => 'ā', 'i' => 'ī', 'u' => 'ū', 'e' => 'ē', 'o' => 'ō'];

    public static function fromKana(string $text): ?string
    {
        // Half-width to full-width katakana, hiragana to katakana.
        $text = mb_convert_kana($text, 'KVC');

        if (self::hasKanji($text) || ! preg_match('/\p{Katakana}/u', $text)) {
            return null;
        }

        // Kana runs (with the long-vowel mark and the middle dot) are converted; the rest is kept.
        return (string) preg_replace_callback(
            '/[\x{30A1}-\x{30FA}\x{30FC}\x{30FB}]+/u',
            fn (array $run) => self::run($run[0]),
            $text,
        );
    }

    /**
     * CJK ideographs (kanji / hanja) and the 々 repeat mark. Not `\p{Han}`: with script
     * extensions PCRE counts the middle dot `・` and other shared marks as Han too.
     */
    public static function hasKanji(string $text): bool
    {
        return (bool) preg_match('/[\x{3005}\x{3400}-\x{4DBF}\x{4E00}-\x{9FFF}\x{F900}-\x{FAFF}\x{20000}-\x{2FA1F}]/u', $text);
    }

    private static function run(string $kana): string
    {
        $chars = mb_str_split($kana);
        $out = '';
        $double = false;

        for ($i = 0, $n = count($chars); $i < $n; $i++) {
            $char = $chars[$i];
            $pair = $char.($chars[$i + 1] ?? '');

            if ($char === 'ッ') {
                $double = true;

                continue;
            }

            if ($char === '・') {
                $out .= ' ';
                $double = false;

                continue;
            }

            if ($char === 'ー') {
                $last = mb_substr($out, -1);
                $out = isset(self::MACRONS[$last]) ? mb_substr($out, 0, -1).self::MACRONS[$last] : $out;

                continue;
            }

            if ($char === 'ン') {
                $next = self::syllable($chars[$i + 1] ?? '', $chars[$i + 2] ?? '');
                $out .= $next !== null && preg_match('/^[aiueoy]/', $next) ? "n'" : 'n';
                $double = false;

                continue;
            }

            if (isset(self::DIGRAPHS[$pair])) {
                $syllable = self::DIGRAPHS[$pair];
                $i++;
            } else {
                $syllable = self::SINGLE[$char] ?? $char;
            }

            if ($double) {
                // Small tsu doubles the next consonant; before "ch" it is written "t".
                $out .= str_starts_with($syllable, 'ch') ? 't' : (preg_match('/^[a-z]/', $syllable) && ! preg_match('/^[aiueo]/', $syllable) ? $syllable[0] : '');
                $double = false;
            }

            $out .= $syllable;
        }

        return strtr($out, ['ou' => 'ō', 'oo' => 'ō', 'uu' => 'ū']);
    }

    private static function syllable(string $char, string $next): ?string
    {
        return self::DIGRAPHS[$char.$next] ?? self::SINGLE[$char] ?? null;
    }
}
