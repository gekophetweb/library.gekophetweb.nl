<?php

use App\Actions\Scan\AddScannedObject;
use App\Enums\ApiKeyProvider;
use App\Enums\OwnershipStatus;
use App\Models\Category;
use App\Models\CollectionObject;
use App\Models\Language;
use App\Models\ScanBatch;
use App\Models\Series;
use App\Models\SeriesVolume;
use App\Models\User;
use App\Models\UserApiKey;
use App\Support\Catalogue\CatalogueResult;
use App\Support\Scan\ScanSetup;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(CatalogueSeeder::class);
    $this->user = User::factory()->create();
    UserApiKey::factory()->for($this->user)->create(['provider' => ApiKeyProvider::Rakuten, 'key' => 'app-123']);
    $this->books = Category::query()->where('slug', 'books')->sole();
    $this->manga = Category::query()->where('slug', 'manga')->sole();
    $this->ja = Language::query()->where('code', 'ja')->sole();

    Http::fake(function (Request $request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return match (true) {
            str_contains($request->url(), 'rakuten') && ($query['isbn'] ?? '') === '9784592135746' => Http::response(file_get_contents(base_path('tests/Fixtures/catalogue/rakuten-isbn-9784592135746.json'))),
            str_contains($request->url(), 'rakuten') => Http::response(['count' => 0, 'Items' => []]),
            default => Http::response(['kind' => 'books#volumes', 'totalItems' => 0]),
        };
    });
});

function wish(array $attributes = []): CollectionObject
{
    return CollectionObject::factory()->for(test()->user)->wishlisted()->create($attributes + [
        'category_id' => test()->books->id,
        'sub_category_id' => test()->manga->id,
    ]);
}

function setupAs(OwnershipStatus $ownership): ScanSetup
{
    return new ScanSetup(test()->books, test()->manga, test()->ja, $ownership);
}

it('labels the statuses and scopes owned and wanted items', function () {
    $owned = CollectionObject::factory()->for($this->user)->create();
    $wanted = wish();

    expect(OwnershipStatus::Owned->label())->toBe('Owned')
        ->and(OwnershipStatus::Wishlist->label())->toBe('Wishlist')
        ->and(CollectionObject::query()->owned()->pluck('id')->all())->toBe([$owned->id])
        ->and(CollectionObject::query()->wishlisted()->pluck('id')->all())->toBe([$wanted->id])
        ->and($owned->ownership)->toBe(OwnershipStatus::Owned);
});

describe('adding a scanned item', function () {
    it('creates it as owned or as wishlist', function () {
        $add = app(AddScannedObject::class);

        $owned = $add->handle($this->user, setupAs(OwnershipStatus::Owned), new CatalogueResult('manual', '111', 'A', 'A'));
        $wanted = $add->handle($this->user, setupAs(OwnershipStatus::Wishlist), new CatalogueResult('manual', '222', 'B', 'B'));

        expect($owned->ownership)->toBe(OwnershipStatus::Owned)
            ->and($wanted->ownership)->toBe(OwnershipStatus::Wishlist);
    });

    it('promotes a wanted item scanned in Own mode without duplicating it', function () {
        $wanted = wish(['barcode' => '111']);

        $result = app(AddScannedObject::class)->handle($this->user, setupAs(OwnershipStatus::Owned), new CatalogueResult('manual', '111', 'A', 'A'));

        expect($result->is($wanted))->toBeTrue()
            ->and($wanted->refresh()->ownership)->toBe(OwnershipStatus::Owned)
            ->and(CollectionObject::count())->toBe(1);
    });

    it('never demotes an owned item scanned in Wishlist mode', function () {
        $owned = CollectionObject::factory()->for($this->user)->create(['barcode' => '111']);

        app(AddScannedObject::class)->handle($this->user, setupAs(OwnershipStatus::Wishlist), new CatalogueResult('manual', '111', 'A', 'A'));

        expect($owned->refresh()->ownership)->toBe(OwnershipStatus::Owned)
            ->and(CollectionObject::count())->toBe(1);
    });
});

function startScanAs(string $mode, ?string $ownership)
{
    return test()->post(route('scan.start'), array_filter([
        'category_id' => test()->books->id,
        'sub_category_id' => test()->manga->id,
        'language_id' => test()->ja->id,
        'mode' => $mode,
        'ownership' => $ownership,
    ]));
}

