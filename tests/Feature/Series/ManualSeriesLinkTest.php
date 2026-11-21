<?php

use App\Models\Category;
use App\Models\CollectionObject;
use App\Models\Series;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;

/* Picking a book's series by hand on the item page (objects.series). */

beforeEach(function () {
    $this->seed(CatalogueSeeder::class);
    $this->user = User::factory()->create();
    $this->books = Category::query()->where('slug', 'books')->sole();
    $this->book = CollectionObject::factory()->for($this->user)->create(['category_id' => $this->books->id]);
    $this->actingAs($this->user);
});

it('shows the series picker on a book', function () {
    $this->get(route('objects.show', $this->book))->assertSee('data-series-picker', false)->assertSee('Choose a series');
});

it('does not show the picker on other categories', function () {
    $game = CollectionObject::factory()->for($this->user)->create(['category_id' => Category::query()->where('slug', 'games')->sole()->id]);

    $this->get(route('objects.show', $game))->assertDontSee('data-series-picker', false);
    $this->put(route('objects.series', $game), ['series_name' => 'X'])->assertNotFound();
});

it('puts a book without a series in one of the owner\'s series, with a volume', function () {
    $series = Series::factory()->for($this->user)->create(['name' => 'ベルセルク']);
    $this->book->update(['series_suggestion' => ['reference' => ['source' => 'title', 'externalId' => 'x', 'name' => 'X'], 'volume' => '1', 'source' => 'title', 'confidence' => 'low']]);

    $this->put(route('objects.series', $this->book), ['serie_id' => $series->id, 'series_volume' => '3'])
        ->assertRedirect(route('objects.show', $this->book))
        ->assertSessionHas('status', 'Added to the series ベルセルク.');

    $this->book->refresh();
    expect($this->book->serie_id)->toBe($series->id)
        ->and($this->book->bookDetail->volume)->toBe('3')
        ->and($this->book->series_suggestion)->toBeNull();
});

it('moves a wrongly linked book to another series', function () {
    $wrong = Series::factory()->for($this->user)->create(['name' => 'Wrong']);
    $right = Series::factory()->for($this->user)->create(['name' => 'Right']);
    $this->book->update(['serie_id' => $wrong->id]);

    $this->get(route('objects.show', $this->book))->assertSee('Change series');
    $this->put(route('objects.series', $this->book), ['serie_id' => $right->id]);

    expect($this->book->refresh()->serie_id)->toBe($right->id)
        ->and(Series::query()->whereKey($wrong->id)->exists())->toBeTrue();
});

it('creates a new series from a typed name, and reuses one of the same name', function () {
    $this->put(route('objects.series', $this->book), ['series_name' => 'Monster', 'series_volume' => '2']);

    $series = Series::query()->sole();
    expect($series->name)->toBe('Monster')
        ->and($series->user_id)->toBe($this->user->id)
        ->and($series->external_source)->toBeNull()
        ->and($this->book->refresh()->serie_id)->toBe($series->id);

    $other = CollectionObject::factory()->for($this->user)->create(['category_id' => $this->books->id]);
    $this->put(route('objects.series', $other), ['series_name' => ' monster ']);

    expect(Series::count())->toBe(1)
        ->and($other->refresh()->serie_id)->toBe($series->id);
});

it('takes a book out of its series when nothing is chosen', function () {
    $series = Series::factory()->for($this->user)->create();
    $this->book->update(['serie_id' => $series->id]);

    $this->put(route('objects.series', $this->book), ['serie_id' => '', 'series_name' => ''])
        ->assertSessionHas('status', 'Taken out of its series.');

    expect($this->book->refresh()->serie_id)->toBeNull()
        ->and(Series::count())->toBe(1);
});

it('refuses someone else\'s series and someone else\'s book', function () {
    $theirs = Series::factory()->for(User::factory())->create();

    $this->put(route('objects.series', $this->book), ['serie_id' => $theirs->id])->assertSessionHasErrors('serie_id');
    expect($this->book->refresh()->serie_id)->toBeNull();

    $this->actingAs(User::factory()->create())
        ->put(route('objects.series', $this->book), ['series_name' => 'X'])->assertNotFound();
});

it('rejects a volume that is not a number', function () {
    $this->put(route('objects.series', $this->book), ['series_name' => 'X', 'series_volume' => 'abc'])->assertSessionHasErrors('series_volume');
    expect(Series::count())->toBe(0);
});
