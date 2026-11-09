<?php

namespace App\Support\Series;

use InvalidArgumentException;
use Normalizer;
use Stringable;

/**
 * A volume number within a series. Decimal-capable so side stories such as "3.5"
 * sort between 3 and 4.
 *
 * parse() only accepts strings that are *just* a volume token (`1`, `４２`, `第12巻`,
 * `Vol. 12`, `#3`, `(12)`, `［2］`). Strings with other text around the number, such as
 * `EPISODE:1` or `黄金時代篇 1`, are rejected: they are not plain volumes of the series.
 */
final readonly class VolumeNumber implements Stringable
{
    private const string PATTERN = '/^(?:
            第\s*(?<kanji>\d+(?:\.\d+)?)\s*巻               # 第12巻
          | (?:vol(?:ume)?\.?|no\.?|book|\#)\s*(?<prefixed>\d+(?:\.\d+)?)   # Vol. 12, #3
          | [(\[]\s*(?<bracketed>\d+(?:\.\d+)?)\s*[)\]]     # (12), [2]
          | (?<plain>\d+(?:\.\d+)?)\s*巻?                   # 12, 12巻
        )$/xiu';

    private function __construct(private string $value) {}

    public static function parse(?string $volume): ?self
    {
        if ($volume === null) {
            return null;
        }

        $normalized = trim(Normalizer::normalize($volume, Normalizer::FORM_KC) ?: $volume);

        if (! preg_match(self::PATTERN, $normalized, $matches)) {
            return null;
        }

        foreach (['kanji', 'prefixed', 'bracketed', 'plain'] as $group) {
            if (($matches[$group] ?? '') !== '') {
                return self::fromNumeric($matches[$group]);
            }
        }

        return null;
    }

    public static function fromNumeric(int|float|string $number): self
    {
        if (! is_numeric($number) || (float) $number < 0) {
            throw new InvalidArgumentException("Not a volume number: [{$number}].");
        }

        $value = (string) $number;

        if (str_contains($value, '.')) {
            $value = rtrim(rtrim($value, '0'), '.');
        }

        $value = ltrim($value, '0');

        return new self(match (true) {
            $value === '', str_starts_with($value, '.') => '0'.$value,
            default => $value,
        });
    }

    /** The sortable numeric value, e.g. 3.5. */
    public function toFloat(): float
    {
        return (float) $this->value;
    }

    public function isWhole(): bool
    {
        return ! str_contains($this->value, '.');
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function compare(self $other): int
    {
        return $this->toFloat() <=> $other->toFloat();
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
