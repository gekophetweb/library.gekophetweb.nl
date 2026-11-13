<?php

namespace App\Services\Series;

use App\Contracts\SeriesLookup;
use App\Services\ApiCallLogger;
use App\Support\Isbn;
use App\Support\Series\Confidence;
use App\Support\Series\SeriesMatch;
use App\Support\Series\SeriesReference;
use App\Support\Series\SeriesVolumeData;
use App\Support\Series\Text;
use App\Support\Series\TitleVolumeParser;
use App\Support\Series\VolumeNumber;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Collection;

/**
 * English (and other Latin-script) series: Open Library for ISBN → work, Wikidata for
 * work → series + ordinal and for the full volume list.
 *
 * One English book has many ISBNs, so the link is always ISBN → work → series, never
 * ISBN → series: Wikidata rarely carries ISBNs for these books.
 */
class OpenLibraryWikidataSeriesLookup implements SeriesLookup
{
    private const string ENTITY_PREFIX = 'http://www.wikidata.org/entity/';

    public function __construct(
        private readonly Http $http,
        private readonly ApiCallLogger $logger,
        private readonly TitleVolumeParser $titleParser,
    ) {}

    public function name(): string
    {
        return 'openlibrary+wikidata';
    }

    public function resolve(Isbn $isbn, string $language): ?SeriesMatch
    {
        $edition = $this->edition($isbn, $language);

        if ($edition === null) {
            return null;
        }

        $workId = basename((string) data_get($edition, 'works.0.key')) ?: null;
        [$olSeries, $olVolume] = $this->openLibrarySeries($edition);
        $title = Text::clean(data_get($edition, 'title'));

        $book = null;
        $confidence = Confidence::High;

        if ($workId !== null) {
            $book = $this->wikidataBookByWork($workId, $language);
        }

        if ($book === null && $olSeries !== null && $title !== null) {
            $book = $this->wikidataBookByLabels($olSeries, $title, $language);
            $confidence = Confidence::Medium;
        }

        if ($book !== null) {
            $volume = VolumeNumber::parse($book['ordinal']) ?? $olVolume;

            return new SeriesMatch(
                series: $this->wikidataReference($book, $language),
                volume: $volume,
                confidence: $volume === null ? Confidence::Medium : $confidence,
                source: $this->name(),
            );
        }

        if ($olSeries === null) {
            return null;
        }

        // Open Library names a series but Wikidata does not know it: a suggestion only.
        return new SeriesMatch(
            series: new SeriesReference(
                source: 'openlibrary',
                externalId: Text::key($olSeries),
                name: $olSeries,
                nameRomanized: Text::isLatin($olSeries) ? $olSeries : null,
                nameEnglish: $language === 'en' ? $olSeries : null,
            ),
            volume: $olVolume,
            confidence: Confidence::Low,
            source: 'openlibrary',
        );
    }

    public function supports(SeriesReference $series): bool
    {
        return $series->source === 'wikidata';
    }

    public function volumes(SeriesReference $series): Collection
    {
        $qid = $this->qid($series->externalId);

        if ($qid === null) {
            return collect();
        }

        $rows = $this->sparql('volumes', <<<SPARQL
            SELECT ?book ?bookLabel ?ordinal ?date ?isbn13 WHERE {
              ?book p:P179 ?statement .
              ?statement ps:P179 wd:{$qid} .
              ?statement pq:P1545 ?ordinal .
              OPTIONAL { ?book wdt:P577 ?date . }
              OPTIONAL { ?book wdt:P212 ?isbn13 . }
              SERVICE wikibase:label { bd:serviceParam wikibase:language "en" . }
            }
            SPARQL);

        return collect($rows)
            ->map(fn (array $row) => $row + ['volume' => VolumeNumber::parse($row['ordinal'] ?? null)])
            ->filter(fn (array $row) => $row['volume'] !== null)
            // A book with several dates or ISBNs comes back as several rows.
            ->groupBy(fn (array $row) => (string) $row['volume'])
            ->map(function (Collection $rows) {
                $isbns = $rows->map(fn (array $row) => Isbn::tryFrom($row['isbn13'] ?? null))
                    ->filter()
                    ->unique(fn (Isbn $isbn) => $isbn->value)
                    ->values()
                    ->all();

                return new SeriesVolumeData(
                    volume: $rows->first()['volume'],
                    title: Text::clean($rows->first()['bookLabel'] ?? null),
                    isbns: $isbns,
                    releaseDate: $rows->map(fn (array $row) => Text::date($row['date'] ?? null))->filter()->sort()->first(),
                );
            })
            ->sort(fn (SeriesVolumeData $a, SeriesVolumeData $b) => $a->volume->compare($b->volume))
            ->values();
    }

