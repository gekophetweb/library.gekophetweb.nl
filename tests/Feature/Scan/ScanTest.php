<?php

use App\Enums\ApiKeyProvider;
use App\Http\Controllers\ScanBatchController;
use App\Jobs\ProcessScanBatch;
use App\Models\ApiCallLog;
use App\Models\Category;
use App\Models\CollectionObject;
use App\Models\Language;
use App\Models\ScanBatch;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->seed(CatalogueSeeder::class);
    $this->user = User::factory()->withApiKeys()->create();
    $this->books = Category::query()->where('slug', 'books')->sole();
    $this->manga = Category::query()->where('slug', 'manga')->sole();
    $this->ja = Language::query()->where('code', 'ja')->sole();
    $this->actingAs($this->user);

    Http::fake(function (Request $request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return match (true) {
            str_contains($request->url(), 'rakuten') && ($query['isbn'] ?? '') === '9784592135746' => Http::response(file_get_contents(base_path('tests/Fixtures/catalogue/rakuten-isbn-9784592135746.json'))),
            str_contains($request->url(), 'rakuten') => Http::response(['count' => 0, 'Items' => []]),
            default => Http::response(['kind' => 'books#volumes', 'totalItems' => 0]),
        };
    });
});

function startScan(string $mode): TestResponse
{
    return test()->post(route('scan.start'), [
        'category_id' => test()->books->id,
        'sub_category_id' => test()->manga->id,
        'language_id' => test()->ja->id,
        'mode' => $mode,
    ]);
}

it('opens the scanner dialog from the dashboard', function () {
    $this->get(route('home'))->assertSee('Scan items')->assertSee(route('scan.start'));
});

it('opens the scanner dialog from a category page with that category selected', function () {
    $this->get(route('library.category', $this->books))
        ->assertSee('Scan items')
        ->assertSee(route('scan.start'))
        ->assertSee('<option value="'.$this->books->id.'" selected>', false)
        ->assertDontSee('<option value="'.$this->manga->id.'" selected>', false);
});

it('selects the sub-category filter in the scanner dialog', function () {
    $this->get(route('library.category', [$this->books, 'sub' => $this->manga->id]))
        ->assertSee('<option value="'.$this->books->id.'" selected>', false)
        ->assertSee('<option value="'.$this->manga->id.'" selected>', false);
});

it('sends a user without every API key to settings instead of scanning', function (string $mode) {
    $this->user->apiKeys()->where('provider', ApiKeyProvider::Hardcover->value)->delete();

    startScan($mode)->assertRedirect(route('settings.api-keys'));

    expect(ScanBatch::count())->toBe(0)
        ->and(session('scan.setup'))->toBeNull();

    // Follow the redirect: asserting on the session errors first would keep them off the page.
    $this->get(route('settings.api-keys'))
        ->assertSee('Add your API keys before scanning. Still missing: Hardcover.')
        ->assertSee('Needed to scan')
        ->assertSee('How to get a Hardcover key')
        ->assertSee(route('settings.api-keys.show', 'hardcover'));
})->with(['single', 'batch']);

it('shows the missing keys in the scanner dialog instead of the form', function () {
    $this->user->apiKeys()->whereIn('provider', [ApiKeyProvider::GoogleBooks->value, ApiKeyProvider::Hardcover->value])->delete();

    $this->get(route('home'))
        ->assertSee('Add your API keys first')
        ->assertSeeInOrder(['Google Books', 'Hardcover'])
        ->assertSee(route('settings.api-keys'), false)
        ->assertDontSee(route('scan.start'));
});

it('scans without a Rakuten key and skips Rakuten in the lookup', function () {
    $this->user->apiKeys()->where('provider', ApiKeyProvider::Rakuten->value)->delete();

    $this->get(route('home'))->assertDontSee('Add your API keys first')->assertSee(route('scan.start'));
    $this->get(route('settings.api-keys'))->assertSee('Optional');

    startScan('single')->assertRedirect(route('scan.single'));
    $this->post(route('scan.single.lookup'), ['barcode' => '9784592135746'])->assertRedirect(route('scan.single'));
    $this->get(route('scan.single'))->assertSee('Rakuten Books')->assertSee(route('settings.api-keys'));

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'rakuten'));
});

it('treats a Rakuten key without its access key as optional too', function () {
    $this->user->apiKeys()->where('provider', ApiKeyProvider::Rakuten->value)->update(['secret' => null]);

    startScan('batch')->assertRedirect();
    expect(ScanBatch::count())->toBe(1)
        ->and($this->user->missingScanKeys())->toBe([]);
});

