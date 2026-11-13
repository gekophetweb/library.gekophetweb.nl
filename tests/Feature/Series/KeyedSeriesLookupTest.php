<?php

use App\Enums\ApiKeyProvider;
use App\Jobs\SyncSeriesVolumes;
use App\Models\Series;
use App\Models\User;
use App\Models\UserApiKey;
use App\Services\Series\GoogleBooksSeriesLookup;
use App\Services\Series\HardcoverSeriesLookup;
use App\Services\Series\SeriesLookupManager;
use App\Support\Isbn;
use App\Support\Series\Confidence;
use App\Support\Series\SeriesReference;
use App\Support\Series\SeriesVolumeData;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

// Google Books and Hardcover fixtures are hand-written (tests/Fixtures/series/README.md).
const BERSERK_DX_1 = '9781506711980';

beforeEach(fn () => fakeSeriesApis());

function userWithKeys(ApiKeyProvider ...$providers): User
{
    $user = User::factory()->create();

    foreach ($providers as $provider) {
        UserApiKey::factory()->for($user)->create(['provider' => $provider, 'key' => $provider === ApiKeyProvider::Hardcover ? 'Bearer hc-token' : 'gb-key']);
    }

    return $user;
}

describe('Google Books', function () {
    it('resolves an English manga volume through seriesInfo', function () {
        $match = app(GoogleBooksSeriesLookup::class)->withKey('gb-key')->resolve(Isbn::fromString(BERSERK_DX_1), 'en');

        expect($match->series->source)->toBe('google_books')
            ->and($match->series->externalId)->toBe('gbSeriesBerserkDx')
            ->and($match->series->name)->toBe('Berserk Deluxe')
            ->and($match->series->nameEnglish)->toBe('Berserk Deluxe')
            ->and($match->series->creator)->toBe('Kentaro Miura')
            ->and($match->series->publisher)->toBe('Dark Horse Comics')
            ->and((string) $match->volume)->toBe('1')
            ->and($match->confidence)->toBe(Confidence::High);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'key=gb-key'));
    });

    it('lists every page of volumes, merging editions and falling back to orderNumber', function () {
        $volumes = app(GoogleBooksSeriesLookup::class)->withKey('gb-key')
            ->volumes(new SeriesReference('google_books', 'gbSeriesBerserkDx', 'Berserk Deluxe'));

        expect($volumes->map(fn (SeriesVolumeData $v) => (string) $v->volume)->all())->toBe(['1', '2', '3'])
            ->and($volumes->first()->title)->toBe('Berserk Deluxe Volume 1')
            ->and($volumes->first()->releaseDate)->toBe('2019-02-26')
            ->and(array_map(fn (Isbn $isbn) => $isbn->value, $volumes->first()->isbns))->toBe([BERSERK_DX_1])
            ->and($volumes->last()->isbns[0]->value)->toBe('9781506712000');
    });

    it('answers nothing without a key and on an unknown ISBN', function () {
        expect(app(GoogleBooksSeriesLookup::class)->resolve(Isbn::fromString(BERSERK_DX_1), 'en'))->toBeNull()
            ->and(app(GoogleBooksSeriesLookup::class)->withKey('gb-key')->resolve(Isbn::fromString('9780593820247'), 'en'))->toBeNull();

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'series/get'));
    });
});

describe('Hardcover', function () {
    it('resolves through book_series and strips "Bearer " from the token', function () {
        $match = app(HardcoverSeriesLookup::class)->withKey('Bearer hc-token')->resolve(Isbn::fromString(BERSERK_DX_1), 'en');

        expect($match->series->source)->toBe('hardcover')
            ->and($match->series->externalId)->toBe('4242')
            ->and($match->series->name)->toBe('Berserk Deluxe Edition')
            ->and($match->series->creator)->toBe('Kentaro Miura')
            ->and((string) $match->volume)->toBe('1')
            ->and($match->confidence)->toBe(Confidence::High);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'api.hardcover.app')
            && $request->header('Authorization') === ['Bearer hc-token']
            && $request['variables'] === ['isbn' => BERSERK_DX_1]);
    });

    it('lists the volumes, skipping unnumbered books and invalid ISBNs', function () {
        $volumes = app(HardcoverSeriesLookup::class)->withKey('hc-token')
            ->volumes(new SeriesReference('hardcover', '4242', 'Berserk Deluxe Edition'));

        expect($volumes->map(fn (SeriesVolumeData $v) => (string) $v->volume)->all())->toBe(['1', '2', '3'])
            ->and(array_map(fn (Isbn $isbn) => $isbn->value, $volumes[1]->isbns))->toBe(['9781506711997'])
            ->and($volumes[2]->releaseDate)->toBe('2019-08-20');
    });

    it('treats GraphQL errors as a miss', function () {
        withoutSeriesFixtures();
        Http::fake(['api.hardcover.app/*' => Http::response(['errors' => [['message' => 'invalid token']]])]);

        expect(app(HardcoverSeriesLookup::class)->withKey('bad')->resolve(Isbn::fromString(BERSERK_DX_1), 'en'))->toBeNull();
    });
});

describe('the English chain', function () {
    it('skips both providers for a user without keys, and for no user at all', function () {
        $user = User::factory()->create();

        expect(app(SeriesLookupManager::class)->resolve(Isbn::fromString(BERSERK_DX_1), 'en', user: $user)->best())->toBeNull()
            ->and(app(SeriesLookupManager::class)->resolve(Isbn::fromString(BERSERK_DX_1), 'en')->best())->toBeNull();

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'googleapis.com') || str_contains($request->url(), 'hardcover.app'));
    });

    it('falls through Open Library + Wikidata to Google Books with the user\'s key', function () {
        $resolution = app(SeriesLookupManager::class)->resolve(Isbn::fromString(BERSERK_DX_1), 'en', user: userWithKeys(ApiKeyProvider::GoogleBooks));

        expect($resolution->best()->source)->toBe('google_books')
            ->and($resolution->isConfident())->toBeTrue();
        // High confidence: the chain stops before Hardcover.
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'hardcover.app'));
    });

    it('uses Hardcover when it is the only key', function () {
        $resolution = app(SeriesLookupManager::class)->resolve(Isbn::fromString(BERSERK_DX_1), 'en', user: userWithKeys(ApiKeyProvider::Hardcover));

        expect($resolution->best()->source)->toBe('hardcover');
    });

    it('does not serve a keyless cached answer to a user with a key', function () {
        $manager = app(SeriesLookupManager::class);
        $manager->resolve(Isbn::fromString(BERSERK_DX_1), 'en', user: User::factory()->create());

        expect($manager->resolve(Isbn::fromString(BERSERK_DX_1), 'en', user: userWithKeys(ApiKeyProvider::GoogleBooks))->best()?->source)
            ->toBe('google_books');
    });

    it('syncs a Hardcover series with its owner\'s token, and does nothing without one', function () {
        $owner = userWithKeys(ApiKeyProvider::Hardcover);
        $series = Series::factory()->for($owner)->create(['external_source' => 'hardcover', 'external_id' => '4242']);
        expect($series->isSyncable())->toBeTrue();

        (new SyncSeriesVolumes($series))->handle(app(SeriesLookupManager::class));
        expect($series->volumes()->pluck('volume_number')->all())->toBe(['1', '2', '3']);

        $keyless = Series::factory()->create(['external_source' => 'hardcover', 'external_id' => '4242']);
        (new SyncSeriesVolumes($keyless))->handle(app(SeriesLookupManager::class));
        expect($keyless->volumes()->count())->toBe(0)->and($keyless->fresh()->synced_at)->toBeNull();
    });
});
