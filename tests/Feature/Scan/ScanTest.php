<?php

use App\Enums\ApiKeyProvider;
use App\Http\Controllers\ScanBatchController;
use App\Jobs\ProcessScanBatch;
use App\Models\Category;
use App\Models\CollectionObject;
use App\Models\Language;
use App\Models\ScanBatch;
use App\Models\User;
use App\Models\UserApiKey;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->seed(CatalogueSeeder::class);
    $this->user = User::factory()->create();
    UserApiKey::factory()->for($this->user)->create(['provider' => ApiKeyProvider::Rakuten, 'key' => 'app-123']);
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
        ->and($object->name_romanized)->toBe('ベルセルク 1')
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

it('points to settings when a provider key is missing', function () {
    $this->user->apiKeys()->delete();
    startScan('single');

    $this->post(route('scan.single.lookup'), ['barcode' => '9784088725093']);
    $this->get(route('scan.single'))->assertSee('Rakuten Books')->assertSee(route('settings.edit'));
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

it('keeps batches private to their owner', function () {
    startScan('batch');
    $batch = ScanBatch::sole();

    $this->actingAs(User::factory()->create());
    $this->get(route('scan.batches.show', $batch))->assertNotFound();
    $this->post(route('scan.batches.items.store', $batch), ['barcode' => '9784592135746'])->assertNotFound();
    $this->post(route('scan.batches.items.bulk', $batch), ['barcodes' => '9784592135746'])->assertNotFound();
    $this->post(route('scan.batches.commit', $batch))->assertNotFound();
});
