<?php

use App\Models\ApiCallLog;
use App\Models\Category;
use App\Models\CollectionObject;
use App\Models\Language;
use App\Models\ScanBatch;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
 * Redoing the lookup of a code that was already scanned, by its owner: a code whose
 * catalogues did not answer is looked up again, and a book that was found without a
 * series gets another series detection. The raw API answers stay admin-only.
 * Catalogues answer from tests/Fixtures/catalogue, series APIs from tests/Fixtures/series.
 */

beforeEach(function () {
    $this->seed(CatalogueSeeder::class);
    $this->user = User::factory()->withApiKeys()->create();
    $this->books = Category::query()->where('slug', 'books')->sole();
    $this->manga = Category::query()->where('slug', 'manga')->sole();
    $this->ja = Language::query()->where('code', 'ja')->sole();
    $this->down = false;     // every external API refuses while true
    $this->seriesDown = false; // only the series APIs refuse while true

    Http::fake(function (Request $request) {
        $url = $request->url();
        $catalogue = str_contains($url, 'rakuten') || str_contains($url, 'googleapis');

        if ($this->down || ($this->seriesDown && ! $catalogue)) {
            return Http::response('Service Unavailable', 503);
        }

        if (! $catalogue) {
            return null; // the series fakes below answer
        }

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return ($query['isbn'] ?? null) === '9784592135746'
            ? Http::response(file_get_contents(base_path('tests/Fixtures/catalogue/rakuten-isbn-9784592135746.json')))
            : Http::response(['count' => 0, 'Items' => [], 'totalItems' => 0]);
    });
    fakeSeriesApis();

    $this->actingAs($this->user);
});

/** A committed (so already processed) batch of the acting user with $codes. */
function redoTestBatch(array $codes): ScanBatch
{
    test()->post(route('scan.start'), ['category_id' => test()->books->id, 'sub_category_id' => test()->manga->id, 'language_id' => test()->ja->id, 'mode' => 'batch']);
    $batch = ScanBatch::query()->latest('id')->first();

    foreach ($codes as $code) {
        test()->post(route('scan.batches.items.store', $batch), ['barcode' => $code]);
    }

    test()->post(route('scan.batches.commit', $batch)); // the sync queue runs the job

    return $batch->refresh();
}

