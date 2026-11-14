<?php

use App\Models\Category;
use App\Models\CollectionObject;
use App\Models\Language;
use App\Models\ScanBatch;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Support\Facades\DB;

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
