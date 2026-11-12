<?php

use App\Models\BookDetail;
use App\Models\Category;
use App\Models\CollectionObject;
use App\Models\LibraryShare;
use App\Models\Series;
use App\Models\SeriesVolume;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;

beforeEach(function () {
    $this->seed(CatalogueSeeder::class);
    $this->owner = User::factory()->create(['name' => 'Jaap']);
    $this->books = Category::query()->where('slug', 'books')->sole();
    $this->music = Category::query()->where('slug', 'music')->sole();
    $this->manga = Category::query()->where('slug', 'manga')->sole();

    $this->series = Series::factory()->for($this->owner)->create(['name' => 'ベルセルク']);
    SeriesVolume::factory()->for($this->series)->create(['volume_number' => '1', 'volume_sort' => 1, 'isbns' => ['9784592135746']]);
    $this->volume = CollectionObject::factory()->for($this->owner)->create([
        'category_id' => $this->books->id, 'sub_category_id' => $this->manga->id,
        'serie_id' => $this->series->id, 'barcode' => '9784592135746', 'name' => 'ベルセルク 1',
    ]);
    BookDetail::query()->create(['object_id' => $this->volume->id, 'volume' => '1', 'volume_sort' => 1]);
    $this->novel = CollectionObject::factory()->for($this->owner)->create(['category_id' => $this->books->id, 'name' => 'Kafka on the Shore']);
    $this->record = CollectionObject::factory()->for($this->owner)->create(['category_id' => $this->music->id, 'name' => 'Pink']);
    $this->wanted = CollectionObject::factory()->for($this->owner)->wishlisted()->create(['category_id' => $this->books->id, 'name' => 'Wanted Book']);
});

function shareUrl(LibraryShare $share, string $page = 'index', mixed $model = null): string
{
    return route('share.'.$page, array_filter(['shareToken' => $share->token, 'category' => $page === 'category' ? $model : null, 'series' => $page === 'series' ? $model : null, 'object' => $page === 'object' ? $model : null]));
}

it('lets a guest with a library link browse the index, a category, a series and an item', function () {
    $share = LibraryShare::factory()->for($this->owner)->create();

    $this->get(shareUrl($share))->assertOk()->assertSee('Jaap’s collection')->assertSee('read only')
        ->assertSee(shareUrl($share, 'category', $this->books));
    $this->get(shareUrl($share, 'category', $this->books))->assertOk()
        ->assertSee('ベルセルク')->assertSee('Kafka on the Shore')->assertSee('Wanted Book')
        ->assertSee(shareUrl($share, 'series', $this->series));
    $this->get(shareUrl($share, 'series', $this->series))->assertOk()
        ->assertSee('1 of 1 volumes owned')->assertDontSee('You own')
        ->assertSee(shareUrl($share, 'object', $this->volume));
    $this->get(shareUrl($share, 'object', $this->volume))->assertOk()->assertSee('ベルセルク 1')->assertSee('9784592135746');

    $this->assertGuest();
});

it('404s on unknown, expired and revoked tokens alike', function () {
    $expired = LibraryShare::factory()->for($this->owner)->expired()->create();
    $revoked = LibraryShare::factory()->for($this->owner)->create();
    $revokedUrl = shareUrl($revoked);
    $revoked->delete();

    $this->get(route('share.index', ['shareToken' => str_repeat('a', 32)]))->assertNotFound();
    $this->get(route('share.index', ['shareToken' => 'short']))->assertNotFound();
    $this->get(shareUrl($expired))->assertNotFound();
    $this->get($revokedUrl)->assertNotFound();
});

it('keeps a category link to its category', function () {
    $share = LibraryShare::factory()->for($this->owner)->forCategory($this->books)->create();

    $this->get(shareUrl($share))->assertRedirect(shareUrl($share, 'category', $this->books));
    $this->get(shareUrl($share, 'category', $this->books))->assertOk()->assertDontSee('Pink');
    $this->get(shareUrl($share, 'series', $this->series))->assertOk();
    $this->get(shareUrl($share, 'object', $this->novel))->assertOk();

    $this->get(shareUrl($share, 'category', $this->music))->assertNotFound();
    $this->get(shareUrl($share, 'object', $this->record))->assertNotFound();
});

it('keeps a series link to its series', function () {
    $share = LibraryShare::factory()->forSeries($this->series)->create();

    $this->get(shareUrl($share))->assertRedirect(shareUrl($share, 'series', $this->series));
    $this->get(shareUrl($share, 'series', $this->series))->assertOk()->assertDontSee('>Library<', false);
    $this->get(shareUrl($share, 'object', $this->volume))->assertOk();

    $this->get(shareUrl($share, 'object', $this->novel))->assertNotFound();
    $this->get(shareUrl($share, 'category', $this->books))->assertNotFound();
});

it('never reaches another user\'s items or series, even within scope', function () {
    $share = LibraryShare::factory()->for($this->owner)->create();
    $other = User::factory()->create();
    $theirs = CollectionObject::factory()->for($other)->create(['category_id' => $this->books->id, 'name' => 'Not Jaap\'s']);
    $theirSeries = Series::factory()->for($other)->create();

    $this->get(shareUrl($share, 'object', $theirs))->assertNotFound();
    $this->get(shareUrl($share, 'series', $theirSeries))->assertNotFound();
    $this->get(shareUrl($share, 'category', $this->books))->assertDontSee('Not Jaap\'s');
});

it('renders no form, no write target and carries noindex', function () {
    $share = LibraryShare::factory()->for($this->owner)->create();

    foreach ([shareUrl($share), shareUrl($share, 'category', $this->books), shareUrl($share, 'series', $this->series), shareUrl($share, 'object', $this->volume)] as $url) {
        $this->get($url)
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertDontSee('<form', false)
            ->assertDontSee('method="POST"', false)
            ->assertDontSee('Mark as owned')
            ->assertDontSee('Move to wishlist');
    }
});

it('shows a signed-in owner the read-only view too, not their own controls', function () {
    $share = LibraryShare::factory()->for($this->owner)->create();

    $this->actingAs($this->owner)->get(shareUrl($share, 'object', $this->volume))
        ->assertOk()
        ->assertDontSee('<form', false)
        ->assertDontSee('Log out');
});

it('offers no write route under the share prefix', function () {
    $share = LibraryShare::factory()->for($this->owner)->create();

    $this->put(shareUrl($share, 'object', $this->volume), ['name' => 'x'])->assertMethodNotAllowed();
    expect($this->volume->refresh()->name)->toBe('ベルセルク 1');
});
