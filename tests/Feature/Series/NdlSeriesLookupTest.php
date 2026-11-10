<?php

use App\Models\ApiCallLog;
use App\Services\Series\NdlSeriesLookup;
use App\Support\Isbn;
use App\Support\Series\Confidence;
use App\Support\Series\SeriesVolumeData;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => fakeSeriesApis());

function ndl(): NdlSeriesLookup
{
    return app(NdlSeriesLookup::class);
}

it('resolves Berserk volume 1 from its ISBN', function () {
    $match = ndl()->resolve(Isbn::fromString('9784592135746'), 'ja');

    expect($match->series->name)->toBe('ベルセルク')
        ->and($match->series->source)->toBe('ndl')
        ->and($match->series->externalId)->toBe('ベルセルク|三浦建太郎|白泉社')
        ->and($match->series->nameTranscription)->toBe('ベルセルク')
        ->and($match->series->creator)->toBe('三浦建太郎')
        ->and($match->series->publisher)->toBe('白泉社')
        ->and((string) $match->volume)->toBe('1')
        ->and($match->confidence)->toBe(Confidence::High)
        ->and($match->source)->toBe('ndl');
});

it('resolves volume 2 to the same series', function () {
    $first = ndl()->resolve(Isbn::fromString('9784592135746'), 'ja');
    $second = ndl()->resolve(Isbn::fromString('9784592135753'), 'ja');

    expect((string) $second->volume)->toBe('2')
        ->and($second->series->key())->toBe($first->series->key());
});

it('never takes the imprint as the series', function () {
    $match = ndl()->resolve(Isbn::fromString('9784592135746'), 'ja');

    expect($match->series->name)->not->toContain('Jets')
        ->and($match->series->externalId)->not->toContain('Jets');
});

it('returns null when NDL has no record', function () {
    expect(ndl()->resolve(Isbn::fromString('9780593820247'), 'ja'))->toBeNull();
});

it('falls back to a volume inside the title with medium confidence', function () {
    withoutSeriesFixtures();
    Http::fake(['ndlsearch.ndl.go.jp/*' => Http::response(<<<'XML'
        <rss xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:openSearch="http://a9.com/-/spec/opensearchrss/1.0/" version="2.0">
          <channel>
            <openSearch:totalResults>1</openSearch:totalResults>
            <item><category>図書</category><dc:title>ベルセルク. 1</dc:title><dc:publisher>白泉社</dc:publisher></item>
          </channel>
        </rss>
        XML)]);

    $match = ndl()->resolve(Isbn::fromString('9784592135746'), 'ja');

    expect($match->series->name)->toBe('ベルセルク')
        ->and((string) $match->volume)->toBe('1')
        ->and($match->confidence)->toBe(Confidence::Medium);
});

it('treats a book without a volume as not part of a series', function () {
    withoutSeriesFixtures();
    Http::fake(['ndlsearch.ndl.go.jp/*' => Http::response(<<<'XML'
        <rss xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:openSearch="http://a9.com/-/spec/opensearchrss/1.0/" version="2.0">
          <channel>
            <openSearch:totalResults>1</openSearch:totalResults>
            <item><category>図書</category><dc:title>ノルウェイの森</dc:title></item>
          </channel>
        </rss>
        XML)]);

    expect(ndl()->resolve(Isbn::fromString('9784592135746'), 'ja'))->toBeNull();
});

it('lists all 43 Berserk volumes, contiguous and without noise', function () {
    $series = ndl()->resolve(Isbn::fromString('9784592135746'), 'ja')->series;

    $volumes = ndl()->volumes($series);

    // The search also returns the light novel, art books, guidebooks, DVDs and
    // translations (Dark Horse, Glénat, …): all filtered out.
    expect($volumes->reject(fn (SeriesVolumeData $v) => $v->isSpecialEdition())
        ->map(fn (SeriesVolumeData $v) => (string) $v->volume)->values()->all())
        ->toBe(array_map('strval', range(1, 43)));
});

it('keeps special editions as separate entries, right after the regular edition', function () {
    $series = ndl()->resolve(Isbn::fromString('9784592135746'), 'ja')->series;
    $volumes = ndl()->volumes($series);

    expect($volumes)->toHaveCount(46)
        ->and($volumes->slice(40, 6)->map(fn (SeriesVolumeData $v) => [(string) $v->volume, $v->isSpecialEdition()])->values()->all())
        ->toBe([['41', false], ['41', true], ['42', false], ['42', true], ['43', false], ['43', true]]);

    $regular = $volumes[40];
    $special = $volumes[41];

    expect($regular->edition)->toBeNull()
        ->and($regular->isbns)->toHaveCount(1)
        ->and($regular->hasIsbn(Isbn::fromString('9784592166917')))->toBeTrue()
        ->and($special->edition)->toBe('キャンバスアート&ドラマCD付き特装版')
        ->and($special->isbns)->toHaveCount(1)
        ->and($special->hasIsbn(Isbn::fromString('9784592106296')))->toBeTrue()
        ->and($volumes[0]->hasIsbn(Isbn::fromString('9784592135746')))->toBeTrue()
        ->and($volumes[0]->releaseDate)->toBe('1990-12')
        ->and($volumes[45]->releaseDate)->toBe('2025-09');
});

it('writes an API call log row for every request', function () {
    $series = ndl()->resolve(Isbn::fromString('9784592135746'), 'ja')->series;
    ndl()->volumes($series);

    expect(ApiCallLog::query()->pluck('operation')->all())->toBe(['resolve', 'volumes']);

    $log = ApiCallLog::query()->firstWhere('operation', 'resolve');

    expect($log->provider)->toBe('ndl')
        ->and($log->query)->toBe('isbn=9784592135746')
        ->and($log->language)->toBe('ja')
        ->and($log->category)->toBe('books')
        ->and($log->outcome)->toBe(ApiCallLog::OUTCOME_SUCCESS)
        ->and($log->status_code)->toBe(200);
});

it('returns null and logs the failure when NDL errors', function () {
    withoutSeriesFixtures();
    Http::fake(['ndlsearch.ndl.go.jp/*' => Http::response('Service Unavailable', 503)]);

    expect(ndl()->resolve(Isbn::fromString('9784592135746'), 'ja'))->toBeNull();

    $log = ApiCallLog::query()->sole();

    expect($log->outcome)->toBe(ApiCallLog::OUTCOME_FAILED)
        ->and($log->status_code)->toBe(503)
        ->and($log->error)->toBe('Service Unavailable');
});

it('sends a descriptive user agent', function () {
    ndl()->resolve(Isbn::fromString('9784592135746'), 'ja');

    Http::assertSent(fn ($request) => str_contains($request->header('User-Agent')[0] ?? '', 'library.gekophetweb.nl'));
});