describe('batch scan', function () {
    it('looks a code up again once its catalogues answer, and links the series it finds', function () {
        $this->down = true;
        $batch = redoTestBatch(['9784592135746']);
        $item = $batch->items()->sole();
        expect($item->status)->toBe('failed');

        $this->get(route('scan.batches.show', $batch))
            ->assertSee('data-redo-code="'.$item->id.'"', false)
            ->assertSee('btn btn-secondary', false);

        $this->down = false;
        $this->post(route('scan.batches.items.redo', [$batch, $item]))
            ->assertRedirect(route('scan.batches.show', $batch))
            ->assertSessionHas('status', 'Looking 9784592135746 up again…');

        $object = CollectionObject::sole();
        expect($item->refresh()->status)->toBe('added')
            ->and($item->object_id)->toBe($object->id)
            ->and($object->name)->toBe('ベルセルク 1')
            ->and($object->serie->name)->toBe('ベルセルク')
            ->and($batch->refresh()->status)->toBe(ScanBatch::DONE);

        // Found and in a series: nothing left to redo.
        $this->get(route('scan.batches.show', $batch))->assertDontSee('data-redo-code="'.$item->id.'"', false);
        $this->post(route('scan.batches.items.redo', [$batch, $item]))->assertStatus(409);
    });

    it('retries the series of a book that was found while the series APIs were down', function () {
        $this->seriesDown = true;
        $batch = redoTestBatch(['9784592135746']);
        $item = $batch->items()->sole();
        $object = CollectionObject::sole();
        expect($item->status)->toBe('added')->and($object->serie_id)->toBeNull();

        $this->get(route('scan.batches.show', $batch))->assertSee('data-redo-code="'.$item->id.'"', false);

        $this->seriesDown = false;
        $linkedCalls = $item->apiCalls()->count();
        $this->post(route('scan.batches.items.redo', [$batch, $item]))
            ->assertSessionHas('status', 'Looking for the series of ベルセルク 1 again…');

        expect($object->refresh()->serie->name)->toBe('ベルセルク')
            ->and($item->refresh()->status)->toBe('added')
            ->and(CollectionObject::count())->toBe(1)
            // The series calls of the retry are linked to the code, for the admin's raw view.
            ->and($item->apiCalls()->count())->toBeGreaterThan($linkedCalls);
    });

    it('lets only the owner redo their codes, and only codes of a finished batch', function () {
        $this->down = true;
        $batch = redoTestBatch(['9784592135746']);
        $item = $batch->items()->sole();
        $this->down = false;

        $stranger = User::factory()->withApiKeys()->create();
        $this->actingAs($stranger)->post(route('scan.batches.items.redo', [$batch, $item]))->assertNotFound();

        // An admin uses the admin pages for someone else's batch, not the owner's route.
        $this->actingAs(User::factory()->admin()->create())->post(route('scan.batches.items.redo', [$batch, $item]))->assertNotFound();

        // A code of another batch can't be redone through this one.
        $this->actingAs($stranger);
        $this->down = true;
        $theirs = redoTestBatch(['9784088725093']);
        $this->down = false;
        $this->actingAs($this->user)->post(route('scan.batches.items.redo', [$batch, $theirs->items()->sole()]))->assertNotFound();

        expect($item->refresh()->status)->toBe('failed');

        $batch->update(['status' => ScanBatch::PROCESSING]);
        $this->post(route('scan.batches.items.redo', [$batch, $item]))->assertStatus(409);
        expect($item->refresh()->status)->toBe('failed');
    });

    it('keeps the raw API answers for admins', function () {
        $batch = redoTestBatch(['9784088725093']);
        $item = $batch->items()->sole();
        expect($item->apiCalls()->exists())->toBeTrue();

        $this->get(route('scan.batches.show', $batch))
            ->assertSee('data-redo-code="'.$item->id.'"', false)
            ->assertDontSee(route('admin.scan-batches.items.show', [$batch, $item]));
        $this->get(route('admin.scan-batches.items.show', [$batch, $item]))->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.scan-batches.items.show', [$batch, $item]))->assertOk();
    });

    it('lets an admin redo the series of found books by ticking them', function () {
        $this->seriesDown = true;
        $batch = redoTestBatch(['9784592135746']);
        $item = $batch->items()->sole();
        $this->seriesDown = false;

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.scan-batches.show', $batch))
            ->assertSee('data-redo-item="'.$item->id.'"', false);

        $this->post(route('admin.scan-batches.items.redo', $batch), ['items' => [$item->id]])
            ->assertSessionHas('status', 'Redoing 1 code…');

        expect(CollectionObject::sole()->serie->name)->toBe('ベルセルク');
    });
});

describe('single scan', function () {
    it('looks the scanned code up again, with its series', function () {
        $this->post(route('scan.start'), ['category_id' => $this->books->id, 'sub_category_id' => $this->manga->id, 'language_id' => $this->ja->id, 'mode' => 'single']);

        $this->down = true;
        $this->post(route('scan.single.lookup'), ['barcode' => '978-4-592-13574-6']);
        $this->get(route('scan.single'))
            ->assertSee('data-unavailable', false)
            ->assertSee(route('scan.single.redo'))
            ->assertSee('btn btn-secondary', false);

        $this->down = false;
        $this->post(route('scan.single.redo'))->assertRedirect(route('scan.single'));

        $this->get(route('scan.single'))
            ->assertSee('ベルセルク 1')
            ->assertSee('value="ベルセルク"', false)
            ->assertDontSee('data-unavailable', false);

        $this->post(route('scan.single.add'), ['series_name' => 'ベルセルク', 'series_volume' => '1']);
        expect(CollectionObject::sole()->serie->name)->toBe('ベルセルク');
    });

    it('needs a scanned code to redo, of the user\'s own session', function () {
        $this->post(route('scan.single.redo'))->assertStatus(409);

        $this->post(route('scan.start'), ['category_id' => $this->books->id, 'sub_category_id' => $this->manga->id, 'language_id' => $this->ja->id, 'mode' => 'single']);
        $this->post(route('scan.single.redo'))->assertStatus(409);
        Http::assertNothingSent();

        // Logged out: nothing to redo.
        auth()->logout();
        $this->post(route('scan.single.redo'))->assertRedirect(route('login'));
    });
});

it('logs every redo lookup', function () {
    $this->down = true;
    $batch = redoTestBatch(['9784592135746']);
    $item = $batch->items()->sole();
    $before = ApiCallLog::query()->where('scan_batch_item_id', $item->id)->count();

    $this->down = false;
    $this->post(route('scan.batches.items.redo', [$batch, $item]));

    expect(ApiCallLog::query()->where('scan_batch_item_id', $item->id)->count())->toBeGreaterThan($before);
});