it('explains how to get every key on its own guide page', function () {
    $overview = $this->get(route('settings.api-keys'))->assertOk()->assertDontSee('Needed to scan');

    foreach (ApiKeyProvider::cases() as $provider) {
        $overview->assertSee(route('settings.api-keys.show', $provider->value));

        $this->get(route('settings.api-keys.show', $provider->value))
            ->assertOk()
            ->assertSee("How to get a {$provider->label()} key")
            ->assertSee('Key saved')
            ->assertSee($provider->setupUrl(), false)
            ->assertSee(route('settings.api-keys.update', $provider->value), false);
    }

    $this->get(route('settings.api-keys.show', 'myspace'))->assertNotFound();
});

it('requires a sub-category when the category has them, plus a language and a mode', function () {
    $this->post(route('scan.start'), ['category_id' => $this->books->id])
        ->assertSessionHasErrors(['sub_category_id', 'language_id', 'mode']);

    $games = Category::query()->where('slug', 'games')->sole();
    $this->post(route('scan.start'), ['category_id' => $games->id, 'language_id' => $this->ja->id, 'mode' => 'single'])
        ->assertSessionHasNoErrors();

    $this->post(route('scan.start'), ['category_id' => $this->manga->id, 'language_id' => $this->ja->id, 'mode' => 'single'])
        ->assertSessionHasErrors('category_id');
});

it('looks a single scan up and adds it to the collection', function () {
    startScan('single')->assertRedirect(route('scan.single'));

    $this->post(route('scan.single.lookup'), ['barcode' => '978-4-592-13574-6'])->assertRedirect(route('scan.single'));
    $this->get(route('scan.single'))->assertSee('ベルセルク 1')->assertSee('Add to collection');

    $response = $this->post(route('scan.single.add'));
    $object = CollectionObject::sole();
    $response->assertRedirect(route('objects.show', $object));

    expect($object->user_id)->toBe($this->user->id)
        ->and($object->barcode)->toBe('9784592135746')
        ->and($object->name)->toBe('ベルセルク 1')
        ->and($object->name_romanized)->toBe('Beruseruku 1') // from Rakuten's titleKana (RomanizeOnScanTest)
        ->and($object->category_id)->toBe($this->books->id)
        ->and($object->sub_category_id)->toBe($this->manga->id)
        ->and($object->languages->pluck('code')->all())->toBe(['ja'])
        ->and($object->publisher->name)->toBe('白泉社')
        ->and($object->bookDetail->artist->name)->toBe('三浦 建太郎')
        ->and($object->bookDetail->volume)->toBe('1')
        ->and($object->release_date->toDateString())->toBe('1990-12-01');
});

it('says when a scanned item is already in the collection, without a lookup', function () {
    $owned = CollectionObject::factory()->for($this->user)->create(['barcode' => '9784592135746', 'category_id' => $this->books->id]);
    startScan('single');

    $this->post(route('scan.single.lookup'), ['barcode' => '9784592135746']);
    $this->get(route('scan.single'))->assertSee('already in your collection')->assertSee(route('objects.show', $owned));

    Http::assertNothingSent();
});

it('offers to add an unknown item by hand', function () {
    startScan('single');

    $this->post(route('scan.single.lookup'), ['barcode' => '9784088725093']);
    $this->get(route('scan.single'))->assertSee('Not found');

    $this->post(route('scan.single.add'), ['name' => ''])->assertSessionHasErrors(['name', 'name_romanized']);
    expect(CollectionObject::count())->toBe(0);

    $this->post(route('scan.single.add'), ['name' => 'ONE PIECE 1', 'name_romanized' => 'One Piece 1'])->assertRedirect();
    expect(CollectionObject::sole()->only(['barcode', 'name_romanized']))->toBe(['barcode' => '9784088725093', 'name_romanized' => 'One Piece 1']);
});

it('points to settings when a provider key is removed after the scan started', function () {
    startScan('single');
    $this->user->apiKeys()->delete();

    $this->post(route('scan.single.lookup'), ['barcode' => '9784088725093']);
    $this->get(route('scan.single'))->assertSee('Rakuten Books')->assertSee(route('settings.api-keys'));
});

it('builds a removable working list in batch mode', function () {
    CollectionObject::factory()->for($this->user)->create(['barcode' => '9784592135753']);
    startScan('batch');
    $batch = ScanBatch::sole();

    $this->post(route('scan.batches.items.store', $batch), ['barcode' => '9784592135746'])->assertSessionHasNoErrors();
    $this->post(route('scan.batches.items.store', $batch), ['barcode' => '9784088725093']);
    $this->post(route('scan.batches.items.store', $batch), ['barcode' => '9784592135746'])->assertSessionHasErrors('barcode');
    $this->post(route('scan.batches.items.store', $batch), ['barcode' => '9784592135753'])->assertSessionHasErrors('barcode');

    expect($batch->items()->pluck('barcode')->all())->toBe(['9784592135746', '9784088725093'])
        ->and(CollectionObject::count())->toBe(1);

    $this->delete(route('scan.batches.items.destroy', [$batch, $batch->items()->first()]))->assertRedirect();
    expect($batch->items()->pluck('barcode')->all())->toBe(['9784088725093']);
    Http::assertNothingSent();
});

