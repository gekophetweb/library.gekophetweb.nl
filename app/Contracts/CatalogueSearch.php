<?php

namespace App\Contracts;

use App\Enums\ApiKeyProvider;
use App\Support\Catalogue\CatalogueContext;
use App\Support\Catalogue\CatalogueResult;

/**
 * "Which books match this title or author?" — finding items the user does not have in
 * hand (plan search-catalogue-not-owned). Separate from CatalogueLookup because some
 * catalogues (NDL, Open Library) only search here. Each result carries an ISBN-13 as its
 * barcode; results without one are dropped, as they could never be matched or scanned.
 */
interface CatalogueSearch
{
    /** Stable provider id, also used in the API call log. */
    public function name(): string;

    /** The user key this provider cannot search without, or null when it works keyless. */
    public function requiredKey(): ?ApiKeyProvider;

    /** @return list<CatalogueResult> */
    public function search(string $term, CatalogueContext $context, int $limit): array;
}
