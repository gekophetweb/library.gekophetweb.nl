<?php

namespace App\Support\Catalogue;

use App\Enums\ApiKeyProvider;

/** The merged catalogue search results, plus why they may be thin. */
final readonly class CatalogueSearchOutcome
{
    /**
     * @param  list<CatalogueResult>  $results  one per ISBN-13
     * @param  list<ApiKeyProvider>  $missingKeys  providers skipped because the user has no key
     */
    public function __construct(
        public array $results,
        public array $missingKeys = [],
        public bool $noProvider = false,
    ) {}
}
