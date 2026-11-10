<?php

use App\Enums\ApiKeyProvider;
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
    $this->post(route('scan.batches.commit', $batch))->assertNotFound();
});
