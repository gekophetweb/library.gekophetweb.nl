<?php

use App\Enums\CategoryKind;
use App\Models\Artist;
use App\Models\BookDetail;
use App\Models\Category;
use App\Models\CollectionObject;
use App\Models\Genre;
use App\Models\Language;
use App\Models\Platform;
use App\Models\Series;
use App\Models\SeriesVolume;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;

beforeEach(function () {
    $this->seed(CatalogueSeeder::class);
    $this->user = User::factory()->create();
    $this->books = Category::query()->where('slug', 'books')->sole();
    $this->manga = Category::query()->where('slug', 'manga')->sole();
});

function item(array $attributes = []): CollectionObject
{
    return CollectionObject::factory()->for(test()->user)->create($attributes + [
        'category_id' => test()->books->id,
        'sub_category_id' => test()->manga->id,
    ]);
}

it('shows the categories with the user\'s counts on the dashboard', function () {
    item();
    item();
    CollectionObject::factory()->create(['category_id' => $this->books->id]); // someone else's

    $this->actingAs($this->user)->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder(['Books', '2 owned', 'Music', '0 owned', 'Games', '0 owned']);
});

it('lists series and standalone items on a category page', function () {
    $berserk = Series::factory()->for($this->user)->create(['name' => 'ベルセルク']);
    item(['serie_id' => $berserk->id, 'name' => 'ベルセルク 1']);
    item(['serie_id' => $berserk->id, 'name' => 'ベルセルク 2']);
    item(['name' => 'Kafka on the Shore']);

    $this->actingAs($this->user)->get(route('library.category', $this->books))
        ->assertOk()
        ->assertSee('ベルセルク')
        ->assertSee('2 owned')
        ->assertSee('Kafka on the Shore')
        ->assertDontSee('ベルセルク 1');
});

it('filters a category page by sub-category', function () {
    $novels = Category::query()->where('slug', 'novels')->sole();
    item(['name' => 'A Manga']);
    item(['name' => 'A Novel', 'sub_category_id' => $novels->id]);

    $this->actingAs($this->user)->get(route('library.category', [$this->books, 'sub' => $novels->id]))
        ->assertSee('A Novel')
        ->assertDontSee('A Manga');
});

it('shows a series with owned and missing volumes', function () {
    $series = Series::factory()->for($this->user)->create(['name' => 'ベルセルク']);
    SeriesVolume::factory()->for($series)->create(['volume_number' => '1', 'volume_sort' => 1, 'isbns' => ['9784592135746']]);
    SeriesVolume::factory()->for($series)->create(['volume_number' => '2', 'volume_sort' => 2, 'isbns' => ['9784592135753']]);
    SeriesVolume::factory()->for($series)->create(['volume_number' => '41', 'volume_sort' => 41, 'edition' => '特装版']);
    item(['serie_id' => $series->id, 'barcode' => '9784592135746', 'name' => 'ベルセルク 1']);

    $this->actingAs($this->user)->get(route('library.series', $series))
        ->assertOk()
        ->assertSee('You own 1 of 3 volumes')
        ->assertSee('data-volume="1" data-state="owned"', false)
        ->assertSee('data-volume="2" data-state="missing"', false)
        ->assertSee('特装版');
});

it('counts an item as owning a volume by its volume number when the barcode is unknown', function () {
    $series = Series::factory()->for($this->user)->create();
    SeriesVolume::factory()->for($series)->create(['volume_number' => '3', 'volume_sort' => 3]);
    $object = item(['serie_id' => $series->id, 'barcode' => null]);
    BookDetail::query()->create(['object_id' => $object->id, 'volume' => '3', 'volume_sort' => 3]);

    $this->actingAs($this->user)->get(route('library.series', $series))
        ->assertSee('data-volume="3" data-state="owned"', false);
});

