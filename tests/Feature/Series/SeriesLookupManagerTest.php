<?php

use App\Services\Series\NdlSeriesLookup;
use App\Services\Series\OpenLibraryWikidataSeriesLookup;
use App\Services\Series\SeriesLookupManager;
use App\Support\Isbn;
use App\Support\Series\Confidence;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => fakeSeriesApis());

function seriesManager(): SeriesLookupManager
{
    return app(SeriesLookupManager::class);
}

function sentTo(string $host): Closure
{
    return fn (Request $request) => str_contains($request->url(), $host);
}

it('uses NDL for a Japanese scan and never calls Open Library', function (string $language) {
    $resolution = seriesManager()->resolve(Isbn::fromString('9784592135746'), $language);

    expect($resolution->best()->source)->toBe('ndl')
        ->and($resolution->isConfident())->toBeTrue();

    Http::assertNotSent(sentTo('openlibrary.org'));
    Http::assertNotSent(sentTo('wikidata.org'));
})->with(['ja', 'ja-JP', 'jpn']);

it('uses Open Library + Wikidata for an English scan and never calls NDL', function () {
    $resolution = seriesManager()->resolve(Isbn::fromString('9780593820247'), 'en');

    expect($resolution->best()->series->externalId)->toBe('Q131612237');

    Http::assertNotSent(sentTo('ndlsearch.ndl.go.jp'));
});

it('uses the default chain for other languages', function () {
    expect(seriesManager()->chainFor('nl'))->toHaveCount(1)
        ->and(seriesManager()->chainFor('nl')[0])->toBeInstanceOf(OpenLibraryWikidataSeriesLookup::class);
});

it('falls through to the next provider on a miss', function () {
    config(['series.chains.ja' => [NdlSeriesLookup::class, OpenLibraryWikidataSeriesLookup::class]]);

    // NDL has no record of the English book, so Open Library + Wikidata answers.
    $resolution = seriesManager()->resolve(Isbn::fromString('9780593820247'), 'ja');

    expect($resolution->best()->series->externalId)->toBe('Q131612237');
    Http::assertSent(sentTo('ndlsearch.ndl.go.jp'));
});

it('stops at the first high-confidence match', function () {
    config(['series.chains.ja' => [NdlSeriesLookup::class, OpenLibraryWikidataSeriesLookup::class]]);

    seriesManager()->resolve(Isbn::fromString('9784592135746'), 'ja');

    Http::assertNotSent(sentTo('openlibrary.org'));
});

it('keeps lower-confidence matches as suggestions and continues the chain', function () {
    config(['series.chains.en' => [OpenLibraryWikidataSeriesLookup::class, NdlSeriesLookup::class]]);
    withoutSeriesFixtures();
    Http::fake([
        'openlibrary.org/*' => Http::response(['title' => 'Berserk', 'series' => ['ベルセルク']]),
        'query.wikidata.org/*' => Http::response(['results' => ['bindings' => []]]),
        'ndlsearch.ndl.go.jp/*' => Http::response(seriesFixture('ndl-isbn-9784592135746.xml')),
    ]);

    $resolution = seriesManager()->resolve(Isbn::fromString('9784592135746'), 'en');

    expect($resolution->matches)->toHaveCount(2)
        ->and($resolution->best()->source)->toBe('ndl')
        ->and($resolution->matches[1]->confidence)->toBe(Confidence::Low);
});

it('falls back to parsing the catalogue title when every provider misses', function () {
    $resolution = seriesManager()->resolve(Isbn::fromString('9784088725093'), 'ja', knownTitle: 'ONE PIECE 1');

    expect($resolution->best()->source)->toBe('title')
        ->and($resolution->best()->series->name)->toBe('ONE PIECE')
        ->and((string) $resolution->best()->volume)->toBe('1')
        ->and($resolution->best()->confidence)->toBe(Confidence::Low)
        ->and($resolution->isConfident())->toBeFalse();
});

it('returns an empty resolution when nothing is found', function () {
    expect(seriesManager()->resolve(Isbn::fromString('9784088725093'), 'ja')->isEmpty())->toBeTrue();
});

it('lists volumes through the provider that owns the series', function () {
    $series = seriesManager()->resolve(Isbn::fromString('9784592135746'), 'ja')->best()->series;

    expect(seriesManager()->volumes($series))->toHaveCount(46); // 43 + 3 special editions
});

it('costs one volumes() call for a batch of scans of the same series', function () {
    $manager = seriesManager();

    foreach (['9784592135746', '9784592135753', '9784592135746'] as $isbn) {
        $manager->volumes($manager->resolve(Isbn::fromString($isbn), 'ja')->best()->series);
    }

    // Two distinct ISBNs resolved (the repeat is cached) + one volume listing.
    Http::assertSentCount(3);
});

it('does not cache a failed lookup', function () {
    withoutSeriesFixtures();
    Http::fakeSequence('ndlsearch.ndl.go.jp/*')
        ->push('Service Unavailable', 503)
        ->push(seriesFixture('ndl-isbn-9784592135746.xml'));

    expect(seriesManager()->resolve(Isbn::fromString('9784592135746'), 'ja')->isEmpty())->toBeTrue()
        ->and(seriesManager()->resolve(Isbn::fromString('9784592135746'), 'ja')->best()->source)->toBe('ndl');
});
