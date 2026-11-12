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

/** Rakuten Books "Book Search" (20170404, formatVersion 2). Needs the user's application ID. */
class RakutenBooksLookup implements CatalogueLookup, CatalogueSearch
{
    public function __construct(private readonly Http $http, private readonly ApiCallLogger $logger) {}

    public function name(): string
    {
        return 'rakuten';
    }

    public function supports(CategoryKind $kind): bool
    {
        return $kind === CategoryKind::Books;
    }

    public function requiredKey(): ?ApiKeyProvider
    {
        return ApiKeyProvider::Rakuten;
    }

    public function lookup(string $barcode, CatalogueContext $context): ?CatalogueResult
    {
        $item = $this->request(['isbn' => $barcode], 'lookup', $context, config('catalogue.timeout'))[0] ?? null;

        return is_array($item) ? $this->result($item, $barcode) : null;
    }

    /** By title, then by author (the API has no "either" search). */
    public function search(string $term, CatalogueContext $context, int $limit): array
    {
        $results = [];

        foreach (['title', 'author'] as $field) {
            foreach ($this->request([$field => $term, 'hits' => min($limit, 30)], 'search', $context, config('catalogue.search.timeout')) as $item) {
                $isbn = is_array($item) ? Isbn::tryFrom($item['isbn'] ?? null) : null;

                if ($isbn !== null && ($result = $this->result($item, $isbn->value))) {
                    $results[$isbn->value] ??= $result;
                }
            }
        }

        return array_values($results);
    }

    /**
     * @param  array<string, scalar>  $params
     * @return list<mixed> the `Items`, empty on any failure
     */
    private function request(array $params, string $operation, CatalogueContext $context, int $timeout): array
    {
        $query = ['format' => 'json', 'formatVersion' => 2] + $params;

        try {
            $response = $this->logger->send(
                provider: $this->name(),
                operation: $operation,
                query: http_build_query($query), // the application ID stays out of the log
                request: fn () => $this->http->timeout($timeout)->acceptJson()
                    ->get(config('catalogue.providers.rakuten.endpoint'), $query + [
                        'applicationId' => $context->user->apiKey(ApiKeyProvider::Rakuten),
                    ]),
                language: $context->language,
                category: $context->category->kind->value,
            );
        } catch (ConnectionException) {
            return [];
        }

        return $response->successful() ? array_values((array) $response->json('Items')) : [];
    }

    /** @param  array<string, mixed>  $item */
    private function result(array $item, string $barcode): ?CatalogueResult
    {
        if (Text::clean($item['title'] ?? null) === null) {
            return null;
        }

        return new CatalogueResult(
            source: $this->name(),
            barcode: $barcode,
            name: Text::clean($item['title']),
            creator: Text::clean($item['author'] ?? null),
            publisher: Text::clean($item['publisherName'] ?? null),
            releaseDate: self::date($item['salesDate'] ?? null),
            coverUrl: Text::clean($item['largeImageUrl'] ?? null) ?? Text::clean($item['mediumImageUrl'] ?? null),
            languageCode: 'ja',
        );
    }

    /** "1990年12月", "1990年12月05日" or "1990年" → "1990-12" / "1990-12-05" / "1990". */
    private static function date(?string $salesDate): ?string
    {
        if ($salesDate === null || ! preg_match('/(\d{4})年(?:(\d{1,2})月)?(?:(\d{1,2})日)?/u', $salesDate, $m)) {
            return null;
        }

        return implode('-', array_filter([
            $m[1],
            isset($m[2]) && $m[2] !== '' ? str_pad($m[2], 2, '0', STR_PAD_LEFT) : null,
            isset($m[3]) && $m[3] !== '' ? str_pad($m[3], 2, '0', STR_PAD_LEFT) : null,
        ]));
    }
}
