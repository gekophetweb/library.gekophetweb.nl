<?php

use App\Jobs\ProcessScanBatch;
use App\Models\ApiCallLog;
use App\Models\Category;
use App\Models\CollectionObject;
use App\Models\Language;
use App\Models\ScanBatch;
use App\Models\Series;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(CatalogueSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->books = Category::query()->whereNull('parent_id')->where('slug', 'books')->sole();
    $this->manga = $this->books->children()->where('slug', 'manga')->sole();
    $this->ja = Language::query()->where('code', 'ja')->sole();
    $this->en = Language::query()->where('code', 'en')->sole();
});

/** A batch for $user, with $codes scanned barcodes. */
function adminTestBatch(User $user, string $status, Language $language, ?Category $sub = null, array $codes = []): ScanBatch
{
    $batch = new ScanBatch([
        'category_id' => test()->books->id,
        'sub_category_id' => $sub?->id,
        'language_id' => $language->id,
        'ownership' => 'owned',
        'status' => $status,
    ]);
    $batch->forceFill(['user_id' => $user->id])->save();

    foreach ($codes as $code) {
        $batch->items()->create(['barcode' => $code]);
    }

    return $batch;
}

it('keeps the scan batch list away from non-admins', function () {
    $batch = adminTestBatch(User::factory()->create(), ScanBatch::DRAFT, $this->ja);

    $this->actingAs(User::factory()->create());
    $this->get(route('admin.scan-batches.index'))->assertForbidden();
    $this->delete(route('admin.scan-batches.destroy', $batch))->assertForbidden();
    expect($batch->exists())->toBeTrue();
});

it('lists every user\'s batches with their counts, but not the scanned codes', function () {
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create(['name' => 'Bob']);
    adminTestBatch($alice, ScanBatch::DRAFT, $this->ja, $this->manga, ['9784592135746', '9784088725093']);
    adminTestBatch($bob, ScanBatch::DONE, $this->en);

    $this->actingAs($this->admin)->get(route('admin.scan-batches.index'))
        ->assertOk()
        ->assertSee('Alice')->assertSee('Bob')
        ->assertSee('Books · Manga · Japanese')
        ->assertSee('Not committed')->assertSee('Done')
        ->assertDontSee('9784592135746');
});

it('filters by status, language and category', function () {
    $user = User::factory()->create();
    $draft = adminTestBatch($user, ScanBatch::DRAFT, $this->ja, $this->manga);
    $done = adminTestBatch($user, ScanBatch::DONE, $this->en);
    $this->actingAs($this->admin);

    $this->get(route('admin.scan-batches.index', ['status' => 'draft']))
        ->assertSee('data-batch="'.$draft->id.'"', false)->assertDontSee('data-batch="'.$done->id.'"', false);
    $this->get(route('admin.scan-batches.index', ['language' => $this->en->id]))
        ->assertSee('data-batch="'.$done->id.'"', false)->assertDontSee('data-batch="'.$draft->id.'"', false)
        ->assertSee('Showing batches for');
    $this->get(route('admin.scan-batches.index', ['category' => $this->manga->id]))
        ->assertSee('data-batch="'.$draft->id.'"', false)->assertDontSee('data-batch="'.$done->id.'"', false);
});

it('removes an abandoned batch, which frees its language, and keeps added items', function () {
    $user = User::factory()->create();
    $chinese = Language::query()->create(['code' => 'zh', 'name' => 'Chinese']);
    $batch = adminTestBatch($user, ScanBatch::DRAFT, $chinese, codes: ['9784592135746']);
    $owned = CollectionObject::factory()->for($user)->create();
    $this->actingAs($this->admin);

    $this->get(route('admin.languages.index'))
        ->assertSee('Used by 1 scan batch')
        ->assertSee(route('admin.scan-batches.index', ['language' => $chinese->id]));

    $this->delete(route('admin.scan-batches.destroy', $batch))->assertRedirect()
        ->assertSessionHas('status');

    expect(ScanBatch::query()->count())->toBe(0)
        ->and(DB::table('scan_batch_items')->count())->toBe(0)
        ->and($owned->exists())->toBeTrue();

    $this->delete(route('admin.languages.destroy', $chinese))->assertSessionHasNoErrors();
    expect(Language::query()->where('code', 'zh')->exists())->toBeFalse();
});

it('refuses to remove a batch that is still adding items', function () {
    $batch = adminTestBatch(User::factory()->create(), ScanBatch::PROCESSING, $this->ja);

    $this->actingAs($this->admin)->delete(route('admin.scan-batches.destroy', $batch))->assertSessionHasErrors('batch');

    expect($batch->exists())->toBeTrue();
});

it('shows scan batches in the admin menu and overview', function () {
    adminTestBatch(User::factory()->create(), ScanBatch::DRAFT, $this->ja);

    $this->actingAs($this->admin)->get(route('admin.home'))
        ->assertSee(route('admin.scan-batches.index'))
        ->assertSee('1 batch')
        ->assertSee('1 not committed');
});

