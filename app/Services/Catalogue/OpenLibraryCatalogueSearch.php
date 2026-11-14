<?php

namespace App\Services\Catalogue;

use App\Contracts\CatalogueLookup;
use App\Contracts\CatalogueSearch;
use App\Enums\ApiKeyProvider;
use App\Enums\CategoryKind;
use App\Services\ApiCallLogger;
use App\Support\Catalogue\CatalogueContext;
use App\Support\Catalogue\CatalogueResult;
use App\Support\Catalogue\CatalogueUnavailable;
use App\Support\Isbn;
use App\Support\Series\Text;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;

/**
 * Open Library `search.json`: title and author in one query. Keyless. A result is a work
 * listing every edition's ISBNs; the first valid ISBN-13 stands for it.
 */
class OpenLibraryCatalogueSearch implements CatalogueLookup, CatalogueSearch
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

    public function supports(CategoryKind $kind): bool
    {
        return $kind === CategoryKind::Books;
    }

    /**
     * The work that lists this ISBN among its editions. Open Library describes the work,
     * so the result carries the scanned ISBN and the work's title, author and cover.
     */
    public function lookup(string $barcode, CatalogueContext $context): ?CatalogueResult
    {
        foreach ($this->docs(['isbn' => $barcode, 'limit' => 1], $context, 'lookup', config('catalogue.timeout'), strict: true) as $doc) {
            $title = Text::clean($doc['title'] ?? null);

            if ($title !== null && in_array($barcode, $doc['isbn'] ?? [], true)) {
                return $this->result($doc, $title, $barcode);
            }
        }

        return null;
    }

    public function search(string $term, CatalogueContext $context, int $limit): array
    {
        $results = [];

        foreach ($this->docs(['q' => $term, 'limit' => $limit], $context, 'search', config('catalogue.search.timeout')) as $doc) {
            $title = Text::clean($doc['title'] ?? null);
            $isbn = collect($doc['isbn'] ?? [])
                ->filter(fn ($isbn) => is_string($isbn) && strlen($isbn) === 13)
                ->map(fn (string $isbn) => Isbn::tryFrom($isbn))
                ->first(fn (?Isbn $isbn) => $isbn !== null && preg_match('/^97[89]/', $isbn->value) === 1);

            if ($title === null || $isbn === null) {
                continue;
            }

            $results[] = $this->result($doc, $title, $isbn->value, withPublisher: true);
        }

        return $results;
    }

    /**
     * A search.json document as a result. The publisher list spans every edition of the
     * work (often another country's first), so a lookup for one ISBN leaves it out.
     *
     * @param  array<string, mixed>  $doc
     */
    private function result(array $doc, string $title, string $barcode, bool $withPublisher = false): CatalogueResult
    {
        return new CatalogueResult(
            source: $this->name(),
            barcode: $barcode,
            name: $title,
            nameRomanized: Text::isLatin($title) ? $title : null,
            creator: Text::clean(implode(', ', array_slice($doc['author_name'] ?? [], 0, 3))),
            publisher: $withPublisher ? Text::clean($doc['publisher'][0] ?? null) : null,
            releaseDate: isset($doc['first_publish_year']) ? (string) $doc['first_publish_year'] : null,
            coverUrl: isset($doc['cover_i']) ? "https://covers.openlibrary.org/b/id/{$doc['cover_i']}-M.jpg" : null,
        );
    }

    /**
     * @param  array<string, scalar>  $params
     * @return list<array<string, mixed>> the `docs`; empty on a failure, or with $strict throws
     *
     * @throws CatalogueUnavailable
     */
    private function docs(array $params, CatalogueContext $context, string $operation, int $timeout, bool $strict = false): array
    {
        try {
            $response = $this->logger->send(
                provider: $this->name(),
                operation: $operation,
                query: http_build_query($params),
                request: fn () => $this->http
                    ->withUserAgent(config('series.user_agent'))
                    ->timeout($timeout)
                    ->acceptJson()
                    ->get(rtrim(config('series.providers.openlibrary.endpoint'), '/').'/search.json', $params + ['fields' => self::FIELDS]),
                language: $context->language,
                category: $context->category->kind->value,
            );
        } catch (ConnectionException) {
            return $strict ? throw CatalogueUnavailable::from($this->name()) : [];
        }

        if ($strict && ! $response->successful()) {
            throw CatalogueUnavailable::from($this->name(), $response->status());
        }

        return $response->successful() ? array_values(array_filter((array) $response->json('docs'), 'is_array')) : [];
    }
}
