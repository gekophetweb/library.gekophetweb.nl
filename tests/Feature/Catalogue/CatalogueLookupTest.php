<?php

use App\Enums\ApiKeyProvider;
use App\Models\ApiCallLog;
use App\Models\Category;
use App\Models\User;
use App\Models\UserApiKey;
use App\Services\Catalogue\CatalogueLookupManager;
use App\Support\Catalogue\CatalogueContext;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function catalogueFixture(string $name): string
{
    return file_get_contents(base_path('tests/Fixtures/catalogue/'.$name));
}

/**
 * Rakuten answers the Berserk fixture, Google Books the DCC fixture, NDL and Open Library
 * their recorded Berserk 1 answers; anything else is empty. $ndlDown makes NDL time out.
 * Each call replaces the previous fake: Http::fake() stacks, and the first one would answer.
 */
function fakeCatalogueApis(bool $googleQuotaExceeded = false, bool $ndlDown = false, bool $openLibraryDown = false): void
{
    withoutSeriesFixtures(); // starts from a clean HTTP client

    Http::fake(function (Request $request) use ($googleQuotaExceeded, $ndlDown, $openLibraryDown) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        if (str_contains($request->url(), 'ndlsearch.ndl.go.jp')) {
            if ($ndlDown) {
                throw new ConnectionException('cURL error 28: Operation timed out');
            }

            // Like the real NDL: the record for 9784592135746 (listed by its ISBN-10).
            return Http::response(($query['isbn'] ?? '') !== ''
                ? catalogueFixture('ndl-isbn-9784592135746.xml')
                : '<rss><channel></channel></rss>');
        }

        if (str_contains($request->url(), 'openlibrary.org')) {
            if ($openLibraryDown) {
                return Http::response('Service unavailable', 503);
            }

            return Http::response(($query['isbn'] ?? '') === '9784592135746'
                ? catalogueFixture('openlibrary-isbn-9784592135746.json')
                : ['numFound' => 0, 'docs' => []]);
        }

        if (str_contains($request->url(), 'openapi.rakuten.co.jp')) {
            return Http::response(($query['isbn'] ?? '') === '9784592135746'
                ? catalogueFixture('rakuten-isbn-9784592135746.json')
                : ['count' => 0, 'Items' => []]);
        }

        if (str_contains($request->url(), 'googleapis.com/books')) {
            if ($googleQuotaExceeded) {
                return Http::response(catalogueFixture('googlebooks-429.json'), 429);
            }

            return Http::response(($query['q'] ?? '') === 'isbn:9780593820247'
                ? catalogueFixture('googlebooks-isbn-9780593820247.json')
                : ['kind' => 'books#volumes', 'totalItems' => 0]);
        }

        return Http::response('Unexpected request: '.$request->url(), 500);
    });
}

beforeEach(function () {
    $this->seed(CatalogueSeeder::class);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function bookContext(string $language): CatalogueContext
{
    return new CatalogueContext(test()->user, Category::query()->where('slug', 'books')->sole(), $language);
}

it('looks a Japanese book up at Rakuten with the user\'s key', function () {
    UserApiKey::factory()->for($this->user)->create(['provider' => ApiKeyProvider::Rakuten, 'key' => 'app-123', 'secret' => 'pk_access']);
    fakeCatalogueApis();

    $outcome = app(CatalogueLookupManager::class)->lookup('978-4-592-13574-6', bookContext('ja'));

    expect($outcome->result->source)->toBe('rakuten')
        ->and($outcome->result->barcode)->toBe('9784592135746')
        ->and($outcome->result->name)->toBe('ベルセルク 1')
        ->and($outcome->result->creator)->toBe('三浦 建太郎')
        ->and($outcome->result->publisher)->toBe('白泉社')
        ->and($outcome->result->releaseDate)->toBe('1990-12')
        ->and($outcome->result->coverUrl)->toContain('large.jpg');

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://openapi.rakuten.co.jp/')
        && $request['applicationId'] === 'app-123' && $request['accessKey'] === 'pk_access');
});

it('skips Rakuten without a key, says so, and still finds a Japanese book at NDL', function () {
    fakeCatalogueApis(googleQuotaExceeded: true);

    $outcome = app(CatalogueLookupManager::class)->lookup('9784592135746', bookContext('ja'));

    // The bug this guards: keyless, the only provider left was Google Books (quota 0).
    expect($outcome->result->source)->toBe('ndl')
        ->and($outcome->result->barcode)->toBe('9784592135746')
        ->and($outcome->result->name)->toBe('ベルセルク 1')
        ->and($outcome->result->publisher)->toBe('白泉社')
        ->and($outcome->missingKeys)->toBe([ApiKeyProvider::Rakuten])
        ->and($outcome->failed())->toBeFalse();
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'rakuten'));
});

