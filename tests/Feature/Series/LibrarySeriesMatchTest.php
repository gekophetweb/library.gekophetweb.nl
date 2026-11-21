<?php

use App\Actions\Series\DetectSeries;
use App\Jobs\SyncSeriesVolumes;
use App\Models\Category;
use App\Models\CollectionObject;
use App\Models\Series;
use App\Models\SeriesVolume;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/*
 * Series detection asks the owner's own library first: a volume list that carries the
 * ISBN, or the series that holds their other volumes of the same title. Seen in
 * production: Hardcover's "Magi: The Labyrinth of Magic" had volumes 1, 2 and 10+ but
 * not the Japanese ISBNs of マギ 3–8, which only got "マギ" / "Magi" suggestions.
 */

const MAGI_3 = '9784091221964';

beforeEach(function () {
    $this->seed(CatalogueSeeder::class);
    $this->user = User::factory()->create();
    $this->books = Category::query()->where('slug', 'books')->sole();
    $this->manga = Category::query()->where('slug', 'manga')->sole();
    $this->magi = Series::factory()->for($this->user)->create([
        'name' => 'Magi: The Labyrinth of Magic', 'name_romanized' => 'Magi: The Labyrinth of Magic',
        'external_source' => 'hardcover', 'external_id' => '27974', 'synced_at' => now(),
    ]);
    Http::fake(fn () => Http::response(['error' => 'notfound'], 404));
    Queue::fake([SyncSeriesVolumes::class]);
});

function magiBook(string $name, array $attributes = []): CollectionObject
{
    return CollectionObject::factory()->for($attributes['user'] ?? test()->user)->create(array_diff_key($attributes, ['user' => 1]) + [
        'name' => $name,
        'name_romanized' => 'Magi',
        'category_id' => test()->books->id,
        'sub_category_id' => test()->manga->id,
    ]);
}

it('joins the series that holds the owner\'s other volumes of the same title', function () {
    magiBook('マギ 1', ['serie_id' => $this->magi->id]);
    magiBook('マギ 10', ['serie_id' => $this->magi->id]);
    $volume3 = magiBook('マギ 3', ['barcode' => MAGI_3]);

    $resolution = app(DetectSeries::class)->handle($volume3, 'en');

    expect($resolution->best()->source)->toBe(DetectSeries::SOURCE_LIBRARY)
        ->and($volume3->refresh()->serie_id)->toBe($this->magi->id)
        ->and($volume3->bookDetail->volume)->toBe('3')
        ->and($volume3->series_suggestion)->toBeNull();
    Http::assertNothingSent();
});

it('joins a series whose volume list carries the ISBN, with that volume number', function () {
    SeriesVolume::factory()->for($this->magi)->create(['volume_number' => '7', 'volume_sort' => 7, 'isbns' => ['9781421559575', '9784091227706']]);
    $volume7 = magiBook('Magi', ['barcode' => '9784091227706']);

    app(DetectSeries::class)->handle($volume7, 'en');

    expect($volume7->refresh()->serie_id)->toBe($this->magi->id)
        ->and($volume7->bookDetail->volume)->toBe('7');
});

it('joins a hand-made series of the same title', function () {
    $manual = Series::factory()->for($this->user)->create(['name' => 'My Magi shelf']);
    magiBook('マギ 1', ['serie_id' => $manual->id]);
    $volume3 = magiBook('マギ 3', ['barcode' => MAGI_3]);

    app(DetectSeries::class)->handle($volume3, 'en');

    expect($volume3->refresh()->serie_id)->toBe($manual->id)
        ->and(Series::count())->toBe(2);
});

it('does not choose when the title is spread over two series', function () {
    magiBook('マギ 1', ['serie_id' => $this->magi->id]);
    magiBook('マギ 2', ['serie_id' => Series::factory()->for($this->user)->create(['name' => 'マギ'])->id]);
    $volume3 = magiBook('マギ 3', ['barcode' => MAGI_3]);

    app(DetectSeries::class)->handle($volume3, 'en');

    expect($volume3->refresh()->serie_id)->toBeNull();
});

it('never uses another user\'s series', function () {
    $other = User::factory()->create();
    $theirs = Series::factory()->for($other)->create(['name' => 'Magi']);
    SeriesVolume::factory()->for($theirs)->create(['volume_number' => '3', 'volume_sort' => 3, 'isbns' => [MAGI_3]]);
    magiBook('マギ 1', ['user' => $other, 'serie_id' => $theirs->id]);
    $volume3 = magiBook('マギ 3', ['barcode' => MAGI_3]);

    app(DetectSeries::class)->handle($volume3, 'en');

    expect($volume3->refresh()->serie_id)->toBeNull();
});

it('shows the original-script title next to a Latin series name on the category page', function () {
    magiBook('マギ 1', ['serie_id' => $this->magi->id]);
    $berserk = Series::factory()->for($this->user)->create(['name' => 'ベルセルク', 'name_english' => 'Berserk']);
    magiBook('ベルセルク 1', ['serie_id' => $berserk->id]);
    $latin = Series::factory()->for($this->user)->create(['name' => 'Dune']);
    magiBook('Dune 1', ['serie_id' => $latin->id]);

    $this->actingAs($this->user)->get(route('library.category', $this->books))
        ->assertOk()
        ->assertSeeInOrder(['Berserk', 'data-original-name>ベルセルク<', 'Dune', 'Magi: The Labyrinth of Magic', 'data-original-name>マギ<'], false)
        ->assertDontSee('data-original-name>Dune', false);
});
