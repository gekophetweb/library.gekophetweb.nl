<?php

namespace App\Services\Series;

use App\Contracts\KeyedSeriesLookup;
use App\Enums\ApiKeyProvider;
use App\Services\ApiCallLogger;
use App\Support\Isbn;
use App\Support\Series\Confidence;
use App\Support\Series\SeriesMatch;
use App\Support\Series\SeriesReference;
use App\Support\Series\SeriesVolumeData;
use App\Support\Series\Text;
use App\Support\Series\VolumeNumber;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;

/**
 * Google Books series (plan series-google-hardcover): a volume's `seriesInfo` names the
 * series and its number; `series/get` gives the series title and
 * `series/membership/get` lists the volumes. `seriesInfo` mostly sits on Play Books
 * editions, which is why this comes after Open Library + Wikidata in the chain.
 *
 * Needs the user's key: keyless calls answer 429 (tests/Fixtures/catalogue).
 */
class GoogleBooksSeriesLookup implements KeyedSeriesLookup
{
    private ?string $key = null;

    public function __construct(
        private readonly Http $http,
        private readonly ApiCallLogger $logger,
    ) {}

    public function name(): string
    {
        return 'google_books';
    }

    public function requiredKey(): ApiKeyProvider
    {
        return ApiKeyProvider::GoogleBooks;
    }

    public function withKey(string $key): static
    {
        $copy = clone $this;
        $copy->key = $key;

        return $copy;
    }

    public function resolve(Isbn $isbn, string $language): ?SeriesMatch
    {
        $volume = $this->get('volume', $isbn->value, 'volumes', ['q' => "isbn:{$isbn->value}", 'maxResults' => 5], $language)
            ?->json('items', []);

        // Only an item that really carries this ISBN, and has series info.
        $item = collect($volume ?? [])->first(fn (array $item) => $this->isbns($item)->contains(fn (Isbn $known) => $known->equals($isbn))
            && data_get($item, 'volumeInfo.seriesInfo.volumeSeries.0.seriesId') !== null);

        if ($item === null) {
            return null;
        }

        $seriesId = (string) data_get($item, 'volumeInfo.seriesInfo.volumeSeries.0.seriesId');
        $name = Text::clean($this->get('series', $seriesId, 'series/get', ['series_id' => $seriesId], $language)?->json('series.0.title'));

        if ($name === null) {
            return null;
        }

        $number = $this->number($item);

        return new SeriesMatch(
            series: new SeriesReference(
                source: $this->name(),
                externalId: $seriesId,
                name: $name,
                nameRomanized: Text::isLatin($name) ? $name : null,
                nameEnglish: $language === 'en' ? $name : null,
                creator: Text::clean(data_get($item, 'volumeInfo.authors.0')),
                publisher: Text::clean(data_get($item, 'volumeInfo.publisher')),
            ),
            volume: $number,
            // Google's own series id: exact when the number is known too.
            confidence: $number === null ? Confidence::Medium : Confidence::High,
            source: $this->name(),
        );
    }

    public function supports(SeriesReference $series): bool
    {
        return $series->source === $this->name();
    }

    public function volumes(SeriesReference $series): Collection
    {
        $members = collect();
        $token = null;

        for ($page = 0; $page < (int) config('series.providers.google_books.max_pages'); $page++) {
            $response = $this->get('membership', $series->externalId, 'series/membership/get', array_filter([
                'series_id' => $series->externalId,
                'page_size' => config('series.providers.google_books.page_size'),
                'page_token' => $token,
            ]));

            if ($response === null) {
                break;
            }

            $members = $members->merge($response->json('member', []));
            $token = $response->json('nextPageToken');

            if (! $token) {
                break;
            }
        }

        return $members
            ->map(fn (array $item) => ['item' => $item, 'volume' => $this->number($item)])
            ->filter(fn (array $row) => $row['volume'] !== null)
            // A volume in several editions (print, ebook) comes back several times.
            ->groupBy(fn (array $row) => (string) $row['volume'])
            ->map(fn (Collection $rows) => new SeriesVolumeData(
                volume: $rows->first()['volume'],
                title: Text::clean(data_get($rows->first()['item'], 'volumeInfo.title')),
                isbns: $rows->flatMap(fn (array $row) => $this->isbns($row['item']))->unique(fn (Isbn $isbn) => $isbn->value)->values()->all(),
                releaseDate: $rows->map(fn (array $row) => Text::date(data_get($row['item'], 'volumeInfo.publishedDate')))->filter()->sort()->first(),
            ))
            ->sort(fn (SeriesVolumeData $a, SeriesVolumeData $b) => $a->volume->compare($b->volume))
            ->values();
    }

    /** `bookDisplayNumber` ("1", "1.5"), else the numeric `orderNumber`. */
    private function number(array $item): ?VolumeNumber
    {
        $display = data_get($item, 'volumeInfo.seriesInfo.bookDisplayNumber');
        $order = data_get($item, 'volumeInfo.seriesInfo.volumeSeries.0.orderNumber');

        return VolumeNumber::parse(is_scalar($display) ? (string) $display : null)
            ?? (is_numeric($order) ? VolumeNumber::fromNumeric($order) : null);
    }

    /** @return Collection<int, Isbn> */
    private function isbns(array $item): Collection
    {
        return collect(data_get($item, 'volumeInfo.industryIdentifiers', []))
            ->filter(fn (array $id) => in_array($id['type'] ?? null, ['ISBN_13', 'ISBN_10'], true))
            ->map(fn (array $id) => Isbn::tryFrom($id['identifier'] ?? null))
            ->filter()
            ->values();
    }

    /** @param  array<string, mixed>  $query */
    private function get(string $operation, string $subject, string $path, array $query, ?string $language = null): ?Response
    {
        if ($this->key === null) {
            return null;
        }

        try {
            $response = $this->logger->send(
                provider: $this->name(),
                operation: "series-{$operation}",
                query: $subject,
                request: fn () => $this->http
                    ->timeout(config('series.timeout'))
                    ->acceptJson()
                    ->get(rtrim(config('series.providers.google_books.endpoint'), '/')."/{$path}", $query + ['key' => $this->key]),
                language: $language,
            );
        } catch (ConnectionException) {
            return null;
        }

        return $response->successful() ? $response : null;
    }
}