it('only takes the NDL record with exactly the scanned ISBN', function () {
    fakeCatalogueApis(googleQuotaExceeded: true);

    // NDL answers the 9784592135746 record whatever ISBN is asked for (see the fake).
    $outcome = app(CatalogueLookupManager::class)->lookup('9784592135753', bookContext('ja'));

    expect($outcome->result)->toBeNull()
        ->and($outcome->failed())->toBeFalse(); // NDL answered: really not found
});

it('reports a lookup as failed, not as not found, when no catalogue answers', function () {
    fakeCatalogueApis(googleQuotaExceeded: true, ndlDown: true);

    $outcome = app(CatalogueLookupManager::class)->lookup('9784592135746', bookContext('ja'));

    expect($outcome->result)->toBeNull()
        ->and($outcome->failed())->toBeTrue()
        ->and($outcome->unavailable)->toBe(['ndl', 'google_books'])
        ->and($outcome->unavailableNames())->toBe('NDL and Google Books');
});

it('finds a book at Open Library without keys when Google Books is over its quota', function () {
    fakeCatalogueApis(googleQuotaExceeded: true);

    $outcome = app(CatalogueLookupManager::class)->lookup('9784592135746', bookContext('en'));

    expect($outcome->result->source)->toBe('openlibrary')
        ->and($outcome->result->name)->toBe('ベルセルク 1')
        ->and($outcome->result->creator)->toBe('三浦建太郎')
        // The work's publisher list spans editions, so a lookup leaves it empty.
        ->and($outcome->result->publisher)->toBeNull();

    fakeCatalogueApis(googleQuotaExceeded: true, openLibraryDown: true);
    expect(app(CatalogueLookupManager::class)->lookup('9784592135746', bookContext('en'))->failed())->toBeTrue();
});

it('looks an English book up at Google Books, keyless or with the user\'s key', function () {
    fakeCatalogueApis();

    $outcome = app(CatalogueLookupManager::class)->lookup('9780593820247', bookContext('en'));

    expect($outcome->result->source)->toBe('google_books')
        ->and($outcome->result->name)->toBe('Dungeon Crawler Carl')
        ->and($outcome->result->creator)->toBe('Matt Dinniman')
        ->and($outcome->result->releaseDate)->toBe('2024-08-13')
        ->and($outcome->result->languageCode)->toBe('en');
    Http::assertSent(fn (Request $request) => ! str_contains($request->url(), 'key='));

    UserApiKey::factory()->for($this->user)->create(['provider' => ApiKeyProvider::GoogleBooks, 'key' => 'g-key']);
    app(CatalogueLookupManager::class)->lookup('9780593820247', bookContext('en'));
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'key=g-key'));
});

it('falls through when Google Books is over its quota', function () {
    UserApiKey::factory()->for($this->user)->create(['provider' => ApiKeyProvider::Rakuten, 'key' => 'app-123']);
    fakeCatalogueApis(googleQuotaExceeded: true);

    $outcome = app(CatalogueLookupManager::class)->lookup('9784592135746', bookContext('en'));

    // Next in line is keyless Open Library (config/catalogue.php); Rakuten, asked last,
    // fills the gap it leaves.
    expect($outcome->result->source)->toBe('openlibrary')
        ->and($outcome->result->publisher)->toBe('白泉社');
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'rakuten'));
    expect(ApiCallLog::query()->where('provider', 'google_books')->sole()->outcome)->toBe(ApiCallLog::OUTCOME_FAILED);
});

it('logs every provider call without the key', function () {
    UserApiKey::factory()->for($this->user)->create(['provider' => ApiKeyProvider::Rakuten, 'key' => 'secret-app-id', 'secret' => 'pk_secret-access']);
    fakeCatalogueApis();

    app(CatalogueLookupManager::class)->lookup('9784592135746', bookContext('ja'));

    $logs = ApiCallLog::query()->orderBy('id')->get();
    expect($logs->pluck('provider')->all())->toBe(['rakuten', 'ndl', 'google_books'])
        ->and($logs->pluck('query')->join(' '))->not->toContain('secret-app-id')->not->toContain('pk_secret-access')
        ->and($logs->first()->user_id)->toBe($this->user->id);
});

it('makes no call for a book barcode that is not an ISBN', function () {
    fakeCatalogueApis();

    $outcome = app(CatalogueLookupManager::class)->lookup('4901234567894', bookContext('ja'));

    expect($outcome->result)->toBeNull()->and($outcome->invalidBarcode)->toBeTrue();
    Http::assertNothingSent();
});

it('reports that Music and Games have no provider yet', function () {
    fakeCatalogueApis();
    $music = new CatalogueContext($this->user, Category::query()->where('slug', 'music')->sole(), 'ja');

    $outcome = app(CatalogueLookupManager::class)->lookup('4988002123456', $music);

    expect($outcome->result)->toBeNull()->and($outcome->noProvider)->toBeTrue();
    Http::assertNothingSent();
});
