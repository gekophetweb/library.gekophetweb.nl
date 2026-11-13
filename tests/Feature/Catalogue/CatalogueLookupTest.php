<?php

use App\Enums\ApiKeyProvider;
use App\Models\ApiCallLog;
use App\Models\Category;
use App\Models\User;
use App\Models\UserApiKey;
use App\Services\Catalogue\CatalogueLookupManager;
use App\Support\Catalogue\CatalogueContext;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function catalogueFixture(string $name): string
{
    return file_get_contents(base_path('tests/Fixtures/catalogue/'.$name));
}

/** Rakuten answers the Berserk fixture, Google Books the DCC fixture; anything else is empty. */
function fakeCatalogueApis(bool $googleQuotaExceeded = false): void
{
    Http::fake(function (Request $request) use ($googleQuotaExceeded) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        if (str_contains($request->url(), 'app.rakuten.co.jp')) {
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
    UserApiKey::factory()->for($this->user)->create(['provider' => ApiKeyProvider::Rakuten, 'key' => 'app-123']);
    fakeCatalogueApis();

    $outcome = app(CatalogueLookupManager::class)->lookup('978-4-592-13574-6', bookContext('ja'));

    expect($outcome->result->source)->toBe('rakuten')
        ->and($outcome->result->barcode)->toBe('9784592135746')
        ->and($outcome->result->name)->toBe('ベルセルク 1')
        ->and($outcome->result->creator)->toBe('三浦 建太郎')
        ->and($outcome->result->publisher)->toBe('白泉社')
        ->and($outcome->result->releaseDate)->toBe('1990-12')
        ->and($outcome->result->coverUrl)->toContain('large.jpg');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'applicationId=app-123'));
});

it('skips Rakuten without a key and says so', function () {
    fakeCatalogueApis();

    $outcome = app(CatalogueLookupManager::class)->lookup('9784592135746', bookContext('ja'));

    expect($outcome->result)->toBeNull()
        ->and($outcome->missingKeys)->toBe([ApiKeyProvider::Rakuten]);
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'rakuten'));
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

    expect($outcome->result->source)->toBe('rakuten');
    expect(ApiCallLog::query()->where('provider', 'google_books')->sole()->outcome)->toBe(ApiCallLog::OUTCOME_FAILED);
});

it('logs every provider call without the key', function () {
    UserApiKey::factory()->for($this->user)->create(['provider' => ApiKeyProvider::Rakuten, 'key' => 'secret-app-id']);
    fakeCatalogueApis();

    app(CatalogueLookupManager::class)->lookup('9784592135746', bookContext('ja'));

    $logs = ApiCallLog::query()->orderBy('id')->get();
    expect($logs->pluck('provider')->all())->toBe(['rakuten', 'google_books'])
        ->and($logs->pluck('query')->join(' '))->not->toContain('secret-app-id')
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
