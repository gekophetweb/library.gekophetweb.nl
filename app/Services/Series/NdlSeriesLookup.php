<?php

namespace App\Services\Series;

use App\Contracts\SeriesLookup;
use App\Services\ApiCallLogger;
use App\Support\Isbn;
use App\Support\Series\Confidence;
use App\Support\Series\CreatorName;
use App\Support\Series\SeriesMatch;
use App\Support\Series\SeriesReference;
use App\Support\Series\SeriesVolumeData;
use App\Support\Series\Text;
use App\Support\Series\TitleVolumeParser;
use App\Support\Series\VolumeNumber;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Collection;
use SimpleXMLElement;

/**
 * NDL Search (国立国会図書館サーチ) OpenSearch — primary series source for Japanese.
 *
 * `dc:title` is the series title and `dcndl:volume` the volume. `dcndl:seriesTitle` is
 * the publisher's imprint (e.g. "Jets comics") and is deliberately never used as the
 * series.
 */
class NdlSeriesLookup implements SeriesLookup
{
    private const string NS_DC = 'http://purl.org/dc/elements/1.1/';

    private const string NS_DCNDL = 'http://ndl.go.jp/dcndl/terms/';

    private const string NS_DCTERMS = 'http://purl.org/dc/terms/';

    private const string NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    private const string NS_OPENSEARCH = 'http://a9.com/-/spec/opensearchrss/1.0/';

    /** Category NDL gives printed books, as opposed to DVDs, CDs, articles. */
    private const string CATEGORY_BOOK = '図書';

    public function __construct(
        private readonly Http $http,
        private readonly ApiCallLogger $logger,
        private readonly TitleVolumeParser $titleParser,
    ) {}

    public function name(): string
    {
        return 'ndl';
    }

    public function resolve(Isbn $isbn, string $language): ?SeriesMatch
    {
        $channel = $this->search(['isbn' => $isbn->value], 'resolve', $language);

        $records = collect($channel['records'] ?? [])
            ->filter(fn (array $record) => $record['is_book'] && $record['title'] !== null);

        $record = $records->first(fn (array $record) => VolumeNumber::parse($record['volume']) !== null)
            ?? $records->first();

        if ($record === null) {
            return null;
        }

        $title = $record['title'];
        $volume = VolumeNumber::parse($record['volume']);
        $confidence = Confidence::High;

        if ($volume === null) {
            // openBD-style records keep the volume inside the title: "ベルセルク. 1".
            $parsed = $this->titleParser->parse($title);

            if ($parsed === null) {
                return null; // a standalone book, not part of a numbered series
            }

            [$title, $volume, $confidence] = [$parsed->title, $parsed->volume, Confidence::Medium];
        }

        return new SeriesMatch(
            series: $this->reference($title, $record),
            volume: $volume,
            confidence: $confidence,
            source: $this->name(),
        );
    }

    public function supports(SeriesReference $series): bool
    {
        return $series->source === $this->name();
    }

    public function volumes(SeriesReference $series): Collection
    {
        $query = array_filter(['title' => $series->name, 'creator' => $series->creator]);
        $config = config('series.providers.ndl');
        $records = [];

        for ($page = 0; $page < $config['max_pages']; $page++) {
            $channel = $this->search($query + [
                'cnt' => $config['page_size'],
                'idx' => $page * $config['page_size'] + 1,
            ], 'volumes');

            if ($channel === null) {
                break;
            }

            array_push($records, ...$channel['records']);

            if (count($records) >= $channel['total'] || $channel['records'] === []) {
                break;
            }
        }

        $titleKey = Text::key($series->name);
        $publisherKey = Text::key($series->publisher);

        return collect($records)
            ->filter(fn (array $record) => $record['is_book']
                && Text::key($record['title']) === $titleKey
                && ($publisherKey === '' || Text::key($record['publisher']) === $publisherKey))
            ->map(fn (array $record) => $record + ['volume_number' => VolumeNumber::parse($record['volume'])])
            ->filter(fn (array $record) => $record['volume_number'] !== null)
            // Regular and special editions of one volume → one entry with all ISBNs.
            ->groupBy(fn (array $record) => (string) $record['volume_number'])
            ->map(function (Collection $editions) {
                $isbns = $editions->flatMap(fn (array $record) => $record['isbns'])
                    ->unique(fn (Isbn $isbn) => $isbn->value)
                    ->values()
                    ->all();

                return new SeriesVolumeData(
                    volume: $editions->first()['volume_number'],
                    isbns: $isbns,
                    releaseDate: $editions->pluck('issued')->filter()->sort()->first(),
                );
            })
            ->sort(fn (SeriesVolumeData $a, SeriesVolumeData $b) => $a->volume->compare($b->volume))
            ->values();
    }

    /** @param  array<string, mixed>  $record */
    private function reference(string $title, array $record): SeriesReference
    {
        $creator = $record['creator'];
        $publisher = $record['publisher'];

        return new SeriesReference(
            source: $this->name(),
            // NDL has no series ID: title + creator + publisher is stable across volumes.
            externalId: implode('|', [$title, (string) $creator, (string) $publisher]),
            name: $title,
            nameRomanized: Text::isLatin($title) ? $title : null,
            nameEnglish: null,
            nameTranscription: $record['transcription'],
            creator: $creator,
            publisher: $publisher,
        );
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array{total: int, records: list<array<string, mixed>>}|null
     */
    private function search(array $query, string $operation, ?string $language = 'ja'): ?array
    {
        try {
            $response = $this->logger->send(
                provider: $this->name(),
                operation: $operation,
                query: http_build_query($query),
                request: fn () => $this->http
                    ->withUserAgent(config('series.user_agent'))
                    ->timeout(config('series.timeout'))
                    ->get(config('series.providers.ndl.endpoint'), $query),
                language: $language,
            );
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $xml = @simplexml_load_string($response->body());

        if ($xml === false || ! isset($xml->channel)) {
            return null;
        }

        $channel = $xml->channel;
        $records = [];

        foreach ($channel->item as $item) {
            $records[] = $this->parseItem($item);
        }

        return [
            'total' => (int) $channel->children(self::NS_OPENSEARCH)->totalResults,
            'records' => $records,
        ];
    }

    /** @return array<string, mixed> */
    private function parseItem(SimpleXMLElement $item): array
    {
        $dc = $item->children(self::NS_DC);
        $dcndl = $item->children(self::NS_DCNDL);
        $dcterms = $item->children(self::NS_DCTERMS);

        $isbns = [];

        foreach ($dc->identifier as $identifier) {
            $type = (string) $identifier->attributes(self::NS_XSI)->type;

            if (in_array($type, ['dcndl:ISBN', 'dcndl:ISBN13'], true) && ($isbn = Isbn::tryFrom((string) $identifier))) {
                $isbns[$isbn->value] = $isbn;
            }
        }

        $categories = [];

        foreach ($item->category as $category) {
            $categories[] = (string) $category;
        }

        return [
            'title' => Text::clean((string) $dc->title),
            'transcription' => Text::clean((string) $dcndl->titleTranscription),
            'volume' => Text::clean((string) $dcndl->volume),
            'edition' => Text::clean((string) $dcndl->edition),
            'creator' => CreatorName::normalize(Text::clean((string) $dc->creator)),
            'publisher' => Text::clean((string) $dc->publisher),
            'issued' => Text::date((string) $dcterms->issued) ?? Text::date((string) $dc->date),
            'isbns' => array_values($isbns),
            'is_book' => in_array(self::CATEGORY_BOOK, $categories, true),
        ];
    }
}
