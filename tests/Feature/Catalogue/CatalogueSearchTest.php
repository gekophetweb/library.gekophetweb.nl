<?php

use App\Enums\ApiKeyProvider;
use App\Enums\OwnershipStatus;
use App\Models\ApiCallLog;
use App\Models\Category;
use App\Models\CollectionObject;
use App\Models\Language;
use App\Models\User;
use App\Models\UserApiKey;
use App\Services\Catalogue\CatalogueSearchManager;
use App\Support\Catalogue\CatalogueContext;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

/*
 * NDL and Open Library responses were recorded 2026-11-12 (title=ベルセルク, q=kafka on the
 * shore); the Rakuten and Google Books search answers are built inline from the documented
 * shapes, like the lookup fixtures (see tests/Fixtures/catalogue/README.md).
 */

beforeEach(function () {
    $this->seed(CatalogueSeeder::class);
    $this->user = User::factory()->create();
    $this->books = Category::query()->where('slug', 'books')->sole();
    $this->manga = Category::query()->where('slug', 'manga')->sole();
    $this->ja = Language::query()->where('code', 'ja')->sole();
    $this->en = Language::query()->where('code', 'en')->sole();

    Http::fake(function (Request $request) {
        $url = $request->url();
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return match (true) {
            str_contains($url, 'ndlsearch.ndl.go.jp') && isset($query['title']) => Http::response(file_get_contents(base_path('tests/Fixtures/catalogue/ndl-search-berserk.xml'))),
            str_contains($url, 'ndlsearch.ndl.go.jp') => Http::response(emptyNdlResponse()),
            str_contains($url, 'openlibrary.org/search.json') => Http::response(file_get_contents(base_path('tests/Fixtures/catalogue/openlibrary-search-kafka.json'))),
            // Rakuten knows one book NDL also has (same ISBN), plus one of its own.
            str_contains($url, 'rakuten') && isset($query['title']) => Http::response(['count' => 2, 'Items' => [
                ['title' => '異世界ギルドの英雄師弟 1', 'author' => 'あさの ハジメ', 'publisherName' => '講談社', 'isbn' => '9784063815597', 'salesDate' => '2023年06月', 'largeImageUrl' => 'https://thumbnail.image.rakuten.co.jp/a.jpg'],
                ['title' => 'ベルセルク 1', 'author' => '三浦 建太郎', 'publisherName' => '白泉社', 'isbn' => '9784592135746', 'salesDate' => '1990年12月'],
            ]]),
            str_contains($url, 'rakuten') => Http::response(['count' => 0, 'Items' => []]),
            str_contains($url, 'googleapis.com/books') => Http::response(file_get_contents(base_path('tests/Fixtures/catalogue/googlebooks-isbn-9780593820247.json'))),
            default => Http::response('Unexpected request: '.$url, 500),
        };
    });
});

function withRakutenKey(): void
{
    UserApiKey::factory()->for(test()->user)->create(['provider' => ApiKeyProvider::Rakuten, 'key' => 'app-123']);
}

function catalogueSearch(string $q, ?Language $language = null, array $extra = [])
{
    return test()->actingAs(test()->user)->get(route('search.index', $extra + [
        'q' => $q,
        'scope' => 'catalogue',
        'category_id' => test()->books->id,
        'language_id' => ($language ?? test()->ja)->id,
    ]));
}

it('asks Rakuten then NDL for Japanese and merges results with the same ISBN', function () {
    withRakutenKey();

    $results = app(CatalogueSearchManager::class)->search('ベルセルク', new CatalogueContext($this->user, $this->books, 'ja'))->results;
    $isbns = array_map(fn ($result) => $result->barcode, $results);

    // NDL's three printed books (one shared with Rakuten) + Rakuten's ベルセルク 1; CDs and DVDs dropped.
    expect($isbns)->toBe(['9784063815597', '9784592135746', '9784063815863', '9784063816167'])
        ->and($results[0]->source)->toBe('rakuten')
        ->and($results[0]->coverUrl)->toBe('https://thumbnail.image.rakuten.co.jp/a.jpg');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'rakuten') && str_contains($request->url(), 'title='));
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'ndlsearch') && str_contains($request->url(), 'creator='));
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'googleapis') || str_contains($request->url(), 'openlibrary'));
});

it('skips Rakuten without a key and reports it, still searching NDL', function () {
    $outcome = app(CatalogueSearchManager::class)->search('ベルセルク', new CatalogueContext($this->user, $this->books, 'ja'));

    expect($outcome->missingKeys)->toBe([ApiKeyProvider::Rakuten])
        ->and($outcome->results)->toHaveCount(3);
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'rakuten'));
});

it('asks Google Books then Open Library for English', function () {
    $results = app(CatalogueSearchManager::class)->search('kafka on the shore', new CatalogueContext($this->user, $this->books, 'en'))->results;

    expect($results[0]->barcode)->toBe('9780593820247')
        ->and($results[0]->source)->toBe('google_books')
        ->and(collect($results)->firstWhere('barcode', '9780099494096')?->name)->toBe('海辺のカフカ')
        ->and(collect($results)->firstWhere('barcode', '9780099494096')?->creator)->toBe('村上春樹');
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'rakuten') || str_contains($request->url(), 'ndlsearch'));
});

it('logs every provider call and caches the search', function () {
    withRakutenKey();
    $context = new CatalogueContext($this->user, $this->books, 'ja');

    app(CatalogueSearchManager::class)->search('ベルセルク', $context);
    expect(ApiCallLog::query()->where('operation', 'search')->pluck('provider')->sort()->values()->all())
        ->toBe(['ndl', 'ndl', 'rakuten', 'rakuten']);

    app(CatalogueSearchManager::class)->search('ベルセルク', $context);
    expect(ApiCallLog::query()->count())->toBe(4);
});

