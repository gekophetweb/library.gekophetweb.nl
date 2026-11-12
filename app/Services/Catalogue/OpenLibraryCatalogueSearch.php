<?php

namespace App\Services\Catalogue;

use App\Contracts\CatalogueSearch;
use App\Enums\ApiKeyProvider;
use App\Services\ApiCallLogger;
use App\Support\Catalogue\CatalogueContext;
use App\Support\Catalogue\CatalogueResult;
use App\Support\Isbn;
use App\Support\Series\Text;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;

/**
 * Open Library `search.json`: title and author in one query. Keyless. A result is a work
 * listing every edition's ISBNs; the first valid ISBN-13 stands for it.
 */
class OpenLibraryCatalogueSearch implements CatalogueSearch
{
    private const string FIELDS = 'key,title,author_name,publisher,first_publish_year,isbn,cover_i,language';

    public function __construct(private readonly Http $http, private readonly ApiCallLogger $logger) {}

    public function name(): string
    {
        return 'openlibrary';
    }

    public function requiredKey(): ?ApiKeyProvider
    {
        return null;
    }

    public function search(string $term, CatalogueContext $context, int $limit): array
    {
        $query = ['q' => $term, 'fields' => self::FIELDS, 'limit' => $limit];

        try {
            $response = $this->logger->send(
                provider: $this->name(),
                operation: 'search',
                query: http_build_query(['q' => $term, 'limit' => $limit]),
                request: fn () => $this->http
                    ->withUserAgent(config('series.user_agent'))
                    ->timeout(config('catalogue.search.timeout'))
                    ->acceptJson()
                    ->get(rtrim(config('series.providers.openlibrary.endpoint'), '/').'/search.json', $query),
                language: $context->language,
                category: $context->category->kind->value,
            );
        } catch (ConnectionException) {
            return [];
        }

        $results = [];

        foreach ($response->successful() ? (array) $response->json('docs') : [] as $doc) {
            $title = Text::clean($doc['title'] ?? null);
            $isbn = collect($doc['isbn'] ?? [])
                ->filter(fn ($isbn) => is_string($isbn) && strlen($isbn) === 13)
                ->map(fn (string $isbn) => Isbn::tryFrom($isbn))
                ->first(fn (?Isbn $isbn) => $isbn !== null && preg_match('/^97[89]/', $isbn->value) === 1);

            if ($title === null || $isbn === null) {
                continue;
            }

            $results[] = new CatalogueResult(
                source: $this->name(),
                barcode: $isbn->value,
                name: $title,
                nameRomanized: Text::isLatin($title) ? $title : null,
                creator: Text::clean(implode(', ', array_slice($doc['author_name'] ?? [], 0, 3))),
                publisher: Text::clean($doc['publisher'][0] ?? null),
                releaseDate: isset($doc['first_publish_year']) ? (string) $doc['first_publish_year'] : null,
                coverUrl: isset($doc['cover_i']) ? "https://covers.openlibrary.org/b/id/{$doc['cover_i']}-M.jpg" : null,
            );
        }

        return $results;
    }
}
