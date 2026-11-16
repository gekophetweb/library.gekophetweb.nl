<?php

namespace App\Services\Romanization;

use App\Support\Romanization\Hepburn;
use App\Support\Romanization\RevisedRomanization;
use App\Support\Series\Text;
use App\Support\Series\TitleVolumeParser;

/**
 * Romanized names for Japanese and Korean titles, people and publishers:
 * - a kana reading from the catalogue, or a name in kana only → Hepburn with macrons;
 * - a name in Hangul → Revised Romanization;
 * - a person or publisher in kanji / hanja only → Wikidata's Latin label (one logged call).
 * Null when none of these applies (a Latin name needs none; kanji titles without a reading
 * stay as they are).
 */
class NameRomanizer
{
    /** Scan languages whose kanji / hanja names are looked up on Wikidata. */
    private const array WIKIDATA_LANGUAGES = ['ja', 'ko'];

    public function __construct(private readonly WikidataLatinName $wikidata, private readonly TitleVolumeParser $titles) {}

    /** `ベルセルク 1` with reading `ベルセルク` → `Beruseruku 1`. */
    public function title(string $name, ?string $reading = null): ?string
    {
        if (Text::isLatin($name)) {
            return null;
        }

        $romanized = $reading !== null ? Hepburn::fromKana($reading) : null;

        // Readings leave out the volume number that the title carries.
        if ($romanized !== null && ! preg_match('/\d/', $romanized) && ($volume = $this->titles->parse($name)?->volume)) {
            $romanized .= ' '.$volume;
        }

        $romanized ??= self::fromScript($name);

        return $romanized !== null ? self::capitalizeFirst($romanized) : null;
    }

    /** `三浦 建太郎` with reading `ミウラ ケンタロウ` → `Miura Kentarō`; without a reading, Wikidata. */
    public function person(string $name, ?string $reading, string $language): ?string
    {
        if (Text::isLatin($name)) {
            return null;
        }

        $romanized = ($reading !== null ? Hepburn::fromKana($reading) : null) ?? self::fromScript($name);

        return $romanized !== null ? self::capitalizeWords($romanized) : $this->fromWikidata($name, $language);
    }

    /** `スクウェア・エニックス` → `Sukuwea Enikkusu`, `白泉社` → Wikidata's `Hakusensha`. */
    public function publisher(string $name, string $language): ?string
    {
        if (Text::isLatin($name)) {
            return null;
        }

        $romanized = self::fromScript($name);

        return $romanized !== null ? self::capitalizeWords($romanized) : $this->fromWikidata($name, $language);
    }

    /** Kana-only or Hangul-only text, converted as it is written. */
    private static function fromScript(string $text): ?string
    {
        return Hepburn::fromKana($text) ?? RevisedRomanization::fromHangul($text);
    }

    private function fromWikidata(string $name, string $language): ?string
    {
        return Hepburn::hasKanji($name) && in_array($language, self::WIKIDATA_LANGUAGES, true)
            ? $this->wikidata->find($name, $language)
            : null;
    }

    private static function capitalizeFirst(string $text): string
    {
        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }

    /** Each word: `miura kentarō` → `Miura Kentarō` (not after an apostrophe: `Gen'in`). */
    private static function capitalizeWords(string $text): string
    {
        return (string) preg_replace_callback('/(^|[\s\/])(\p{Ll})/u', fn (array $m) => $m[1].mb_strtoupper($m[2]), $text);
    }
}