it('redoes a finished batch: codes not added are looked up again, the rest left alone', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $batch = adminTestBatch($owner, ScanBatch::DONE, $this->ja, $this->manga, ['111', '222', '333', '444', '555']);
    $object = CollectionObject::factory()->for($owner)->create(['barcode' => '111']);
    $items = $batch->items()->get();
    $items[0]->update(['status' => 'added', 'object_id' => $object->id]);
    $items[1]->update(['status' => 'not_found', 'message' => 'Not found.']);
    $items[2]->update(['status' => 'failed', 'message' => 'No answer from Rakuten.']);
    $items[3]->update(['status' => 'invalid']);
    $items[4]->update(['status' => 'already_owned']);

    $this->actingAs($this->admin)->get(route('admin.scan-batches.show', $batch))
        ->assertSee('data-redo="2"', false)
        ->assertSee('Redo batch: 2 codes not added');

    $this->post(route('admin.scan-batches.redo', $batch))
        ->assertRedirect(route('admin.scan-batches.show', $batch))
        ->assertSessionHas('status', 'Redoing 2 codes…');

    expect($batch->refresh()->status)->toBe(ScanBatch::QUEUED)
        ->and($batch->finished_at)->toBeNull()
        ->and($batch->items()->pluck('status', 'barcode')->all())->toBe([
            '111' => 'added', '222' => 'pending', '333' => 'pending', '444' => 'invalid', '555' => 'already_owned',
        ])
        ->and($batch->items()->where('barcode', '222')->value('message'))->toBeNull();
    Queue::assertPushed(ProcessScanBatch::class, fn (ProcessScanBatch $job) => $job->batch->is($batch));
});

it('only redoes finished batches, and only for admins', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $running = adminTestBatch($owner, ScanBatch::PROCESSING, $this->ja, codes: ['111']);
    $done = adminTestBatch($owner, ScanBatch::DONE, $this->ja, codes: ['222']);
    $done->items()->update(['status' => 'not_found']);

    $this->actingAs($owner)->post(route('admin.scan-batches.redo', $done))->assertForbidden();
    $this->actingAs($this->admin)->post(route('admin.scan-batches.redo', $running))->assertStatus(409);

    $done->items()->update(['status' => 'already_owned']);
    $this->post(route('admin.scan-batches.redo', $done))->assertSessionHas('status', 'Every code in this batch was already added; nothing to redo.');
    Queue::assertNothingPushed();
});

it('redoes only the ticked codes that did not reach the collection', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $batch = adminTestBatch($owner, ScanBatch::DONE, $this->ja, $this->manga, ['111', '222', '333', '444']);
    // Found and in a series: nothing to redo (a book without a series would get its series again).
    $object = CollectionObject::factory()->for($owner)->create(['barcode' => '111', 'serie_id' => Series::factory()->for($owner)]);
    $items = $batch->items()->get();
    $items[0]->update(['status' => 'added', 'object_id' => $object->id]);
    $items[1]->update(['status' => 'not_found']);
    $items[2]->update(['status' => 'invalid', 'message' => 'Not a valid barcode for this category.']);
    $items[3]->update(['status' => 'failed']);
    $other = adminTestBatch($owner, ScanBatch::DONE, $this->ja, codes: ['999']);
    $other->items()->update(['status' => 'failed']);

    $this->actingAs($this->admin)->get(route('admin.scan-batches.show', $batch))
        ->assertSee('Redo ticked codes')
        ->assertDontSee('data-redo-item="'.$items[0]->id.'"', false)
        ->assertSee('data-redo-item="'.$items[1]->id.'"', false)
        ->assertSee('data-redo-item="'.$items[2]->id.'"', false);

    $this->post(route('admin.scan-batches.items.redo', $batch), ['items' => [$items[0]->id, $items[2]->id, $other->items()->value('id')]])
        ->assertRedirect(route('admin.scan-batches.show', $batch))
        ->assertSessionHas('status', 'Redoing 1 code…');

    expect($batch->refresh()->status)->toBe(ScanBatch::QUEUED)
        ->and($batch->items()->pluck('status', 'barcode')->all())->toBe(['111' => 'added', '222' => 'not_found', '333' => 'pending', '444' => 'failed'])
        ->and($batch->items()->where('barcode', '333')->value('message'))->toBeNull()
        ->and($other->items()->value('status'))->toBe('failed');
    Queue::assertPushed(ProcessScanBatch::class, 1);
});

it('lets an admin correct a mis-scanned code and look it up again', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $batch = adminTestBatch($owner, ScanBatch::DONE, $this->ja, $this->manga, ['8784840240536', '111']);
    $object = CollectionObject::factory()->for($owner)->create(['barcode' => '111']);
    [$wrong, $added] = $batch->items()->orderBy('id')->get()->all();
    $wrong->update(['status' => 'invalid', 'message' => 'Not a valid barcode for this category.']);
    $added->update(['status' => 'added', 'object_id' => $object->id]);

    $this->actingAs($this->admin)->get(route('admin.scan-batches.show', $batch))
        ->assertSee('data-barcode-edit="'.$wrong->id.'"', false)
        ->assertDontSee('data-barcode-edit="'.$added->id.'"', false);

    $this->put(route('admin.scan-batches.items.barcode', [$batch, $wrong]), ['barcode' => ' 978-4-8402-4053-6 '])
        ->assertRedirect(route('admin.scan-batches.show', $batch))
        ->assertSessionHas('status', 'Changed 8784840240536 to 9784840240536; looking it up again…');

    expect($wrong->refresh()->only('barcode', 'status', 'message'))->toBe(['barcode' => '9784840240536', 'status' => 'pending', 'message' => null])
        ->and($batch->refresh()->status)->toBe(ScanBatch::QUEUED);
    Queue::assertPushed(ProcessScanBatch::class, 1);
});