describe('scanning', function () {
    beforeEach(fn () => $this->actingAs($this->user));

    it('defaults an omitted ownership to owned and rejects an invalid one', function () {
        startScanAs('single', null)->assertSessionHasNoErrors();
        expect(session('scan.setup.ownership'))->toBe('owned');

        startScanAs('single', 'borrowed')->assertSessionHasErrors('ownership');
    });

    it('adds a single scan to the wishlist', function () {
        startScanAs('single', 'wishlist');
        $this->post(route('scan.single.lookup'), ['barcode' => '9784592135746']);
        $this->get(route('scan.single'))->assertSee('Adding to your wishlist')->assertSee('Add to wishlist');

        $this->post(route('scan.single.add'))->assertSessionHas('status', fn ($status) => str_contains($status, 'wishlist'));
        expect(CollectionObject::sole()->ownership)->toBe(OwnershipStatus::Wishlist);
    });

    it('offers to mark a wanted item as owned when scanned in Own mode', function () {
        $wanted = wish(['barcode' => '9784592135746']);
        startScanAs('single', 'owned');

        $this->post(route('scan.single.lookup'), ['barcode' => '9784592135746']);
        $this->get(route('scan.single'))->assertSee('on your wishlist')->assertSee('Mark as owned');
        Http::assertNothingSent();

        $this->post(route('scan.single.add'))->assertRedirect(route('objects.show', $wanted));
        expect($wanted->refresh()->ownership)->toBe(OwnershipStatus::Owned)
            ->and(CollectionObject::count())->toBe(1);
    });

    it('says a wanted item is already on the wishlist in Wishlist mode', function () {
        wish(['barcode' => '9784592135746']);
        startScanAs('single', 'wishlist');

        $this->post(route('scan.single.lookup'), ['barcode' => '9784592135746']);
        $this->get(route('scan.single'))->assertSee('already on your wishlist')->assertDontSee('Mark as owned');
        $this->post(route('scan.single.add'))->assertStatus(409);
    });

    it('persists the batch ownership and applies it on commit, promoting wanted items', function () {
        $wanted = wish(['barcode' => '9784088725093']);
        startScanAs('batch', 'owned');
        $batch = ScanBatch::sole();
        expect($batch->ownership)->toBe(OwnershipStatus::Owned);

        $this->post(route('scan.batches.items.store', $batch), ['barcode' => '9784592135746'])->assertSessionHasNoErrors();
        $this->post(route('scan.batches.items.store', $batch), ['barcode' => '9784088725093'])->assertSessionHasNoErrors();
        $this->post(route('scan.batches.commit', $batch));

        expect($batch->items()->pluck('status', 'barcode')->all())->toBe(['9784592135746' => 'added', '9784088725093' => 'promoted'])
            ->and($wanted->refresh()->ownership)->toBe(OwnershipStatus::Owned)
            ->and(CollectionObject::query()->owned()->count())->toBe(2);
    });

    it('adds a wishlist batch as wanted items and refuses items already wanted', function () {
        wish(['barcode' => '9784088725093']);
        startScanAs('batch', 'wishlist');
        $batch = ScanBatch::sole();

        $this->post(route('scan.batches.items.store', $batch), ['barcode' => '9784088725093'])->assertSessionHasErrors('barcode');
        $this->post(route('scan.batches.items.store', $batch), ['barcode' => '9784592135746']);
        $this->get(route('scan.batches.show', $batch))->assertSee('Adding to your wishlist')->assertSee('to my wishlist');
        $this->post(route('scan.batches.commit', $batch));

        expect(CollectionObject::query()->where('barcode', '9784592135746')->sole()->ownership)->toBe(OwnershipStatus::Wishlist);
    });
});

