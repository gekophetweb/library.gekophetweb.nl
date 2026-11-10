<?php

namespace App\Services\Catalogue;

use App\Contracts\CatalogueLookup;
use App\Enums\ApiKeyProvider;
use App\Enums\CategoryKind;
use App\Services\ApiCallLogger;
use App\Support\Catalogue\CatalogueContext;
use App\Support\Catalogue\CatalogueResult;
use App\Support\Series\Text;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;

/**
 * Google Books volumes search by ISBN. Works keyless (with a small daily quota); uses the
 * user's own key when one is saved. Never a shared global key.
 */
class GoogleBooksLookup implements CatalogueLookup
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
        $query = ['q' => 'isbn:'.$barcode];
        $key = $context->user->apiKey(ApiKeyProvider::GoogleBooks);

        try {
            $response = $this->logger->send(
                provider: $this->name(),
                operation: 'lookup',
                query: http_build_query($query),
                request: fn () => $this->http->timeout(config('catalogue.timeout'))->acceptJson()
                    ->get(config('catalogue.providers.google_books.endpoint'), $query + array_filter(['key' => $key])),
                language: $context->language,
                category: $context->category->kind->value,
            );
        } catch (ConnectionException) {
            return null;
        }

        $info = $response->successful() ? $response->json('items.0.volumeInfo') : null;

        if (! is_array($info) || Text::clean($info['title'] ?? null) === null) {
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
