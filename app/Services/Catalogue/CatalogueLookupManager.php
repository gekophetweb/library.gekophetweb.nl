<?php

namespace App\Services\Catalogue;

use App\Contracts\CatalogueLookup;
use App\Enums\CategoryKind;
use App\Support\Catalogue\CatalogueContext;
use App\Support\Catalogue\CatalogueOutcome;
use App\Support\Catalogue\CatalogueUnavailable;
use App\Support\Isbn;

/**
 * Asks the providers for a category in their configured order and merges what they know
 * about the item (guidelines §5). Providers that need a key the user lacks are skipped and
 * reported, so the UI can point to settings.
 */
class CatalogueLookupManager
{
    /** @var array<string, CatalogueLookup> */
    private array $providers;

    public function __construct(
        RakutenBooksLookup $rakuten,
        GoogleBooksLookup $googleBooks,
        NdlCatalogueSearch $ndl,
        OpenLibraryCatalogueSearch $openLibrary,
    ) {
        foreach ([$rakuten, $googleBooks, $ndl, $openLibrary] as $provider) {
            $this->providers[$provider->name()] = $provider;
        }
    }

    public function lookup(string $barcode, CatalogueContext $context): CatalogueOutcome
    {
        $kind = $context->category->topLevel()->kind;
        $chain = $this->chain($kind, $context->language);

        if ($chain === []) {
            return new CatalogueOutcome(null, noProvider: true);
        }

        if ($kind === CategoryKind::Books) {
            $isbn = Isbn::tryFrom($barcode);

            // A Bookland EAN starts with 978 / 979; any other EAN-13 is not a book.
            if ($isbn === null || ! preg_match('/^97[89]/', $isbn->value)) {
                return new CatalogueOutcome(null, invalidBarcode: true);
            }

            $barcode = $isbn->value;
        }

        $result = null;
        $missingKeys = [];
        $unavailable = [];
        $answered = 0;

        foreach ($chain as $provider) {
            $key = $provider->requiredKey();

            if ($key !== null && $context->user->apiKey($key) === null) {
                $missingKeys[] = $key;

                continue;
            }

            try {
                $found = $provider->lookup($barcode, $context);
            } catch (CatalogueUnavailable) {
                // Timed out or refused: the next provider may still know it.
                $unavailable[] = $provider->name();

                continue;
            }

            $answered++;

            if ($found !== null) {
                $result = $result?->mergedWith($found) ?? $found;
            }
        }

        return new CatalogueOutcome($result, $missingKeys, unavailable: $unavailable, answered: $answered);
    }

    /** @return list<CatalogueLookup> */
    private function chain(CategoryKind $kind, string $language): array
    {
        $order = match ($kind) {
            CategoryKind::Books => config("catalogue.books_order.{$language}") ?? config('catalogue.books_order.default'),
            // No Music or Games provider yet (guidelines §5): leave the slots empty.
            CategoryKind::Music, CategoryKind::Games => [],
        };

        return array_values(array_filter(
            array_map(fn (string $name) => $this->providers[$name] ?? null, $order),
            fn (?CatalogueLookup $provider) => $provider?->supports($kind) ?? false,
        ));
    }
}