    /** @return array<string, mixed>|null */
    private function edition(Isbn $isbn, string $language): ?array
    {
        try {
            $response = $this->logger->send(
                provider: 'openlibrary',
                operation: 'edition',
                query: $isbn->value,
                request: fn () => $this->http
                    ->withUserAgent(config('series.user_agent'))
                    ->timeout(config('series.timeout'))
                    ->acceptJson()
                    ->get(rtrim(config('series.providers.openlibrary.endpoint'), '/')."/isbn/{$isbn->value}.json"),
                language: $language,
            );
        } catch (ConnectionException) {
            return null;
        }

        return $response->successful() ? $response->json() : null;
    }

    /**
     * Open Library's free-text series field, e.g. `Dungeon Crawler Carl` or
     * `Dungeon Crawler Carl #1`.
     *
     * @param  array<string, mixed>  $edition
     * @return array{0: ?string, 1: ?VolumeNumber}
     */
    private function openLibrarySeries(array $edition): array
    {
        $series = Text::clean(collect(data_get($edition, 'series', []))->first());

        if ($series === null) {
            return [null, null];
        }

        $parsed = $this->titleParser->parse($series);

        return $parsed === null ? [$series, null] : [$parsed->title, $parsed->volume];
    }

    /** @return array<string, string>|null */
    private function wikidataBookByWork(string $workId, string $language): ?array
    {
        $workId = addslashes($workId);

        return $this->firstBook('book-by-work', "?book wdt:P648 \"{$workId}\" .", $language);
    }

    /** @return array<string, string>|null */
    private function wikidataBookByLabels(string $series, string $title, string $language): ?array
    {
        $series = addslashes($series);
        $title = addslashes($title);

        return $this->firstBook('book-by-labels', <<<SPARQL
            ?seriesItem rdfs:label "{$series}"@en .
            ?book wdt:P179 ?seriesItem ;
                  rdfs:label "{$title}"@en .
            SPARQL, $language);
    }

    /** @return array<string, string>|null */
    private function firstBook(string $operation, string $bookPattern, string $language): ?array
    {
        $language = preg_replace('/[^a-z\-]/', '', strtolower($language)) ?: 'en';

        $rows = $this->sparql($operation, <<<SPARQL
            SELECT ?book ?series ?ordinal ?labelEn ?labelOriginal ?labelLatn WHERE {
              {$bookPattern}
              ?book p:P179 ?statement .
              ?statement ps:P179 ?series .
              OPTIONAL { ?statement pq:P1545 ?ordinal . }
              OPTIONAL { ?series rdfs:label ?labelEn . FILTER(LANG(?labelEn) = "en") }
              OPTIONAL { ?series rdfs:label ?labelOriginal . FILTER(LANG(?labelOriginal) = "{$language}") }
              OPTIONAL { ?series rdfs:label ?labelLatn . FILTER(LANG(?labelLatn) = "{$language}-latn") }
            }
            LIMIT 10
            SPARQL, $language);

        // Prefer the statement that carries an ordinal.
        return collect($rows)->sortByDesc(fn (array $row) => isset($row['ordinal']))->first();
    }

    /** @param  array<string, string>  $book */
    private function wikidataReference(array $book, string $language): SeriesReference
    {
        $english = Text::clean($book['labelEn'] ?? null);
        $original = Text::clean($book['labelOriginal'] ?? null) ?? $english;
        $qid = (string) $this->qid($book['series']);

        $name = $original ?? $qid;

        return new SeriesReference(
            source: 'wikidata',
            externalId: $qid,
            name: $name,
            nameRomanized: Text::isLatin($name) ? $name : Text::clean($book['labelLatn'] ?? null),
            nameEnglish: $english,
        );
    }

    /**
     * Runs a SPARQL query and flattens the bindings to [var => value].
     *
     * @return list<array<string, string>>
     */
    private function sparql(string $operation, string $query, ?string $language = null): array
    {
        try {
            $response = $this->logger->send(
                provider: 'wikidata',
                operation: $operation,
                query: $query,
                request: fn () => $this->http
                    ->withUserAgent(config('series.user_agent'))
                    ->timeout(config('series.timeout'))
                    ->accept('application/sparql-results+json')
                    ->get(config('series.providers.wikidata.endpoint'), ['query' => $query]),
                language: $language,
            );
        } catch (ConnectionException) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        return collect($response->json('results.bindings', []))
            ->map(fn (array $binding) => array_map(fn (array $value) => (string) $value['value'], $binding))
            ->values()
            ->all();
    }

    /** `http://www.wikidata.org/entity/Q123` or `Q123` → `Q123`; anything else → null. */
    private function qid(string $value): ?string
    {
        $qid = str_starts_with($value, self::ENTITY_PREFIX) ? substr($value, strlen(self::ENTITY_PREFIX)) : $value;

        return preg_match('/^Q\d+$/', $qid) ? $qid : null;
    }
}