it('adds a pasted list to the working list, one barcode per line', function () {
    CollectionObject::factory()->for($this->user)->create(['barcode' => '9784592135753']);
    startScan('batch');
    $batch = ScanBatch::sole();
    $this->post(route('scan.batches.items.store', $batch), ['barcode' => '9784088725093']);

    $this->post(route('scan.batches.items.bulk', $batch), [
        'barcodes' => "978-4-592-13574-6\r\n\n  4988601234567  \n9784088725093\n9784592135753\n9784592135746\n".str_repeat('9', 65),
    ])
        ->assertRedirect(route('scan.batches.show', $batch))
        ->assertSessionHas('status', 'Added 2 items to the list.')
        ->assertSessionHas('bulk_skipped', [
            ['line' => '9784088725093', 'reason' => 'is already on this list'],
            ['line' => '9784592135753', 'reason' => 'is already in your collection'],
            ['line' => '9784592135746', 'reason' => 'is already on this list'],
            ['line' => str_repeat('9', 65), 'reason' => 'is too long for a barcode'],
        ])
        // Only the lines that still need attention go back into the textarea.
        ->assertSessionHasInput('barcodes', "9784088725093\n9784592135753\n9784592135746\n".str_repeat('9', 65));

    expect($batch->items()->pluck('barcode')->all())->toBe(['9784088725093', '9784592135746', '4988601234567']);
    Http::assertNothingSent();
});

it('offers camera scanning on both scan pages, filling the right field', function () {
    startScan('single');
    $this->get(route('scan.single'))
        ->assertSee('data-camera-scanner data-mode="single" data-target="barcode"', false)
        ->assertSee('Scan with camera');

    startScan('batch');
    $batch = ScanBatch::sole();
    $this->get(route('scan.batches.show', $batch))
        ->assertSee('data-camera-scanner data-mode="batch" data-target="barcodes"', false);

    // A committed batch takes no more items, so no camera either.
    $this->post(route('scan.batches.items.store', $batch), ['barcode' => '9784592135746']);
    Queue::fake();
    $this->post(route('scan.batches.commit', $batch));
    $this->get(route('scan.batches.show', $batch))->assertDontSee('data-camera-scanner', false);
});

it('shows the paste box and what a pasted list skipped', function () {
    startScan('batch');
    $batch = ScanBatch::sole();
    $this->post(route('scan.batches.items.store', $batch), ['barcode' => '9784592135746']);

    $this->get(route('scan.batches.show', $batch))->assertSee('Paste a list instead')->assertDontSee('data-bulk-skipped', false);

    $this->followingRedirects()
        ->post(route('scan.batches.items.bulk', $batch), ['barcodes' => '9784592135746'])
        ->assertSee('Nothing added.')
        ->assertSee('1 line not added')
        ->assertSee('is already on this list');
});

it('rejects an empty or oversized paste, and pastes into a committed batch', function () {
    Queue::fake();
    startScan('batch');
    $batch = ScanBatch::sole();

    $this->post(route('scan.batches.items.bulk', $batch), ['barcodes' => "\n  \n"])->assertSessionHasErrors('barcodes');
    $this->post(route('scan.batches.items.bulk', $batch), [
        'barcodes' => implode("\n", range(1, ScanBatchController::MAX_BULK_LINES + 1)),
    ])->assertSessionHasErrors('barcodes');
    expect($batch->items()->count())->toBe(0);

    $this->post(route('scan.batches.items.bulk', $batch), ['barcodes' => '9784592135746']);
    $this->post(route('scan.batches.commit', $batch));
    $this->post(route('scan.batches.items.bulk', $batch), ['barcodes' => '9784088725093'])->assertStatus(409);
});

it('commits a batch to a queued job', function () {
    Queue::fake();
    startScan('batch');
    $batch = ScanBatch::sole();
    $this->post(route('scan.batches.items.store', $batch), ['barcode' => '9784592135746']);

    $this->post(route('scan.batches.commit', $batch))->assertRedirect(route('scan.batches.show', $batch));

    Queue::assertPushed(ProcessScanBatch::class, fn (ProcessScanBatch $job) => $job->batch->is($batch));
    expect($batch->refresh()->status)->toBe('queued');
    $this->post(route('scan.batches.items.store', $batch), ['barcode' => '9784088725093'])->assertStatus(409);
    $this->get(route('scan.batches.show', $batch))->assertSee('Adding');
});