it('shows an item with its details', function () {
    $artist = Artist::factory()->create(['name' => '三浦建太郎']);
    $object = item(['name' => 'ベルセルク 1']);
    BookDetail::query()->create(['object_id' => $object->id, 'artist_id' => $artist->id, 'volume' => '1', 'volume_sort' => 1]);
    $object->languages()->attach(Language::query()->where('code', 'ja')->sole());

    $this->actingAs($this->user)->get(route('objects.show', $object))
        ->assertOk()
        ->assertSee('ベルセルク 1')
        ->assertSee('三浦建太郎')
        ->assertSee('Japanese')
        ->assertSee('Manga');
});

it('404s on another user\'s item, series and their pages', function () {
    $other = CollectionObject::factory()->create();
    $series = Series::factory()->create();

    $this->actingAs($this->user)->get(route('objects.show', $other))->assertNotFound();
    $this->put(route('objects.update', $other), ['name' => 'x'])->assertNotFound();
    $this->get(route('library.series', $series))->assertNotFound();
});

it('updates an item', function () {
    $object = item();
    $ja = Language::query()->where('code', 'ja')->sole();
    $genre = Genre::factory()->for($this->books)->create(['name' => 'Seinen']);
    $series = Series::factory()->for($this->user)->create();
    $artist = Artist::factory()->create();

    $this->actingAs($this->user)->put(route('objects.update', $object), [
        'name' => 'ベルセルク 3',
        'name_romanized' => 'Berserk 3',
        'name_english' => 'Berserk Volume 3',
        'sub_category_id' => $this->manga->id,
        'languages' => [$ja->id],
        'genres' => [$genre->id],
        'release_date' => '1991-07-01',
        'serie_id' => $series->id,
        'volume' => '３',
        'artist_id' => $artist->id,
    ])->assertRedirect(route('objects.show', $object));

    $object->refresh();
    expect($object->name_romanized)->toBe('Berserk 3')
        ->and($object->languages->pluck('code')->all())->toBe(['ja'])
        ->and($object->genres->pluck('name')->all())->toBe(['Seinen'])
        ->and($object->serie_id)->toBe($series->id)
        ->and($object->bookDetail->volume)->toBe('3')
        ->and((float) $object->bookDetail->volume_sort)->toBe(3.0)
        ->and($object->bookDetail->artist_id)->toBe($artist->id);
});

it('requires the name, romanized name and at least one language', function () {
    $object = item();

    $this->actingAs($this->user)->put(route('objects.update', $object), ['name' => '', 'name_romanized' => '', 'languages' => []])
        ->assertSessionHasErrors(['name', 'name_romanized', 'languages']);
});

it('rejects a genre of another category, a foreign sub-category and someone else\'s series', function () {
    $object = item();
    $gameGenre = Genre::factory()->for(Category::query()->where('slug', 'games')->sole())->create();
    $vinyl = Category::query()->where('slug', 'vinyl')->sole();
    $foreignSeries = Series::factory()->create();

    $this->actingAs($this->user)->put(route('objects.update', $object), [
        'name' => 'x',
        'name_romanized' => 'x',
        'languages' => [Language::first()->id],
        'genres' => [$gameGenre->id],
        'sub_category_id' => $vinyl->id,
        'serie_id' => $foreignSeries->id,
    ])->assertSessionHasErrors(['genres.0', 'sub_category_id', 'serie_id']);
});

it('rejects a volume that is not a number', function () {
    $object = item();

    $this->actingAs($this->user)->put(route('objects.update', $object), [
        'name' => 'x', 'name_romanized' => 'x', 'languages' => [Language::first()->id], 'volume' => 'twelve',
    ])->assertSessionHasErrors('volume');
});

it('keeps category-specific fields to their category', function () {
    $games = Category::query()->where('slug', 'games')->sole();
    expect($games->kind)->toBe(CategoryKind::Games);
    $game = item(['category_id' => $games->id, 'sub_category_id' => null]);
    $platform = Platform::query()->where('name', 'PC')->sole();

    $this->actingAs($this->user)->put(route('objects.update', $game), [
        'name' => 'Elden Ring', 'name_romanized' => 'Elden Ring', 'languages' => [Language::first()->id],
        'platform_id' => $platform->id, 'volume' => '2',
    ])->assertRedirect();

    $game->refresh();
    expect($game->gameDetail->platform->name)->toBe('PC')
        ->and($game->bookDetail)->toBeNull();
});
