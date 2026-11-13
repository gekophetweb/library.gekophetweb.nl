<?php

use App\Enums\SearchScope;
use App\Models\Artist;
use App\Models\BookDetail;
use App\Models\Category;
use App\Models\CollectionObject;
use App\Models\MusicDetail;
use App\Models\Series;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(CatalogueSeeder::class);
    $this->user = User::factory()->create();
    $this->books = Category::query()->where('slug', 'books')->sole();
    $this->music = Category::query()->where('slug', 'music')->sole();
});

function book(array $attributes = [], ?Artist $author = null, ?User $user = null): CollectionObject
{
    $object = CollectionObject::factory()->for($user ?? test()->user)->create($attributes + ['category_id' => test()->books->id]);
    BookDetail::query()->create(['object_id' => $object->id, 'artist_id' => $author?->id]);

    return $object;
}

function search(string $q, string $scope = 'all')
{
    return test()->actingAs(test()->user)->get(route('search.index', ['q' => $q, 'scope' => $scope]));
}

it('labels the scopes and escapes LIKE wildcards', function () {
    expect(SearchScope::All->label())->toBe('Everything')
        ->and(SearchScope::Title->label())->toBe('Title')
        ->and(SearchScope::Creator->label())->toBe('Author / artist')
        ->and(CollectionObject::escapeLike('50%_a!b'))->toBe('50!%!_a!!b')
        ->and(CollectionObject::normalizeSearchTerm("  ベルセルク \u{3000}  1 "))->toBe('ベルセルク 1')
        ->and(CollectionObject::normalizeSearchTerm('   '))->toBeNull();
});

it('matches titles on each of the three name columns', function (string $column) {
    $object = book(array_merge(['name' => '海辺のカフカ', 'name_romanized' => 'Umibe no Kafuka'], [$column => 'Kafka on the Shore']));

    search('ON THE SHORE', 'title')->assertOk()->assertSee(route('objects.show', $object));
})->with(['name', 'name_romanized', 'name_english']);

it('finds items by title, series title and exact barcode', function () {
    $series = Series::factory()->for($this->user)->create(['name' => 'ベルセルク', 'name_romanized' => 'Berserk']);
    book(['name' => 'Volume One', 'serie_id' => $series->id]);
    book(['name' => 'Kafka on the Shore', 'name_english' => 'Kafka on the Shore']);
    book(['name' => 'Barcode item', 'barcode' => '9784592135746']);

    search('berserk', 'title')->assertSee('Volume One')->assertDontSee('Kafka on the Shore')->assertSee('ベルセルク');
    search('kafka', 'title')->assertSee('Kafka on the Shore');
    search('978-4-592-13574-6', 'title')->assertSee('Barcode item');
    search('9784592135746', 'creator')->assertDontSee('Barcode item');
});

it('finds items by a book author, a music artist and the series artist', function () {
    $miura = Artist::factory()->create(['name' => '三浦 建太郎', 'name_romanized' => 'Kentaro Miura']);
    $band = Artist::factory()->create(['name' => 'Boris']);
    book(['name' => 'Authored book'], $miura);

    $record = CollectionObject::factory()->for($this->user)->create(['category_id' => $this->music->id, 'name' => 'Pink']);
    MusicDetail::query()->create(['object_id' => $record->id, 'artist_id' => $band->id]);

    $series = Series::factory()->for($this->user)->create(['name' => 'Series by Miura', 'artist_id' => $miura->id]);
    book(['name' => 'Series volume', 'serie_id' => $series->id]);

    search('miura', 'creator')->assertSee('Authored book')->assertSee('Series volume')->assertDontSee('Pink')
        ->assertSee(route('library.artist', $miura));
    search('boris', 'creator')->assertSee('Pink');
    search('miura', 'title')->assertDontSee('Authored book')->assertSee('Series by Miura');
});

it('returns the union under All', function () {
    $author = Artist::factory()->create(['name' => 'Haruki Murakami']);
    book(['name' => 'Norwegian Wood'], $author);
    book(['name' => 'Murakami: a biography']);

    search('murakami')->assertSee('Norwegian Wood')->assertSee('Murakami: a biography');
});

