<?php

use App\Models\Category;
use App\Models\Language;
use App\Models\ScanBatch;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;

beforeEach(function () {
    $this->seed(CatalogueSeeder::class);
    $this->user = User::factory()->withApiKeys()->create();
    $this->books = Category::query()->whereNull('parent_id')->where('slug', 'books')->sole();
    $this->ja = Language::query()->where('code', 'ja')->sole();
});

function listTestBatch(User $user, string $status, array $statuses = []): ScanBatch
{
    $batch = new ScanBatch([
        'category_id' => test()->books->id,
        'language_id' => test()->ja->id,
        'ownership' => 'owned',
        'status' => $status,
    ]);
    $batch->forceFill(['user_id' => $user->id])->save();

    foreach ($statuses as $i => $itemStatus) {
        $batch->items()->create(['barcode' => '97840000000'.$i, 'status' => $itemStatus]);
    }

    return $batch;
}

it('lists the user\'s own batches, not other users\'', function () {
    $draft = listTestBatch($this->user, ScanBatch::DRAFT, ['pending', 'pending']);
    $done = listTestBatch($this->user, ScanBatch::DONE, ['added', 'failed', 'not_found']);
    $theirs = listTestBatch(User::factory()->create(), ScanBatch::DONE);

    $this->actingAs($this->user)->get(route('scan.batches.index'))
        ->assertOk()
        ->assertSee('Your scan batches')
        ->assertSee('data-batch="'.$draft->id.'"', false)
        ->assertSee('data-batch="'.$done->id.'"', false)
        ->assertDontSee('data-batch="'.$theirs->id.'"', false)
        ->assertSee(route('scan.batches.show', $draft))
        ->assertSee('Continue')
        ->assertSee('1 / 3')
        ->assertSee('data-failed="1"', false);
});

it('links to the batch list from the library and a batch, once there is one', function () {
    $this->actingAs($this->user)->get(route('home'))->assertDontSee(route('scan.batches.index'));
    $this->get(route('scan.batches.index'))->assertSee('No batch scans yet');

    $batch = listTestBatch($this->user, ScanBatch::DRAFT);

    $this->get(route('home'))->assertSee(route('scan.batches.index'));
    $this->get(route('scan.batches.show', $batch))->assertSee(route('scan.batches.index'));
});
