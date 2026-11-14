<?php

namespace App\Support\Catalogue;

use App\Enums\ApiKeyProvider;

/** The merged answer of all providers, plus why it may be empty. */
final readonly class CatalogueOutcome
{
    /**
     * @param  list<ApiKeyProvider>  $missingKeys  providers skipped because the user has no key
     * @param  list<string>  $unavailable  providers asked that gave no usable answer
     */
    public function __construct(
        public ?CatalogueResult $result,
        public array $missingKeys = [],
        public bool $noProvider = false,
        public bool $invalidBarcode = false,
        public array $unavailable = [],
        public int $answered = 0,
    ) {}

    /**
     * Nothing found because no catalogue could be reached, not because none knows the
     * item: worth trying again later rather than adding it by hand.
     */
    public function failed(): bool
    {
        return $this->result === null && $this->answered === 0 && $this->unavailable !== [];
    }

    /** "NDL and Google Books": the catalogues that gave no answer, for a message. */
    public function unavailableNames(): string
    {
        $names = array_map(fn (string $provider) => match ($provider) {
            'ndl' => 'NDL',
            'google_books' => 'Google Books',
            'openlibrary' => 'Open Library',
            'rakuten' => 'Rakuten Books',
            default => $provider,
        }, $this->unavailable);

        return collect($names)->join(', ', ' and ');
    }
}
