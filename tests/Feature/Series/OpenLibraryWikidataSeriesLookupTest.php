<?php

use App\Models\ApiCallLog;
use App\Services\Series\OpenLibraryWikidataSeriesLookup;
use App\Support\Isbn;
use App\Support\Series\Confidence;
use App\Support\Series\SeriesReference;
use App\Support\Series\SeriesVolumeData;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => fakeSeriesApis());

function openLibraryWikidata(): OpenLibraryWikidataSeriesLookup
{
    return app(OpenLibraryWikidataSeriesLookup::class);
}

it('resolves Dungeon Crawler Carl book 1 via its Open Library work', function () {
    $match = openLibraryWikidata()->resolve(Isbn::fromString('9780593820247'), 'en');

    expect($match->series->source)->toBe('wikidata')
        ->and($match->series->externalId)->toBe('Q131612237')
        ->and($match->series->name)->toBe('Dungeon Crawler Carl')
        ->and($match->series->nameRomanized)->toBe('Dungeon Crawler Carl')
        ->and($match->series->nameEnglish)->toBe('Dungeon Crawler Carl')
        ->and((string) $match->volume)->toBe('1')
        ->and($match->confidence)->toBe(Confidence::High);

    // ISBN → work → series, never ISBN → series.
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'query.wikidata.org')
        && str_contains($request['query'], 'wdt:P648 "OL24593432W"'));
});

it('lists the 8 Dungeon Crawler Carl books in order', function () {
    $volumes = openLibraryWikidata()->volumes(new SeriesReference('wikidata', 'Q131612237', 'Dungeon Crawler Carl'));

    expect($volumes->map(fn (SeriesVolumeData $v) => (string) $v->volume)->all())
        ->toBe(['1', '2', '3', '4', '5', '6', '7', '8'])
        ->and($volumes->first()->title)->toBe('Dungeon Crawler Carl')
        ->and($volumes->first()->releaseDate)->toBe('2020-09-21')
        ->and($volumes->last()->title)->toBe('A Parade of Horribles')
        ->and($volumes->last()->releaseDate)->toBeNull();
});

it('suggests the Open Library series name when Wikidata does not know the book', function () {
    withoutSeriesFixtures();
    Http::fake([
        'openlibrary.org/*' => Http::response(['title' => 'Some Book', 'works' => [['key' => '/works/OL1W']], 'series' => ['Some Saga #3']]),
        'query.wikidata.org/*' => Http::response(['results' => ['bindings' => []]]),
    ]);

    $match = openLibraryWikidata()->resolve(Isbn::fromString('9780593820247'), 'en');

    expect($match->series->source)->toBe('openlibrary')
        ->and($match->series->name)->toBe('Some Saga')
        ->and((string) $match->volume)->toBe('3')
        ->and($match->confidence)->toBe(Confidence::Low);

    // Work lookup, then the label fallback.
    Http::assertSentCount(3);
});

it('returns null for an ISBN Open Library does not know', function () {
    expect(openLibraryWikidata()->resolve(Isbn::fromString('9784592135746'), 'en'))->toBeNull();

    expect(ApiCallLog::query()->sole()->outcome)->toBe(ApiCallLog::OUTCOME_NOT_FOUND);
});

it('cannot list volumes of a series that is not on Wikidata', function () {
    $series = new SeriesReference('openlibrary', 'somesaga', 'Some Saga');

    expect(openLibraryWikidata()->supports($series))->toBeFalse()
        ->and(openLibraryWikidata()->volumes(new SeriesReference('wikidata', 'not-a-qid', 'x')))->toBeEmpty();

    Http::assertNothingSent();
});

it('logs both the Open Library and the Wikidata call', function () {
    openLibraryWikidata()->resolve(Isbn::fromString('9780593820247'), 'en');

    expect(ApiCallLog::query()->orderBy('id')->get(['provider', 'operation'])->toArray())->toBe([
        ['provider' => 'openlibrary', 'operation' => 'edition'],
        ['provider' => 'wikidata', 'operation' => 'book-by-work'],
    ]);
});
