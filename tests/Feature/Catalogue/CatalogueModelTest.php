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
use Illuminate\Database\QueryException;

it('seeds the category hierarchy as data', function () {
    $this->seed(CatalogueSeeder::class);

    $tree = Category::query()->whereNull('parent_id')->with('children')->orderBy('id')->get()
        ->mapWithKeys(fn (Category $category) => [$category->name => $category->children->pluck('name')->all()])
        ->all();

    expect($tree)->toBe([
        'Books' => ['Manga', 'Novels'],
        'Music' => ['CD', 'Vinyl', 'Tape', 'Minidisc'],
        'Games' => [],
    ]);
    expect(Category::query()->where('name', 'Music')->sole()->kind)->toBe(CategoryKind::Music)
        ->and(Category::query()->where('name', 'Manga')->sole()->kind)->toBe(CategoryKind::Books);
});

it('seeds languages and platforms, and seeding twice adds nothing', function () {
    $this->seed(CatalogueSeeder::class);
    $this->seed(CatalogueSeeder::class);

    expect(Language::query()->where('code', 'ja')->sole()->name)->toBe('Japanese')
        ->and(Category::count())->toBe(9)
        ->and(Platform::query()->where('name', 'PC')->exists())->toBeTrue();
});

it('lets a sub-category resolve its top-level category', function () {
    $books = Category::factory()->create(['name' => 'Books', 'kind' => CategoryKind::Books]);
    $manga = Category::factory()->childOf($books)->create(['name' => 'Manga']);

    expect($manga->topLevel()->is($books))->toBeTrue()
        ->and($books->topLevel()->is($books))->toBeTrue();
});

it('scopes objects and series to their owner', function () {
    [$ann, $bob] = User::factory()->count(2)->create();
    CollectionObject::factory()->for($ann)->count(2)->create();
    CollectionObject::factory()->for($bob)->create();
    Series::factory()->for($ann)->create();

    expect(CollectionObject::query()->ownedBy($ann)->count())->toBe(2)
        ->and(CollectionObject::query()->ownedBy($bob)->count())->toBe(1)
        ->and(Series::query()->ownedBy($bob)->count())->toBe(0)
        ->and($ann->objects()->count())->toBe(2);
});

it('links an object to languages, genres, a publisher and its book details', function () {
    $object = CollectionObject::factory()->book()->create();
    $genre = Genre::factory()->for($object->category->topLevel())->create(['name' => 'Seinen']);
    $artist = Artist::factory()->create(['name' => '三浦建太郎']);

    $object->languages()->attach(Language::factory()->create(['code' => 'ja']));
    $object->genres()->attach($genre);
    BookDetail::query()->create(['object_id' => $object->id, 'artist_id' => $artist->id, 'volume' => '3.5', 'volume_sort' => 3.5]);

    $object->refresh();
    expect($object->languages->pluck('code')->all())->toBe(['ja'])
        ->and($object->genres->pluck('name')->all())->toBe(['Seinen'])
        ->and($object->bookDetail->artist->name)->toBe('三浦建太郎')
        ->and((float) $object->bookDetail->volume_sort)->toBe(3.5)
        ->and($object->publisher)->not->toBeNull();
});

it('tells whether a genre fits an object category', function () {
    $books = Category::factory()->create(['kind' => CategoryKind::Books]);
    $manga = Category::factory()->childOf($books)->create();
    $games = Category::factory()->create(['kind' => CategoryKind::Games]);

    expect(Genre::factory()->for($books)->create()->fitsCategory($manga))->toBeTrue()
        ->and(Genre::factory()->for($games)->create()->fitsCategory($manga))->toBeFalse();
});

it('keeps a special edition as its own series volume', function () {
    $series = Series::factory()->create();

    SeriesVolume::factory()->for($series)->create(['volume_number' => '41', 'volume_sort' => 41]);
    SeriesVolume::factory()->for($series)->create(['volume_number' => '41', 'volume_sort' => 41, 'edition' => '特装版']);

    expect($series->volumes()->count())->toBe(2);
    expect(fn () => SeriesVolume::factory()->for($series)->create(['volume_number' => '41', 'volume_sort' => 41]))
        ->toThrow(QueryException::class);
});

it('stores the ISBNs of a series volume and side-story numbers', function () {
    $volume = SeriesVolume::factory()->create([
        'volume_number' => '3.5',
        'volume_sort' => 3.5,
        'isbns' => ['9784592135746'],
    ]);

    expect($volume->refresh()->isbns)->toBe(['9784592135746'])
        ->and((float) $volume->volume_sort)->toBe(3.5);
});

it('allows the same external series id for two users but not twice for one', function () {
    [$ann, $bob] = User::factory()->count(2)->create();
    $ids = ['external_source' => 'ndl', 'external_id' => 'ベルセルク|三浦建太郎|白泉社'];

    Series::factory()->for($ann)->create($ids);
    Series::factory()->for($bob)->create($ids);

    expect(fn () => Series::factory()->for($ann)->create($ids))->toThrow(QueryException::class);
});

it('allows a barcode once per user', function () {
    [$ann, $bob] = User::factory()->count(2)->create();
    CollectionObject::factory()->for($ann)->create(['barcode' => '9784592135746']);
    CollectionObject::factory()->for($bob)->create(['barcode' => '9784592135746']);
    CollectionObject::factory()->for($ann)->count(2)->create(['barcode' => null]);

    expect(fn () => CollectionObject::factory()->for($ann)->create(['barcode' => '9784592135746']))
        ->toThrow(QueryException::class);
});

it('deletes a user\'s objects and series with the user', function () {
    $user = User::factory()->create();
    CollectionObject::factory()->for($user)->create();
    Series::factory()->for($user)->create();

    $user->delete();

    expect(CollectionObject::count())->toBe(0)->and(Series::count())->toBe(0);
});

it('shows each user\'s total items on the admin user list', function () {
    $admin = User::factory()->admin()->create();
    $ann = User::factory()->create();
    CollectionObject::factory()->for($ann)->count(3)->create();

    $this->actingAs($admin)->get(route('admin.users.index'))
        ->assertSee('Total items')
        ->assertSee('data-user="'.$ann->id.'"', false)
        ->assertSeeInOrder(['data-user="'.$ann->id.'"', 'data-total="3"'], false)
        ->assertDontSee($ann->objects()->first()->name);
});
