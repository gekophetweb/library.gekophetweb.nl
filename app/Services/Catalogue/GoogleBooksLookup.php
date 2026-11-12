<?php

namespace App\Services\Catalogue;

use App\Contracts\CatalogueLookup;
use App\Contracts\CatalogueSearch;
use App\Enums\ApiKeyProvider;
use App\Enums\CategoryKind;
use App\Services\ApiCallLogger;
use App\Support\Catalogue\CatalogueContext;
use App\Support\Catalogue\CatalogueResult;
use App\Support\Isbn;
use App\Support\Series\Text;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;

/**
 * Google Books volumes search by ISBN. Works keyless (with a small daily quota); uses the
 * user's own key when one is saved. Never a shared global key.
 */
class GoogleBooksLookup implements CatalogueLookup, CatalogueSearch
{
    public function __construct(private readonly Http $http, private readonly ApiCallLogger $logger) {}

    public function name(): string
    {
        return 'google_books';
    }

    public function supports(CategoryKind $kind): bool
    {
        return $kind === CategoryKind::Books;
    }

    public function requiredKey(): ?ApiKeyProvider
    {
        return null;
    }

    public function lookup(string $barcode, CatalogueContext $context): ?CatalogueResult
    {
        $info = $this->request(['q' => 'isbn:'.$barcode], 'lookup', $context, config('catalogue.timeout'))[0]['volumeInfo'] ?? null;

        return is_array($info) ? $this->result($info, $barcode) : null;
    }

    /** A plain `q=` search covers titles and authors alike. */
    public function search(string $term, CatalogueContext $context, int $limit): array
    {
        $results = [];
        $params = ['q' => $term, 'maxResults' => min($limit, 40), 'printType' => 'books'];

        foreach ($this->request($params, 'search', $context, config('catalogue.search.timeout')) as $item) {
            $info = $item['volumeInfo'] ?? null;
            $isbn = collect(is_array($info) ? $info['industryIdentifiers'] ?? [] : [])
                ->firstWhere('type', 'ISBN_13')['identifier'] ?? null;
            $isbn = Isbn::tryFrom($isbn);

            if ($isbn !== null && ($result = $this->result($info, $isbn->value))) {
                $results[$isbn->value] ??= $result;
            }
        }

        return array_values($results);
    }

    /**
     * @param  array<string, scalar>  $query
     * @return list<array<string, mixed>> the `items`, empty on any failure
     */
    private function request(array $query, string $operation, CatalogueContext $context, int $timeout): array
    {
        $key = $context->user->apiKey(ApiKeyProvider::GoogleBooks);

        try {
            $response = $this->logger->send(
                provider: $this->name(),
                operation: $operation,
                query: http_build_query($query),
                request: fn () => $this->http->timeout($timeout)->acceptJson()
                    ->get(config('catalogue.providers.google_books.endpoint'), $query + array_filter(['key' => $key])),
                language: $context->language,
                category: $context->category->kind->value,
            );
        } catch (ConnectionException) {
            return [];
        }

        return $response->successful() ? array_values(array_filter((array) $response->json('items'), 'is_array')) : [];
    }

    /** @param  array<string, mixed>  $info  a volume's `volumeInfo` */
    private function result(array $info, string $barcode): ?CatalogueResult
    {
        if (Text::clean($info['title'] ?? null) === null) {
            return null;
        }

        $cover = $info['imageLinks']['thumbnail'] ?? $info['imageLinks']['smallThumbnail'] ?? null;

        return new CatalogueResult(
            source: $this->name(),
            barcode: $barcode,
            name: Text::clean($info['title']),
            nameRomanized: Text::isLatin($info['title']) ? Text::clean($info['title']) : null,
            creator: Text::clean(implode(', ', $info['authors'] ?? [])),
            publisher: Text::clean($info['publisher'] ?? null),
            releaseDate: Text::clean($info['publishedDate'] ?? null),
            coverUrl: $cover ? preg_replace('#^http://#', 'https://', $cover) : null,
            languageCode: Text::clean($info['language'] ?? null),
        );
    }
}
