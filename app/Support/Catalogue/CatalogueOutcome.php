<?php

namespace App\Support\Catalogue;

use App\Enums\ApiKeyProvider;

/** The merged answer of all providers, plus why it may be empty. */
final readonly class CatalogueOutcome
{
    /** @param  list<ApiKeyProvider>  $missingKeys  providers skipped because the user has no key */
    public function __construct(
        public ?CatalogueResult $result,
        public array $missingKeys = [],
        public bool $noProvider = false,
        public bool $invalidBarcode = false,
    ) {}
}
