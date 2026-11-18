<?php

namespace App\Services\Catalogue;

use App\Contracts\CatalogueLookup;
use App\Contracts\CatalogueSearch;
use App\Enums\ApiKeyProvider;
use App\Enums\CategoryKind;
use App\Services\ApiCallLogger;
use App\Services\NdlRateLimiter;
use App\Support\Catalogue\CatalogueContext;
use App\Support\Catalogue\CatalogueResult;
use App\Support\Catalogue\CatalogueUnavailable;
use App\Support\Isbn;
use App\Support\Series\CreatorName;
use App\Support\Series\Text;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use SimpleXMLElement;

/**
 * NDL Search (国立国会図書館サーチ) OpenSearch by title, then by author, and by ISBN for a
 * scan. Keyless, so Japanese books are found without any key. Only printed books
 * (category 図書) with an ISBN are kept: a title search also returns CDs and DVDs.
 */
class NdlCatalogueSearch implements CatalogueLookup, CatalogueSearch
{
    private const string NS_DC = 'http://purl.org/dc/elements/1.1/';

    private const string NS_DCNDL = 'http://ndl.go.jp/dcndl/terms/';

    private const string NS_DCTERMS = 'http://purl.org/dc/terms/';

    private const string NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    public function __construct(
        private readonly Http $http,
        private readonly ApiCallLogger $logger,
        private readonly NdlRateLimiter $limiter,
    ) {}

    public function name(): string
    {
        return 'ndl';
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
     * The printed book with exactly this ISBN. NDL also answers unrelated records for an
     * unknown ISBN, and lists older books by their ISBN-10, so the match is checked here.
     */
    public function lookup(string $barcode, CatalogueContext $context): ?CatalogueResult
    {
        // NDL often takes 5-15 s; its own timeout keeps a slow answer from looking like "not found".
        $results = $this->query(['isbn' => $barcode, 'cnt' => 10], $context, 'lookup', config('catalogue.providers.ndl.timeout'), strict: true);

        foreach ($results as $result) {
            if ($result->barcode === $barcode) {
                return $result;
            }
        }

        return null;
    }

    public function search(string $term, CatalogueContext $context, int $limit): array
    {
        $results = [];

        foreach (['title', 'creator'] as $field) {
            foreach ($this->query([$field => $term, 'cnt' => $limit], $context) as $result) {
                $results[$result->barcode] ??= $result;
            }
        }

        return array_values($results);
    }

    /**
     * @param  array<string, scalar>  $query
     * @return list<CatalogueResult> empty on a failure, or with $strict throws
     *
     * @throws CatalogueUnavailable
     */
    private function query(array $query, CatalogueContext $context, string $operation = 'search', ?int $timeout = null, bool $strict = false): array
    {
        // Over the app-wide NDL budget on a page: treated like NDL not answering.
        if (! $this->limiter->acquire()) {
            return $strict ? throw CatalogueUnavailable::from($this->name()) : [];
        }

        try {
            $response = $this->logger->send(
                provider: $this->name(),
                operation: $operation,
                query: http_build_query($query),
                request: fn () => $this->http
                    ->withUserAgent(config('series.user_agent'))
                    ->timeout($timeout ?? config('catalogue.search.timeout'))
                    ->get(config('series.providers.ndl.endpoint'), $query),
                language: $context->language,
                category: $context->category->kind->value,
            );
        } catch (ConnectionException) {
            return $strict ? throw CatalogueUnavailable::from($this->name()) : [];
        }

        $xml = $response->successful() ? @simplexml_load_string($response->body()) : false;

        if ($xml === false || ! isset($xml->channel)) {
            return $strict ? throw CatalogueUnavailable::from($this->name(), $response->status()) : [];
        }

        $results = [];

        foreach ($xml->channel->item as $item) {
            if ($result = $this->result($item)) {
                $results[] = $result;
            }
        }

        return $results;
    }

    private function result(SimpleXMLElement $item): ?CatalogueResult
    {
        $categories = [];

        foreach ($item->category as $category) {
            $categories[] = (string) $category;
        }

        if (! in_array('図書', $categories, true)) {
            return null;
        }

        $dc = $item->children(self::NS_DC);
        $isbn = null;

        foreach ($dc->identifier as $identifier) {
            $type = (string) $identifier->attributes(self::NS_XSI)->type;

            if (in_array($type, ['dcndl:ISBN', 'dcndl:ISBN13'], true) && ($isbn = Isbn::tryFrom((string) $identifier))) {
                break;
            }
        }

        $title = Text::clean((string) $dc->title);

        if ($isbn === null || $title === null) {
            return null;
        }

        // The volume has its own field; the title parser expects it in the name.
        $volume = Text::clean((string) $item->children(self::NS_DCNDL)->volume);

        return new CatalogueResult(
            source: $this->name(),
            barcode: $isbn->value,
            name: $volume !== null ? "{$title} {$volume}" : $title,
            nameRomanized: Text::isLatin($title) ? $title : null,
            creator: CreatorName::normalize(Text::clean((string) $dc->creator)),
            publisher: Text::clean((string) $dc->publisher),
            releaseDate: Text::date((string) $item->children(self::NS_DCTERMS)->issued) ?? Text::date((string) $dc->date),
            languageCode: 'ja',
            nameReading: Text::clean((string) $item->children(self::NS_DCNDL)->titleTranscription),
            // `ミウラ, ケンタロウ, 1966-2021` → `ミウラ ケンタロウ`: family name first, like the name.
            creatorReading: Text::clean(str_replace(',', ' ', (string) preg_replace('/,\s*\d{0,4}\s*-\s*\d{0,4}$/u', '', (string) $item->children(self::NS_DCNDL)->creatorTranscription))),
        );
    }
}