it('labels results the user owns or wants, ignoring other users\' items', function () {
    withRakutenKey();
    CollectionObject::factory()->for($this->user)->create(['barcode' => '9784592135746', 'category_id' => $this->books->id]);
    CollectionObject::factory()->for($this->user)->wishlisted()->create(['barcode' => '9784063815863', 'category_id' => $this->books->id]);
    CollectionObject::factory()->create(['barcode' => '9784063816167']); // someone else's

    catalogueSearch('ベルセルク')
        ->assertOk()
        ->assertSee('data-isbn="9784592135746" data-state="owned"', false)
        ->assertSee('data-isbn="9784063815863" data-state="wishlist"', false)
        ->assertSee('data-isbn="9784063816167" data-state="none"', false)
        ->assertSee('Add to wishlist');
});

it('points to settings when a key is missing', function () {
    catalogueSearch('ベルセルク')->assertSee('Rakuten')->assertSee(route('settings.edit'));
});

it('never calls a provider for a blank term', function () {
    catalogueSearch('  ')->assertOk()->assertSee('Find books');
    Http::assertNothingSent();
});

it('says when the category has no catalogue', function () {
    $this->actingAs($this->user)->get(route('search.index', ['q' => 'x', 'scope' => 'catalogue', 'category_id' => Category::query()->where('slug', 'music')->sole()->id]))
        ->assertSee('no online catalogue for Music');
    Http::assertNothingSent();
});

describe('adding to the wishlist', function () {
    function wishlistPayload(array $overrides = []): array
    {
        return $overrides + [
            'category_id' => test()->books->id,
            'sub_category_id' => test()->manga->id,
            'language_id' => test()->ja->id,
            'source' => 'ndl',
            'barcode' => '978-4-06-381559-7',
            'name' => '異世界ギルドの英雄師弟 1',
            'creator' => 'あさの ハジメ',
            'publisher' => '講談社',
            'release_date' => '2023-06',
        ];
    }

    it('creates a wishlist item once, and a later Own scan promotes it', function () {
        $this->actingAs($this->user)->post(route('wishlist.store'), wishlistPayload())->assertSessionHas('status', fn ($status) => str_contains($status, 'Added'));
        $this->post(route('wishlist.store'), wishlistPayload())->assertSessionHas('status', fn ($status) => str_contains($status, 'already on your wishlist'));

        $object = CollectionObject::sole();
        expect($object->ownership)->toBe(OwnershipStatus::Wishlist)
            ->and($object->barcode)->toBe('9784063815597')
            ->and($object->sub_category_id)->toBe($this->manga->id)
            ->and($object->bookDetail->artist->name)->toBe('あさの ハジメ')
            ->and($object->bookDetail->volume)->toBe('1')
            ->and($object->release_date->toDateString())->toBe('2023-06-01');

        $this->post(route('scan.start'), ['category_id' => $this->books->id, 'sub_category_id' => $this->manga->id, 'language_id' => $this->ja->id, 'mode' => 'single']);
        $this->post(route('scan.single.lookup'), ['barcode' => '9784063815597']);
        $this->post(route('scan.single.add'));

        expect($object->refresh()->ownership)->toBe(OwnershipStatus::Owned)
            ->and(CollectionObject::count())->toBe(1);
    });

    it('rejects an invalid ISBN, a missing title and a foreign sub-category', function () {
        $this->actingAs($this->user)->post(route('wishlist.store'), wishlistPayload([
            'barcode' => '12345',
            'name' => '',
            'sub_category_id' => Category::query()->where('slug', 'vinyl')->sole()->id,
        ]))->assertSessionHasErrors(['barcode', 'name', 'sub_category_id']);

        expect(CollectionObject::count())->toBe(0);
    });

    it('does not demote a book the user already owns', function () {
        CollectionObject::factory()->for($this->user)->create(['barcode' => '9784063815597']);

        $this->actingAs($this->user)->post(route('wishlist.store'), wishlistPayload())->assertSessionHas('status', fn ($status) => str_contains($status, 'already own'));
        expect(CollectionObject::sole()->ownership)->toBe(OwnershipStatus::Owned);
    });
});

describe('the API', function () {
    beforeEach(fn () => Sanctum::actingAs($this->user));

    it('searches the catalogues with ownership labels', function () {
        withRakutenKey();
        CollectionObject::factory()->for($this->user)->create(['barcode' => '9784592135746']);

        $response = $this->getJson('/api/v1/search?'.http_build_query(['q' => 'ベルセルク', 'scope' => 'catalogue', 'category_id' => $this->books->id, 'language_id' => $this->ja->id]))
            ->assertOk()
            ->assertJsonCount(4, 'data.catalogue')
            ->assertJsonPath('meta.language', 'ja');

        $owned = collect($response->json('data.catalogue'))->firstWhere('isbn', '9784592135746');
        expect($owned['ownership'])->toBe('owned')
            ->and(collect($response->json('data.catalogue'))->firstWhere('isbn', '9784063815597')['ownership'])->toBeNull();
    });

    it('adds a result to the wishlist', function () {
        $this->postJson('/api/v1/wishlist', wishlistPayload())->assertCreated()->assertJsonPath('data.ownership', 'wishlist');
        $this->postJson('/api/v1/wishlist', wishlistPayload())->assertOk();
        $this->postJson('/api/v1/wishlist', wishlistPayload(['barcode' => 'nope']))->assertUnprocessable();

        expect(CollectionObject::count())->toBe(1);
    });
});
