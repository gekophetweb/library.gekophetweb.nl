<?php

use App\Actions\Series\LinkSeries;
use App\Jobs\SyncSeriesVolumes;
use App\Models\Category;
use App\Models\CollectionObject;
use App\Models\LibraryShare;
use App\Models\Series;
use App\Models\SeriesVolume;
use App\Models\User;
use App\Support\Series\SeriesReference;
use App\Support\Series\VolumeNumber;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Support\Facades\Queue;

/*
 * Seen in production: NDL catalogues some volumes of 斉木楠雄のΨ難 with the reading of Ψ in
 * brackets (`斉木楠雄のΨ (サイ) 難`) and others without, and the two titles became two series.
 */

beforeEach(function () {
    $this->seed(CatalogueSeeder::class);
    $this->user = User::factory()->create();
    $this->books = Category::query()->where('slug', 'books')->sole();
    Queue::fake([SyncSeriesVolumes::class]);
});

function saikiBook(array $attributes = []): CollectionObject
{
    return CollectionObject::factory()->for(test()->user)->create($attributes + [
        'name' => '斉木楠雄のΨ難 1',
        'name_romanized' => 'Saiki Kusuo no Psi Nan 1',
        'category_id' => test()->books->id,
    ]);
}

function ndlSaiki(string $title): SeriesReference
{
    return new SeriesReference('ndl', "{$title}|麻生周一|集英社", $title, creator: '麻生周一', publisher: '集英社');
}

it('joins the series whose title carries the reading in brackets', function () {
    $series = app(LinkSeries::class)->toReference(saikiBook(), ndlSaiki('斉木楠雄のΨ (サイ) 難'), VolumeNumber::parse('1'));

    $joined = app(LinkSeries::class)->toReference(saikiBook(['name' => '斉木楠雄のΨ難 2']), ndlSaiki('斉木楠雄のΨ難'), VolumeNumber::parse('2'));

    expect($joined->id)->toBe($series->id)
        ->and(Series::count())->toBe(1);
});

it('merges one series into another', function () {
    $into = Series::factory()->for($this->user)->create(['name' => '斉木楠雄のΨ (サイ) 難', 'external_source' => 'ndl', 'external_id' => 'a', 'synced_at' => now()]);
    $from = Series::factory()->for($this->user)->create(['name' => '斉木楠雄のΨ難', 'external_source' => 'ndl', 'external_id' => 'b', 'synced_at' => now()]);
    SeriesVolume::factory()->for($from)->create(['volume_number' => '1', 'volume_sort' => 1]);
    $book = saikiBook(['serie_id' => $from->id]);
    $share = LibraryShare::factory()->forSeries($from)->create();

    $this->artisan('series:merge', ['from' => $from->id, 'into' => $into->id])
        ->expectsConfirmation("Move 1 item from series {$from->id} \"{$from->name}\" into series {$into->id} \"{$into->name}\" and delete series {$from->id}?", 'yes')
        ->assertSuccessful();

    expect($book->refresh()->serie_id)->toBe($into->id)
        ->and(Series::find($from->id))->toBeNull()
        ->and(SeriesVolume::query()->where('series_id', $from->id)->exists())->toBeFalse()
        ->and($share->refresh()->shareable_id)->toBe($into->id)
        ->and($into->refresh()->synced_at)->toBeNull();
    Queue::assertPushed(SyncSeriesVolumes::class, fn (SyncSeriesVolumes $job) => $job->series->is($into));
});

it('refuses to merge series of different owners', function () {
    $into = Series::factory()->for($this->user)->create();
    $from = Series::factory()->for(User::factory())->create();

    $this->artisan('series:merge', ['from' => $from->id, 'into' => $into->id])->assertFailed();

    expect(Series::find($from->id))->not->toBeNull();
});