it('treats % and _ as literal characters', function () {
    book(['name' => 'Anything']);
    book(['name' => '100% Orange']);

    search('%')->assertSee('100% Orange')->assertDontSee('Anything');
    search('_')->assertSee('data-empty', false);
});

it('finds a spaced stored name from an unspaced term', function () {
    $miura = Artist::factory()->create(['name' => '三浦 建太郎']);
    book(['name' => 'ベルセルク 1'], $miura);

    search('三浦建太郎', 'creator')->assertSee('ベルセルク 1')->assertSee('三浦 建太郎');
});

it('never shows another user\'s items, series or artists', function () {
    $other = User::factory()->create();
    $theirs = Artist::factory()->create(['name' => 'Secret Author']);
    book(['name' => 'Secret Book'], $theirs, $other);
    Series::factory()->for($other)->create(['name' => 'Secret Series']);

    search('secret')->assertSee('data-empty', false)
        ->assertDontSee('Secret Book')->assertDontSee('Secret Series')->assertDontSee('Secret Author');
});

it('lists only artists reachable through the user\'s own library', function () {
    Artist::factory()->create(['name' => 'Lonely Artist']);

    search('lonely', 'creator')->assertDontSee('Lonely Artist');
});

it('shows the empty page for a blank term without querying the collection', function () {
    $this->actingAs($this->user);
    DB::enableQueryLog();

    $this->get(route('search.index', ['q' => '   ']))->assertOk()->assertSee('Type a title');

    expect(collect(DB::getQueryLog())->pluck('query')->filter(fn ($sql) => str_contains($sql, '"objects"')))->toBeEmpty();
});

it('rejects an unknown scope', function () {
    $this->actingAs($this->user)->get(route('search.index', ['q' => 'x', 'scope' => 'publisher']))->assertSessionHasErrors('scope');
});

it('shows an artist page with the user\'s items only, and 404s on unreachable artists', function () {
    $miura = Artist::factory()->create(['name' => '三浦 建太郎']);
    book(['name' => 'Mine'], $miura);
    book(['name' => 'Not mine'], $miura, User::factory()->create());

    $this->actingAs($this->user)->get(route('library.artist', $miura))->assertOk()->assertSee('Mine')->assertDontSee('Not mine');
    $this->get(route('library.artist', Artist::factory()->create()))->assertNotFound();
});

describe('the API', function () {
    beforeEach(fn () => Sanctum::actingAs($this->user));

    it('searches objects by title and creator, narrowed by search_by', function () {
        $author = Artist::factory()->create(['name' => 'Haruki Murakami']);
        $byAuthor = book(['name' => 'Norwegian Wood'], $author);
        $byTitle = book(['name' => 'Murakami: a biography']);

        $ids = fn (string $query) => collect($this->getJson('/api/v1/objects?'.$query)->assertOk()->json('data'))->pluck('id')->sort()->values()->all();

        expect($ids('search=murakami'))->toBe(collect([$byAuthor->id, $byTitle->id])->sort()->values()->all())
            ->and($ids('search=murakami&search_by=title'))->toBe([$byTitle->id])
            ->and($ids('search=murakami&search_by=creator'))->toBe([$byAuthor->id]);

        $this->getJson('/api/v1/objects?search=x&search_by=genre')->assertUnprocessable();
    });

    it('returns grouped search results scoped to the user', function () {
        $author = Artist::factory()->create(['name' => 'Kentaro Miura']);
        book(['name' => 'Berserk 1'], $author);
        Series::factory()->for($this->user)->create(['name' => 'Berserk']);
        Series::factory()->create(['name' => 'Berserk elsewhere']);

        $this->getJson('/api/v1/search?q=berserk')
            ->assertOk()
            ->assertJsonCount(1, 'data.objects')
            ->assertJsonPath('data.objects.0.name', 'Berserk 1')
            ->assertJsonCount(1, 'data.series')
            ->assertJsonPath('data.series.0.name', 'Berserk')
            ->assertJsonPath('meta.total_objects', 1);

        $this->getJson('/api/v1/search?q=miura&scope=creator')
            ->assertJsonPath('data.artists.0.name', 'Kentaro Miura')
            ->assertJsonCount(0, 'data.series');

        $this->getJson('/api/v1/search?q=')->assertOk()->assertJsonCount(0, 'data.objects');
    });
});