it('refuses a code change that is a duplicate, unchanged, on an added code, or not by an admin', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $batch = adminTestBatch($owner, ScanBatch::DONE, $this->ja, codes: ['111', '222', '333']);
    $object = CollectionObject::factory()->for($owner)->create(['barcode' => '333']);
    [$one, , $three] = $batch->items()->orderBy('id')->get()->all();
    $batch->items()->update(['status' => 'not_found']);
    $three->update(['status' => 'added', 'object_id' => $object->id]);

    $this->actingAs($owner)->put(route('admin.scan-batches.items.barcode', [$batch, $one]), ['barcode' => '999'])->assertForbidden();

    $this->actingAs($this->admin);
    $this->put(route('admin.scan-batches.items.barcode', [$batch, $one]), ['barcode' => '222'])
        ->assertSessionHasErrorsIn("barcode-{$one->id}", ['barcode' => '222 is already in this batch.']);
    $this->put(route('admin.scan-batches.items.barcode', [$batch, $one]), ['barcode' => '111'])
        ->assertSessionHasErrorsIn("barcode-{$one->id}", 'barcode');
    $this->put(route('admin.scan-batches.items.barcode', [$batch, $three]), ['barcode' => '444'])->assertStatus(409);

    $other = adminTestBatch($owner, ScanBatch::DONE, $this->ja, codes: ['555']);
    $this->put(route('admin.scan-batches.items.barcode', [$other, $one]), ['barcode' => '666'])->assertNotFound();

    expect($batch->items()->orderBy('id')->pluck('barcode')->all())->toBe(['111', '222', '333']);
    Queue::assertNothingPushed();
});

it('asks for ticked codes, and redoes codes only on finished batches for admins', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $done = adminTestBatch($owner, ScanBatch::DONE, $this->ja, codes: ['111']);
    $done->items()->update(['status' => 'failed']);
    $running = adminTestBatch($owner, ScanBatch::PROCESSING, $this->ja, codes: ['222']);

    $this->actingAs($owner)->post(route('admin.scan-batches.items.redo', $done), ['items' => [$done->items()->value('id')]])->assertForbidden();
    $this->actingAs($this->admin)->post(route('admin.scan-batches.items.redo', $running), ['items' => [$running->items()->value('id')]])->assertStatus(409);
    $this->post(route('admin.scan-batches.items.redo', $done), [])->assertSessionHasErrors('items');
    Queue::assertNothingPushed();
});

it('shows the raw API answers for a scanned code', function () {
    $owner = User::factory()->create();
    $batch = adminTestBatch($owner, ScanBatch::DONE, $this->ja, codes: ['9784592135746', '222']);
    [$item, $other] = $batch->items()->get()->all();
    $item->update(['status' => 'not_found']);
    $log = fn (array $attributes) => ApiCallLog::query()->create($attributes + [
        'scan_batch_item_id' => $item->id, 'provider' => 'rakuten', 'operation' => 'lookup', 'query' => 'isbn=9784592135746',
        'category' => 'books', 'language' => 'ja', 'outcome' => 'success', 'status_code' => 200, 'duration_ms' => 120,
    ]);
    $log(['response_body' => '{"count":0,"Items":[],"note":"<script>"}']);
    $log(['provider' => 'ndl', 'outcome' => 'error', 'status_code' => null, 'error' => 'cURL error 28: Operation timed out']);

    $this->actingAs($this->admin)->get(route('admin.scan-batches.show', $batch))
        ->assertSee(route('admin.scan-batches.items.show', [$batch, $item]))
        ->assertSee('2 calls')
        ->assertDontSee('href="'.route('admin.scan-batches.items.show', [$batch, $other]).'"', false);

    $this->get(route('admin.scan-batches.items.show', [$batch, $item]))
        ->assertOk()
        ->assertSee('isbn=9784592135746')
        ->assertSee('"count": 0')                  // pretty-printed JSON
        ->assertSee('&lt;script&gt;', false)       // escaped
        ->assertSee('cURL error 28: Operation timed out')
        ->assertSee('No response body (the request did not get an answer).');

    $this->get(route('admin.scan-batches.items.show', [$batch, $other]))->assertSee('No API calls are linked to this code.');

    $elsewhere = adminTestBatch($owner, ScanBatch::DONE, $this->ja, codes: ['333']);
    $this->get(route('admin.scan-batches.items.show', [$elsewhere, $item]))->assertNotFound();
    $this->actingAs($owner)->get(route('admin.scan-batches.items.show', [$batch, $item]))->assertForbidden();
});
