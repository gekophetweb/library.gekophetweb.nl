<?php

namespace App\Services\Catalogue;

use App\Contracts\CatalogueSearch;
use App\Enums\CategoryKind;
use App\Models\CollectionObject;
use App\Models\User;
use App\Support\Catalogue\CatalogueContext;
use App\Support\Catalogue\CatalogueResult;
use App\Support\Catalogue\CatalogueSearchOutcome;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Collection;

/**
 * Searches the catalogues by title or author for books the user may not own (plan
 * search-catalogue-not-owned). Providers are asked in the configured order for the
 * language, keyed ones are skipped without the user's key, and results are merged by
 * ISBN-13. Runs in the request (the user is waiting) and is cached per user, term,
 * category and language, since the user's keys decide which providers ran.
 */
class CatalogueSearchManager
{
    /** @var array<string, CatalogueSearch> */
    private array $providers = [];

    public function __construct(
        RakutenBooksLookup $rakuten,
        GoogleBooksLookup $googleBooks,
        NdlCatalogueSearch $ndl,
        OpenLibraryCatalogueSearch $openLibrary,
        private readonly Cache $cache,
    ) {
        foreach ([$rakuten, $googleBooks, $ndl, $openLibrary] as $provider) {
            $this->providers[$provider->name()] = $provider;
        }
    }

    public function search(string $term, CatalogueContext $context): CatalogueSearchOutcome
    {
        $chain = $this->chain($context->category->topLevel()->kind, $context->language);

        if ($chain === []) {
            return new CatalogueSearchOutcome([], noProvider: true);
        }

        $usable = array_filter($chain, fn (CatalogueSearch $provider) => $provider->requiredKey() === null
            || $context->user->apiKey($provider->requiredKey()) !== null);
        $missingKeys = array_values(array_map(fn (CatalogueSearch $provider) => $provider->requiredKey(), array_diff_key($chain, $usable)));

        $cacheKey = 'catalogue-search:'.sha1(implode('|', [
            $context->user->id, mb_strtolower($term), $context->category->topLevel()->id, $context->language,
            implode(',', array_map(fn (CatalogueSearch $provider) => $provider->name(), $usable)),
        ]));

        $results = $this->cache->remember($cacheKey, config('catalogue.search.cache_ttl'), function () use ($usable, $term, $context) {
            $merged = [];

            foreach ($usable as $provider) {
                foreach ($provider->search($term, $context, config('catalogue.search.limit')) as $result) {
                    $merged[$result->barcode] = isset($merged[$result->barcode])
                        ? $merged[$result->barcode]->mergedWith($result)
                        : $result;
                }
            }

            return array_map(fn (CatalogueResult $result) => $result->toArray(), array_values($merged));
        });

        return new CatalogueSearchOutcome(array_map(CatalogueResult::fromArray(...), $results), $missingKeys);
    }

    /**
     * The user's items matching the results by barcode, keyed by barcode, so each result
     * can be labelled Owned / On wishlist. Never looks beyond the user's own items.
     *
     * @param  list<CatalogueResult>  $results
     * @return Collection<string, CollectionObject>
     */
    public static function existing(User $user, array $results): Collection
    {
        $barcodes = array_map(fn (CatalogueResult $result) => $result->barcode, $results);

        return $barcodes === []
            ? collect()
            : CollectionObject::query()->ownedBy($user)->whereIn('barcode', $barcodes)->get()->keyBy('barcode');
    }

    /** @return array<int, CatalogueSearch> */
    private function chain(CategoryKind $kind, string $language): array
    {
        $order = match ($kind) {
            CategoryKind::Books => config("catalogue.search.books_order.{$language}") ?? config('catalogue.search.books_order.default'),
            // No Music or Games provider yet (guidelines §5).
            CategoryKind::Music, CategoryKind::Games => [],
        };

        return array_values(array_filter(array_map(fn (string $name) => $this->providers[$name] ?? null, $order)));
    }
}
