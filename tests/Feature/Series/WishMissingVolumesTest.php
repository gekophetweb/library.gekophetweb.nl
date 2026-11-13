<?php

use App\Enums\OwnershipStatus;
use App\Models\Category;
use App\Models\CollectionObject;
use App\Models\Language;
use App\Models\LibraryShare;
use App\Models\Series;
use App\Models\SeriesVolume;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;

beforeEach(function () {
    $this->seed(CatalogueSeeder::class);
    $this->user = User::factory()->create();
    $this->manga = Category::query()->where('slug', 'manga')->sole();
    $this->ja = Language::query()->where('code', 'ja')->sole();
    $this->series = Series::factory()->for($this->user)->create(['name' => 'ベルセルク', 'name_romanized' => 'Berserk']);
    $this->v1 = SeriesVolume::factory()->for($this->series)->create(['volume_number' => '1', 'volume_sort' => 1, 'isbns' => ['9784592135746']]);
    $this->v2 = SeriesVolume::factory()->for($this->series)->create(['volume_number' => '2', 'volume_sort' => 2, 'isbns' => ['9784592135753'], 'release_date' => '1990-12']);
    $this->v3 = SeriesVolume::factory()->for($this->series)->create(['volume_number' => '3', 'volume_sort' => 3, 'isbns' => []]);

    $owned = CollectionObject::factory()->for($this->user)->create([
        'category_id' => $this->manga->parent_id ?? $this->manga->id,
        'sub_category_id' => $this->manga->parent_id ? $this->manga->id : null,
        'serie_id' => $this->series->id,
        'barcode' => '9784592135746',
    ]);
    $owned->languages()->attach($this->ja);
    $this->owned = $owned;

    $this->actingAs($this->user);
});

it('adds one missing volume to the wishlist, in the series and with its ISBN', function () {
    $this->post(route('library.series.volumes.wish', [$this->series, $this->v2]))
        ->assertRedirect(route('library.series', $this->series))
        ->assertSessionHas('status', 'Added ベルセルク 2 to your wishlist.');

    $item = CollectionObject::query()->where('barcode', '9784592135753')->sole();
    expect($item->ownership)->toBe(OwnershipStatus::Wishlist)
        ->and($item->serie_id)->toBe($this->series->id)
        ->and($item->category_id)->toBe($this->owned->category_id)
        ->and($item->sub_category_id)->toBe($this->owned->sub_category_id)
        ->and($item->name_romanized)->toBe('Berserk 2')
        ->and($item->release_date->toDateString())->toBe('1990-12-01')
        ->and($item->bookDetail->volume)->toBe('2')
        ->and($item->languages->pluck('id')->all())->toBe([$this->ja->id]);

    $this->get(route('library.series', $this->series))->assertSee('data-volume="2" data-state="wanted"', false);
});

it('adds every missing volume at once, a volume without ISBN included', function () {
    $this->post(route('library.series.wish-missing', $this->series))
        ->assertSessionHas('status', 'Added 2 volumes to your wishlist.');

    expect(CollectionObject::query()->wishlisted()->count())->toBe(2);
    $this->get(route('library.series', $this->series))
        ->assertSee('data-volume="2" data-state="wanted"', false)
        ->assertSee('data-volume="3" data-state="wanted"', false);
});

it('never duplicates an owned or wanted volume', function () {
    $this->post(route('library.series.volumes.wish', [$this->series, $this->v1]))
        ->assertSessionHas('status', 'Nothing to add: those volumes are already in your library.');
    $this->post(route('library.series.wish-missing', $this->series));
    $this->post(route('library.series.wish-missing', $this->series))
        ->assertSessionHas('status', 'Nothing to add: those volumes are already in your library.');

    expect(CollectionObject::query()->count())->toBe(3);
});

it('skips a volume whose ISBN the user already has outside the series', function () {
    CollectionObject::factory()->for($this->user)->create(['category_id' => $this->owned->category_id, 'barcode' => '9784592135753']);

    $this->post(route('library.series.volumes.wish', [$this->series, $this->v2]))
        ->assertSessionHas('status', 'Nothing to add: those volumes are already in your library.');
});

it('shows the buttons to the owner only', function () {
    $this->get(route('library.series', $this->series))
        ->assertSee('data-wish-missing', false)
        ->assertSee('data-wish-volume', false);

    $share = LibraryShare::factory()->for($this->user)->forSeries($this->series)->create();
    auth()->logout();
    $this->get(route('share.series', [$share->token, $this->series]))
        ->assertOk()
        ->assertDontSee('data-wish-volume', false);
});

it('refuses another user\'s series and a volume of another series', function () {
    $other = Series::factory()->create();
    $foreign = SeriesVolume::factory()->for($other)->create();

    $this->post(route('library.series.wish-missing', $other))->assertNotFound();
    $this->post(route('library.series.volumes.wish', [$this->series, $foreign]))->assertNotFound();
});

it('hides the buttons when the series has no item to copy a category from', function () {
    $empty = Series::factory()->for($this->user)->create();
    SeriesVolume::factory()->for($empty)->count(2)->create();

    $this->get(route('library.series', $empty))->assertDontSee('data-wish-missing', false);
    $this->post(route('library.series.wish-missing', $empty))->assertStatus(409);
});
