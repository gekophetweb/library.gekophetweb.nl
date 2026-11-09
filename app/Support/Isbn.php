<?php

namespace App\Support;

use InvalidArgumentException;
use Normalizer;
use Stringable;

/**
 * A validated ISBN, always held as a hyphen-free ISBN-13.
 *
 * Accepts ISBN-10 and ISBN-13 in any common notation: hyphens, spaces, full-width
 * digits (NDL records use them) and a lower-case "x" check digit.
 */
final readonly class Isbn implements Stringable
{
    private function __construct(public string $value) {}

    public static function fromString(string $isbn): self
    {
        return self::tryFrom($isbn)
            ?? throw new InvalidArgumentException("Not a valid ISBN: [{$isbn}].");
    }

    public static function tryFrom(?string $isbn): ?self
    {
        if ($isbn === null) {
            return null;
        }

        $normalized = Normalizer::normalize($isbn, Normalizer::FORM_KC) ?: $isbn;
        $compact = strtoupper((string) preg_replace('/[^0-9Xx]/', '', $normalized));

        return match (strlen($compact)) {
            10 => self::isValidIsbn10($compact)
                ? new self(self::isbn10To13($compact))
                : null,
            13 => self::isValidIsbn13($compact) ? new self($compact) : null,
            default => null,
        };
    }

    public static function isValid(?string $isbn): bool
    {
        return self::tryFrom($isbn) !== null;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private static function isValidIsbn10(string $isbn): bool
    {
        if (! preg_match('/^\d{9}[\dX]$/', $isbn)) {
            return false;
        }

        $sum = 0;

        for ($i = 0; $i < 10; $i++) {
            $digit = $isbn[$i] === 'X' ? 10 : (int) $isbn[$i];
            $sum += $digit * (10 - $i);
        }

        return $sum % 11 === 0;
    }

    private static function isValidIsbn13(string $isbn): bool
    {
        if (! preg_match('/^97[89]\d{10}$/', $isbn)) {
            return false;
        }

        return self::isbn13CheckDigit(substr($isbn, 0, 12)) === (int) $isbn[12];
    }

    private static function isbn10To13(string $isbn10): string
    {
        $body = '978'.substr($isbn10, 0, 9);

        return $body.self::isbn13CheckDigit($body);
    }

    private static function isbn13CheckDigit(string $first12): int
    {
        $sum = 0;

        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $first12[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return (10 - $sum % 10) % 10;
    }
}
