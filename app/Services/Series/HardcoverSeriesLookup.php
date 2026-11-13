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
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Hardcover.app series (plan series-google-hardcover), over its GraphQL API: edition by
 * ISBN-13 → book → `book_series` (series + position), and a series' `book_series` for the
 * volume list. Good on translated manga, where Open Library has one work per volume.
 *
 * Needs the user's API token. Hardcover shows it as "Bearer …"; either form is accepted.
 */
class HardcoverSeriesLookup implements KeyedSeriesLookup
{
    private ?string $token = null;

    public function __construct(
        private readonly Http $http,
        private readonly ApiCallLogger $logger,
    ) {}

    public function name(): string
    {
        return 'hardcover';
    }

    public function requiredKey(): ApiKeyProvider
    {
        return ApiKeyProvider::Hardcover;
    }

    public function withKey(string $key): static
    {
        $copy = clone $this;
        $copy->token = trim(Str::after(trim($key), 'Bearer '));

        return $copy;
    }

    public function resolve(Isbn $isbn, string $language): ?SeriesMatch
    {
        $book = data_get($this->query('edition', $isbn->value, <<<'GRAPHQL'
            query SeriesByIsbn($isbn: String!) {
              editions(where: {isbn_13: {_eq: $isbn}}, limit: 1) {
                book {
                  title
                  contributions(limit: 1) { author { name } }
                  book_series(limit: 1) { position series { id name } }
                }
              }
            }
            GRAPHQL, ['isbn' => $isbn->value], $language), 'editions.0.book');

        $entry = data_get($book, 'book_series.0');
        $name = Text::clean(data_get($entry, 'series.name'));

        if ($entry === null || $name === null || data_get($entry, 'series.id') === null) {
            return null;
        }

        $volume = $this->position($entry['position'] ?? null);

        return new SeriesMatch(
            series: new SeriesReference(
                source: $this->name(),
                externalId: (string) data_get($entry, 'series.id'),
                name: $name,
                nameRomanized: Text::isLatin($name) ? $name : null,
                nameEnglish: $language === 'en' ? $name : null,
                creator: Text::clean(data_get($book, 'contributions.0.author.name')),
            ),
            volume: $volume,
            confidence: $volume === null ? Confidence::Medium : Confidence::High,
            source: $this->name(),
        );
    }

    public function supports(SeriesReference $series): bool
    {
        return $series->source === $this->name();
    }

    public function volumes(SeriesReference $series): Collection
    {
        if (! ctype_digit($series->externalId)) {
            return collect();
        }

        $entries = data_get($this->query('volumes', $series->externalId, <<<'GRAPHQL'
            query SeriesVolumes($id: Int!) {
              series_by_pk(id: $id) {
                book_series(order_by: {position: asc}) {
                  position
                  book {
                    title
                    release_date
                    editions(where: {isbn_13: {_is_null: false}}) { isbn_13 }
                  }
                }
              }
            }
            GRAPHQL, ['id' => (int) $series->externalId]), 'series_by_pk.book_series', []);

        return collect($entries)
            ->map(fn (array $entry) => ['entry' => $entry, 'volume' => $this->position($entry['position'] ?? null)])
            ->filter(fn (array $row) => $row['volume'] !== null)
            // Hardcover can list several books at one position (omnibus, box set): the
            // first one names the volume, all of them contribute ISBNs.
            ->groupBy(fn (array $row) => (string) $row['volume'])
            ->map(fn (Collection $rows) => new SeriesVolumeData(
                volume: $rows->first()['volume'],
                title: Text::clean(data_get($rows->first()['entry'], 'book.title')),
                isbns: $rows->flatMap(fn (array $row) => data_get($row['entry'], 'book.editions', []))
                    ->map(fn (array $edition) => Isbn::tryFrom($edition['isbn_13'] ?? null))
                    ->filter()
                    ->unique(fn (Isbn $isbn) => $isbn->value)
                    ->values()
                    ->all(),
                releaseDate: Text::date(data_get($rows->first()['entry'], 'book.release_date')),
            ))
            ->sort(fn (SeriesVolumeData $a, SeriesVolumeData $b) => $a->volume->compare($b->volume))
            ->values();
    }

    private function position(mixed $position): ?VolumeNumber
    {
        return is_numeric($position) && (float) $position >= 0 ? VolumeNumber::fromNumeric($position) : null;
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>|null `data`, or null on a failed call or GraphQL errors
     */
    private function query(string $operation, string $subject, string $query, array $variables, ?string $language = null): ?array
    {
        if ($this->token === null) {
            return null;
        }

        try {
            $response = $this->logger->send(
                provider: $this->name(),
                operation: "series-{$operation}",
                query: $subject,
                request: fn () => $this->http
                    ->withUserAgent(config('series.user_agent'))
                    ->timeout(config('series.timeout'))
                    ->withToken($this->token)
                    ->acceptJson()
                    ->post(config('series.providers.hardcover.endpoint'), ['query' => $query, 'variables' => $variables]),
                language: $language,
            );
        } catch (ConnectionException) {
            return null;
        }

        // GraphQL answers 200 with `errors` on a bad query or token.
        if (! $response->successful() || $response->json('errors') !== null) {
            return null;
        }

        return $response->json('data');
    }
}