it('adds the found items when the batch job runs and reports the rest', function () {
    startScan('batch');
    $batch = ScanBatch::sole();
    foreach (['9784592135746', '9784088725093'] as $barcode) {
        $this->post(route('scan.batches.items.store', $batch), ['barcode' => $barcode]);
    }

    $this->post(route('scan.batches.commit', $batch)); // the sync queue runs the job

    $batch->refresh();
    expect($batch->status)->toBe('done')
        ->and($batch->items()->pluck('status', 'barcode')->all())->toBe(['9784592135746' => 'added', '9784088725093' => 'not_found'])
        ->and(CollectionObject::sole()->name)->toBe('ベルセルク 1');

    $this->get(route('scan.batches.show', $batch))->assertSee('ベルセルク 1')->assertSee('Not found');
});

it('shows not found codes in red with an add-by-hand form, and only those', function () {
    startScan('batch');
    $batch = ScanBatch::sole();
    foreach (['9784592135746', '9784088725093'] as $barcode) {
        $this->post(route('scan.batches.items.store', $batch), ['barcode' => $barcode]);
    }
    $this->post(route('scan.batches.commit', $batch));
    $found = $batch->items()->where('barcode', '9784592135746')->sole();
    $missing = $batch->items()->where('barcode', '9784088725093')->sole();

    $this->get(route('scan.batches.show', $batch))
        ->assertSee('data-not-found', false)
        ->assertSee('font-medium text-red-600 dark:text-red-400" data-status="not_found"', false)
        ->assertSee('data-add-by-hand="'.$missing->id.'"', false)
        ->assertDontSee('data-add-by-hand="'.$found->id.'"', false);

    // The required fields are checked, per code.
    $this->post(route('scan.batches.items.add', [$batch, $missing]), ['name' => ''])
        ->assertSessionHasErrorsIn('item'.$missing->id, ['name', 'name_romanized']);

    $this->post(route('scan.batches.items.add', [$batch, $missing]), ['name' => 'ONE PIECE 1', 'name_romanized' => 'One Piece 1'])
        ->assertRedirect(route('scan.batches.show', $batch))
        ->assertSessionHas('status', 'Added ONE PIECE 1 to your collection.');

    $object = CollectionObject::query()->where('barcode', '9784088725093')->sole();
    expect($object->user_id)->toBe($this->user->id)
        ->and($object->name_romanized)->toBe('One Piece 1')
        ->and($object->sub_category_id)->toBe($this->manga->id)
        ->and($missing->refresh()->status)->toBe('added')
        ->and($missing->object_id)->toBe($object->id);

    // Anything but a not-found code is refused: found, added by hand already, or someone else's.
    $this->post(route('scan.batches.items.add', [$batch, $found]), ['name' => 'X', 'name_romanized' => 'X'])->assertStatus(409);
    $this->post(route('scan.batches.items.add', [$batch, $missing]), ['name' => 'X', 'name_romanized' => 'X'])->assertStatus(409);
    $this->actingAs(User::factory()->create())
        ->post(route('scan.batches.items.add', [$batch, $missing]), ['name' => 'X', 'name_romanized' => 'X'])->assertNotFound();
    expect(CollectionObject::query()->count())->toBe(2);
});

it('links every API call of a batch job to its code and keeps the raw answer', function () {
    startScan('batch');
    $batch = ScanBatch::sole();
    $this->post(route('scan.batches.items.store', $batch), ['barcode' => '9784592135746']);

    $this->post(route('scan.batches.commit', $batch));

    $item = $batch->items()->sole();
    $rakuten = $item->apiCalls()->where('provider', 'rakuten')->where('operation', 'lookup')->sole();
    expect($rakuten->response_body)->toContain('ベルセルク')
        ->and($rakuten->response_body)->not->toContain('applicationId')
        ->and(ApiCallLog::query()->whereNull('scan_batch_item_id')->count())->toBe(0);

    // A single scan is recorded in a single-scan batch, its calls linked to its code too.
    startScan('single');
    $this->post(route('scan.single.lookup'), ['barcode' => '9784088725093']);
    $single = ScanBatch::query()->where('mode', ScanBatch::MODE_SINGLE)->sole()->items()->sole();
    expect($single->apiCalls()->exists())->toBeTrue()
        ->and(ApiCallLog::query()->whereNull('scan_batch_item_id')->count())->toBe(0);
});

it('keeps batches private to their owner', function () {
    startScan('batch');
    $batch = ScanBatch::sole();

    $this->actingAs(User::factory()->create());
    $this->get(route('scan.batches.show', $batch))->assertNotFound();
    $this->post(route('scan.batches.items.store', $batch), ['barcode' => '9784592135746'])->assertNotFound();
    $this->post(route('scan.batches.items.bulk', $batch), ['barcodes' => '9784592135746'])->assertNotFound();
    $this->post(route('scan.batches.commit', $batch))->assertNotFound();
});
