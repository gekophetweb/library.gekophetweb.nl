<?php

namespace App\Contracts;

use App\Enums\ApiKeyProvider;
use App\Enums\CategoryKind;
use App\Support\Catalogue\CatalogueContext;
use App\Support\Catalogue\CatalogueResult;
use App\Support\Catalogue\CatalogueUnavailable;

/**
 * "What is this item?" — one implementation per external catalogue (guidelines §5),
 * resolved by category kind by CatalogueLookupManager.
 */
interface CatalogueLookup
{
    /** Stable provider id, also used in the API call log. */
    public function name(): string;

    public function supports(CategoryKind $kind): bool;

    /** The user key this provider cannot work without, or null when it works keyless. */
    public function requiredKey(): ?ApiKeyProvider;

    /**
     * The item behind $barcode, or null when this provider answered that it does not know it.
     *
     * @throws CatalogueUnavailable when the provider gave no usable answer (timeout, refusal)
     */
    public function lookup(string $barcode, CatalogueContext $context): ?CatalogueResult;
}
