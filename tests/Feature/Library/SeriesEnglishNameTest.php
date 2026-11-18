<?php

use App\Actions\Series\LinkSeries;
use App\Models\Artist;
use App\Models\BookDetail;
use App\Models\Category;
use App\Models\CollectionObject;
use App\Models\LibraryShare;
use App\Models\Series;
use App\Models\User;
use App\Support\Series\SeriesReference;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(CatalogueSeeder::class);
    $this->user = User::factory()->create();
    $this->series = Series::factory()->for($this->user)->create(['name' => 'ベルセルク', 'name_romanized' => 'Beruseruku']);
});

describe('editing', function () {
    it('lets the owner set an English name', function () {
        $this->actingAs($this->user)
            ->patch(route('library.series.update', $this->series), ['name_english' => 'Berserk'])
            ->assertRedirect(route('library.series', $this->series))
            ->assertSessionHas('status');

        expect($this->series->fresh()->name_english)->toBe('Berserk');
    });

    it('clears the English name when blank', function () {
        $this->series->update(['name_english' => 'Berserk']);

        $this->actingAs($this->user)->patch(route('library.series.update', $this->series), ['name_english' => '   ']);

        expect($this->series->fresh()->name_english)->toBeNull();
    });

    it('rejects a name over 255 characters', function () {
        $this->actingAs($this->user)
            ->patch(route('library.series.update', $this->series), ['name_english' => str_repeat('a', 256)])
            ->assertSessionHasErrors('name_english');

        expect($this->series->fresh()->name_english)->toBeNull();
    });

    it('404s for another user\'s series', function () {
        $other = Series::factory()->create(['name' => 'ワンピース']);

        $this->actingAs($this->user)
            ->patch(route('library.series.update', $other), ['name_english' => 'One Piece'])
            ->assertNotFound();

        expect($other->fresh()->name_english)->toBeNull();
    });
});

describe('series page', function () {
    it('shows the English name as the title with the original underneath', function () {
        $this->series->update(['name_english' => 'Berserk']);

        $this->actingAs($this->user)->get(route('library.series', $this->series))
            ->assertSee('<title>Berserk ·', false)
            ->assertSeeInOrder(['<h1', 'Berserk', 'data-original-name', 'ベルセルク', 'Beruseruku'], false);
    });

    it('shows the original title alone without an English name', function () {
        $this->actingAs($this->user)->get(route('library.series', $this->series))
            ->assertSeeInOrder(['<h1', 'ベルセルク', 'Beruseruku'], false)
            ->assertDontSee('data-original-name', false);
    });

    it('shows the edit form to the owner only', function () {
        $this->series->update(['name_english' => 'Berserk']);

        $this->actingAs($this->user)->get(route('library.series', $this->series))
            ->assertSee('name="name_english"', false);

        auth()->logout();
        $share = LibraryShare::factory()->for($this->user)->create();

        $this->get(route('share.series', ['shareToken' => $share->token, 'series' => $this->series]))
            ->assertOk()
            ->assertSee('Berserk')
            ->assertDontSee('name="name_english"', false);
    });
});

it('sorts series by display name', function () {
    $this->series->update(['name_english' => 'Berserk']);
    $akira = Series::factory()->for($this->user)->create(['name' => 'Akira']);
    $kana = Series::factory()->for($this->user)->create(['name' => 'あ']);

    expect(Series::query()->orderByDisplayName()->pluck('id')->all())
        ->toBe([$akira->id, $this->series->id, $kana->id]);
});

describe('elsewhere', function () {
    beforeEach(function () {
        $this->books = Category::query()->where('slug', 'books')->sole();
        $this->artist = Artist::factory()->create(['name' => '三浦建太郎']);
        $this->series->update(['name_english' => 'Berserk', 'artist_id' => $this->artist->id]);
        $this->object = CollectionObject::factory()->for($this->user)->create([
            'category_id' => $this->books->id, 'serie_id' => $this->series->id, 'name' => 'Volume one',
        ]);
        BookDetail::query()->create(['object_id' => $this->object->id, 'artist_id' => $this->artist->id, 'volume' => '1', 'volume_sort' => 1]);
        $this->actingAs($this->user);
    });

    it('shows the English name on the category shelf, artist page and settings share picker', function () {
        $this->get(route('library.category', $this->books))
            ->assertSee('<span class="font-medium">Berserk</span>', false)
            ->assertDontSee('>ベルセルク<', false);

        $this->get(route('library.artist', $this->artist))
            ->assertSee('<span class="font-medium">Berserk</span>', false);

        $this->get(route('settings.shares'))
            ->assertSee('>Berserk</option>', false);
    });

    it('shows the English name on an item page and in its series select', function () {
        $this->get(route('objects.show', $this->object))
            ->assertSee('Berserk · vol. 1')
            ->assertSee('>Berserk</option>', false)
            ->assertDontSee('ベルセルク');
    });

    it('finds a series by its English name in library search', function () {
        $this->get(route('search.index', ['q' => 'berser', 'scope' => 'all']))
            ->assertSee('<span class="font-medium">Berserk</span>', false);
    });
});

it('labels a series share link with the English name', function () {
    $this->series->update(['name_english' => 'Berserk']);
    $share = LibraryShare::factory()->for($this->user)->create();
    $share->shareable()->associate($this->series)->save();

    expect($share->fresh()->scopeLabel())->toBe('Series: Berserk');
});

describe('matching', function () {
    beforeEach(function () {
        Queue::fake();
        $this->series->update(['name_english' => 'Berserk', 'external_source' => 'ndl', 'external_id' => 'ベルセルク|三浦建太郎|白泉社']);
        $this->object = CollectionObject::factory()->for($this->user)->create(['category_id' => Category::query()->where('slug', 'books')->sole()->id]);
    });

    it('does not put a detected series into the owned one through its English name', function () {
        $linked = app(LinkSeries::class)->toReference($this->object, new SeriesReference('hardcover', '123', 'Berserk'), null);

        expect($linked->id)->not->toBe($this->series->id)
            ->and($this->series->fresh()->external_source)->toBe('ndl');
    });

    it('still matches a typed series name against the English name', function () {
        $linked = app(LinkSeries::class)->byName($this->object, 'berserk', null);

        expect($linked->id)->toBe($this->series->id);
    });
});
