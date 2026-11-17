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
            self::Rakuten => 'Your Rakuten app’s application ID and access key. Optional: used for Japanese book lookups.',
            self::GoogleBooks => 'Your Google Cloud API key with the Books API enabled. Used for book lookups and series of translated manga.',
            self::Hardcover => 'Your Hardcover API token. Used for series of translated manga.',
        };
    }

    /** What the main credential is called at the provider. */
    public function keyLabel(): string
    {
        return match ($this) {
            self::Rakuten => 'Application ID',
            self::GoogleBooks => 'API key',
            self::Hardcover => 'API token',
        };
    }

    /**
     * The second credential the provider needs next to the key, or null for none. Rakuten
     * wants an access key with every call since its 2026 API move.
     */
    public function secretLabel(): ?string
    {
        return match ($this) {
            self::Rakuten => 'Access key',
            default => null,
        };
    }

    public function needsSecret(): bool
    {
        return $this->secretLabel() !== null;
    }

    /**
     * Whether scanning waits for this key. Rakuten is optional: without it, lookups skip
     * Rakuten and use the other providers.
     */
    public function requiredToScan(): bool
    {
        return $this !== self::Rakuten;
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
