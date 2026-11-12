<?php

namespace App\Enums;

/** External services a user can store their own key for (guidelines §5.1). */
enum ApiKeyProvider: string
{
    case Rakuten = 'rakuten';
    case GoogleBooks = 'google_books';
    case Hardcover = 'hardcover';

    public function label(): string
    {
        return match ($this) {
            self::Rakuten => 'Rakuten Books',
            self::GoogleBooks => 'Google Books',
            self::Hardcover => 'Hardcover',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Rakuten => 'Your Rakuten Web Service application ID. Needed for Japanese book lookups.',
            self::GoogleBooks => 'Optional: lookups work without a key, a key raises the daily limit.',
            self::Hardcover => 'Your Hardcover API token. Used for series of translated manga.',
        };
    }
}
