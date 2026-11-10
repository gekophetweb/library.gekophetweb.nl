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

/** Rakuten Books "Book Search" (20170404, formatVersion 2). Needs the user's application ID. */
class RakutenBooksLookup implements CatalogueLookup
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
        $query = ['format' => 'json', 'formatVersion' => 2, 'isbn' => $barcode];

        try {
            $response = $this->logger->send(
                provider: $this->name(),
                operation: 'lookup',
                query: http_build_query($query), // the application ID stays out of the log
                request: fn () => $this->http->timeout(config('catalogue.timeout'))->acceptJson()
                    ->get(config('catalogue.providers.rakuten.endpoint'), $query + [
                        'applicationId' => $context->user->apiKey(ApiKeyProvider::Rakuten),
                    ]),
                language: $context->language,
                category: $context->category->kind->value,
            );
        } catch (ConnectionException) {
            return null;
        }

        $item = $response->successful() ? $response->json('Items.0') : null;

        if (! is_array($item) || Text::clean($item['title'] ?? null) === null) {
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
