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
            self::GoogleBooks => 'Your Google Cloud API key with the Books API enabled. Used for book lookups and series of translated manga.',
            self::Hardcover => 'Your Hardcover API token. Used for series of translated manga.',
        };
    }

    /** Where the user signs up for a key. */
    public function setupUrl(): string
    {
        return match ($this) {
            self::Rakuten => 'https://webservice.rakuten.co.jp/',
            self::GoogleBooks => 'https://console.cloud.google.com/apis/library/books.googleapis.com',
            self::Hardcover => 'https://hardcover.app/account/api',
        };
    }

    /**
     * The free account the key comes with. The step-by-step guide for each provider is
     * resources/views/settings/api-keys/guides/{value}.blade.php.
     */
    public function accountNeeded(): string
    {
        return match ($this) {
            self::Rakuten => 'Rakuten account',
            self::GoogleBooks => 'Google account',
            self::Hardcover => 'Hardcover account',
        };
    }
}