describe('the item page', function () {
    beforeEach(fn () => $this->actingAs($this->user));

    it('toggles the status in both directions', function () {
        $object = wish();

        $this->get(route('objects.show', $object))->assertSee('Mark as owned')->assertSee('data-ownership="wishlist"', false);
        $this->patch(route('objects.ownership', $object), ['ownership' => 'owned'])->assertRedirect(route('objects.show', $object));
        expect($object->refresh()->ownership)->toBe(OwnershipStatus::Owned);

        $this->get(route('objects.show', $object))->assertSee('Move to wishlist');
        $this->patch(route('objects.ownership', $object), ['ownership' => 'wishlist']);
        expect($object->refresh()->ownership)->toBe(OwnershipStatus::Wishlist);
    });

    it('rejects an invalid status and another user\'s item', function () {
        $object = wish();

        $this->patch(route('objects.ownership', $object), ['ownership' => 'lent'])->assertSessionHasErrors('ownership');
        $this->patch(route('objects.ownership', CollectionObject::factory()->create()), ['ownership' => 'owned'])->assertNotFound();
    });

    it('keeps the status when the edit form leaves it out, and saves it when given', function () {
        $object = wish();
        $base = ['name' => 'x', 'name_romanized' => 'x', 'languages' => [$this->ja->id]];

        $this->put(route('objects.update', $object), $base)->assertSessionHasNoErrors();
        expect($object->refresh()->ownership)->toBe(OwnershipStatus::Wishlist);

        $this->put(route('objects.update', $object), $base + ['ownership' => 'owned'])->assertSessionHasNoErrors();
        expect($object->refresh()->ownership)->toBe(OwnershipStatus::Owned);

        $this->put(route('objects.update', $object), $base + ['ownership' => 'nope'])->assertSessionHasErrors('ownership');
    });
});

describe('library pages', function () {
    beforeEach(fn () => $this->actingAs($this->user));

    it('counts owned items only on the dashboard and shows wanted ones separately', function () {
        CollectionObject::factory()->for($this->user)->count(2)->create(['category_id' => $this->books->id]);
        wish();

        $this->get(route('home'))
            ->assertSee('data-owned="2"', false)
            ->assertSee('data-wanted="1"', false)
            ->assertSee('1 wanted');
    });

    it('lists wanted items in their own section of the category page', function () {
        CollectionObject::factory()->for($this->user)->create(['category_id' => $this->books->id, 'name' => 'Owned book']);
        wish(['name' => 'Wanted book']);

        $this->get(route('library.category', $this->books))
            ->assertSeeInOrder(['Owned book', 'Wishlist', 'Wanted book']);
    });

    it('gives series owned and wanted counts', function () {
        $series = Series::factory()->for($this->user)->create(['name' => 'ベルセルク']);
        CollectionObject::factory()->for($this->user)->create(['category_id' => $this->books->id, 'serie_id' => $series->id]);
        wish(['serie_id' => $series->id]);

        $this->get(route('library.category', $this->books))->assertSee('1 owned')->assertSee('1 wanted');
    });

    it('shows a wishlisted volume as wanted, not owned', function () {
        $series = Series::factory()->for($this->user)->create();
        SeriesVolume::factory()->for($series)->create(['volume_number' => '1', 'volume_sort' => 1, 'isbns' => ['9784592135746']]);
        SeriesVolume::factory()->for($series)->create(['volume_number' => '2', 'volume_sort' => 2, 'isbns' => ['9784592135753']]);
        SeriesVolume::factory()->for($series)->create(['volume_number' => '3', 'volume_sort' => 3, 'isbns' => ['9784592135760']]);
        CollectionObject::factory()->for($this->user)->create(['category_id' => $this->books->id, 'serie_id' => $series->id, 'barcode' => '9784592135746']);
        wish(['serie_id' => $series->id, 'barcode' => '9784592135753']);

        $this->get(route('library.series', $series))
            ->assertSee('You own 1 of 3 volumes')
            ->assertSee('data-volume="1" data-state="owned"', false)
            ->assertSee('data-volume="2" data-state="wanted"', false)
            ->assertSee('data-volume="3" data-state="missing"', false);
    });

    it('counts only owned items in the admin user list', function () {
        $this->user->forceFill(['is_admin' => true])->save();
        CollectionObject::factory()->for($this->user)->create();
        wish();

        $this->get(route('admin.users.index'))->assertSee('data-total="1"', false);
    });
});

describe('the API', function () {
    beforeEach(fn () => Sanctum::actingAs($this->user));

    it('exposes and filters by ownership', function () {
        $owned = CollectionObject::factory()->for($this->user)->create();
        $wanted = wish();

        $this->getJson('/api/v1/objects')->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/objects?ownership=wishlist')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $wanted->id)->assertJsonPath('data.0.ownership', 'wishlist');
        $this->getJson('/api/v1/objects?ownership=owned')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $owned->id);
        $this->getJson('/api/v1/objects?ownership=lent')->assertUnprocessable();
    });

    it('updates ownership through PATCH', function () {
        $wanted = wish();

        $this->patchJson("/api/v1/objects/{$wanted->id}", ['name' => 'x', 'name_romanized' => 'x', 'languages' => [$this->ja->id], 'ownership' => 'owned'])
            ->assertOk()
            ->assertJsonPath('data.ownership', 'owned');
    });
});
