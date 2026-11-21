<?php

use App\Models\ApiCallLog;
use App\Models\Category;
use App\Models\CollectionObject;
use App\Models\Language;
use App\Models\ScanBatch;
use App\Models\ScanBatchItem;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
 * Single scans are recorded as codes in an automatic "single" scan batch, one per scan
 * setup, so admins can see, redo and romanize them like batch scans.
 */

beforeEach(function () {
    $this->seed(CatalogueSeeder::class);
    $this->user = User::factory()->withApiKeys()->create();
    $this->books = Category::query()->where('slug', 'books')->sole();
    $this->manga = Category::query()->where('slug', 'manga')->sole();
    $this->ja = Language::query()->where('code', 'ja')->sole();
    $this->ko = Language::query()->where('code', 'ko')->sole();
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

function startSingleScan(Language $language): void
{
    test()->post(route('scan.start'), [
        'category_id' => test()->books->id,
        'sub_category_id' => test()->manga->id,
        'language_id' => $language->id,
        'mode' => 'single',
    ]);
}

it('records a single scan as a code in a single batch, with its API calls', function () {
    startSingleScan($this->ja);
    $this->post(route('scan.single.lookup'), ['barcode' => '9784592135746']);

    $batch = ScanBatch::sole();
    $item = ScanBatchItem::sole();

    expect($batch->mode)->toBe(ScanBatch::MODE_SINGLE)
        ->and($batch->isSingle())->toBeTrue()
        ->and($batch->status)->toBe(ScanBatch::DONE)
        ->and($batch->user_id)->toBe($this->user->id)
        ->and($batch->language_id)->toBe($this->ja->id)
        ->and($batch->sub_category_id)->toBe($this->manga->id)
        ->and($item->scan_batch_id)->toBe($batch->id)
        ->and($item->barcode)->toBe('9784592135746')
        ->and($item->status)->toBe('not_added')
        ->and($item->object_id)->toBeNull()
        ->and(ApiCallLog::query()->where('scan_batch_item_id', $item->id)->exists())->toBeTrue();

    $this->post(route('scan.single.add'));

    expect($item->refresh()->status)->toBe('added')
        ->and($item->object_id)->toBe(CollectionObject::sole()->id);
});

it('updates the same code on a re-scan and starts a new single batch for another setup', function () {
    startSingleScan($this->ja);
    $this->post(route('scan.single.lookup'), ['barcode' => '9784592135746']);
    $this->post(route('scan.single.lookup'), ['barcode' => '9784592135746']);

    expect(ScanBatch::count())->toBe(1)->and(ScanBatchItem::count())->toBe(1);

    startSingleScan($this->ko);
    $this->post(route('scan.single.lookup'), ['barcode' => '9784592135746']);

    expect(ScanBatch::query()->where('mode', ScanBatch::MODE_SINGLE)->pluck('language_id')->sort()->values()->all())
        ->toBe(collect([$this->ja->id, $this->ko->id])->sort()->values()->all())
        ->and(ScanBatchItem::count())->toBe(2);
});

it('records not found, and an item added by hand', function () {
    startSingleScan($this->ja);
    $this->post(route('scan.single.lookup'), ['barcode' => '9784088725093']);

    $item = ScanBatchItem::sole();
    expect($item->status)->toBe('not_found');

    $this->post(route('scan.single.add'), ['name' => 'ONE PIECE 1', 'name_romanized' => 'One Piece 1']);

    expect($item->refresh()->status)->toBe('added')
        ->and($item->object_id)->toBe(CollectionObject::sole()->id);
});

it('records a code already in the collection', function () {
    $owned = CollectionObject::factory()->for($this->user)->create(['barcode' => '9784592135746', 'category_id' => $this->books->id]);
    startSingleScan($this->ja);

    $this->post(route('scan.single.lookup'), ['barcode' => '9784592135746']);

    expect(ScanBatchItem::sole()->only(['status', 'object_id']))->toBe(['status' => 'already_owned', 'object_id' => $owned->id]);
    Http::assertNothingSent();
});

it('records a failed lookup and still shows the scan page', function () {
    Http::fake(fn () => throw new RuntimeException('boom'));
    startSingleScan($this->ja);

    $this->post(route('scan.single.lookup'), ['barcode' => '9784592135746'])->assertRedirect(route('scan.single'));
    $this->get(route('scan.single'))->assertOk();

    expect(ScanBatchItem::sole()->status)->toBe('failed');
});

it('still adds a scan looked up before scans were recorded', function () {
    startSingleScan($this->ja);
    $this->post(route('scan.single.lookup'), ['barcode' => '9784592135746']);
    session()->forget('scan.pending.scan_item_id');
    ScanBatchItem::query()->delete();

    $this->post(route('scan.single.add'))->assertRedirect();

    expect(CollectionObject::count())->toBe(1)->and(ScanBatchItem::count())->toBe(0);
});
