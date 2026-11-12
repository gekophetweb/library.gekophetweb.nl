<?php

use App\Actions\Series\DetectSeries;
use App\Enums\ApiKeyProvider;
use App\Jobs\DetectObjectSeries;
use App\Jobs\SyncSeriesVolumes;
use App\Models\Category;
use App\Models\CollectionObject;
use App\Models\Language;
use App\Models\ScanBatch;
use App\Models\Series;
use App\Models\SeriesVolume;
use App\Models\User;
use App\Models\UserApiKey;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

/*
 * Series detection wired into scanning, plus the volume sync (plan
 * series-detection-from-isbn phases 4–6). NDL answers from the recorded fixtures in
 * tests/Fixtures/series; Rakuten from the catalogue fixture or inline items.
 */

const BERSERK_1 = '9784592135746';
const BERSERK_2 = '9784592135753';
const ONE_PIECE_1 = '9784088725093';

beforeEach(function () {
    $this->seed(CatalogueSeeder::class);
    $this->user = User::factory()->create();
    UserApiKey::factory()->for($this->user)->create(['provider' => ApiKeyProvider::Rakuten, 'key' => 'app-123']);
    $this->books = Category::query()->where('slug', 'books')->sole();
    $this->manga = Category::query()->where('slug', 'manga')->sole();
    $this->ja = Language::query()->where('code', 'ja')->sole();

    // Catalogue first (returns null for anything else so the series router answers).
    Http::fake(function (Request $request) {
        if (! str_contains($request->url(), 'rakuten') && ! str_contains($request->url(), 'googleapis')) {
            return null;
        }

        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return match ($query['isbn'] ?? null) {
            BERSERK_1 => Http::response(file_get_contents(base_path('tests/Fixtures/catalogue/rakuten-isbn-9784592135746.json'))),
            BERSERK_2 => Http::response(['count' => 1, 'Items' => [['title' => 'ベルセルク 2', 'author' => '三浦 建太郎', 'publisherName' => '白泉社', 'isbn' => BERSERK_2, 'salesDate' => '1990年12月']]]),
            ONE_PIECE_1 => Http::response(['count' => 1, 'Items' => [['title' => 'ONE PIECE 1', 'author' => '尾田 栄一郎', 'publisherName' => '集英社', 'isbn' => ONE_PIECE_1]]]),
            default => Http::response(['count' => 0, 'Items' => [], 'totalItems' => 0]),
        };
    });
    fakeSeriesApis();

    $this->actingAs($this->user);
});

function scanOne(string $barcode, array $form = [])
{
    test()->post(route('scan.start'), ['category_id' => test()->books->id, 'sub_category_id' => test()->manga->id, 'language_id' => test()->ja->id, 'mode' => 'single']);
    test()->post(route('scan.single.lookup'), ['barcode' => $barcode]);

    return $form;
}

describe('single scan', function () {
    it('pre-fills the detected series and volume, editable', function () {
        scanOne(BERSERK_1);

        $this->get(route('scan.single'))
            ->assertSee('value="ベルセルク"', false)
            ->assertSee('id="series_volume" name="series_volume" maxlength="20" value="1"', false)
            ->assertSee('data-series-source="ndl"', false);
    });

    it('links the detected series with its source and fetches every volume', function () {
        scanOne(BERSERK_1);
        $this->post(route('scan.single.add'), ['series_name' => 'ベルセルク', 'series_volume' => '1']);

        $series = Series::sole();
        $object = CollectionObject::sole();
        expect($object->serie_id)->toBe($series->id)
            ->and($object->bookDetail->volume)->toBe('1')
            ->and($series->external_source)->toBe('ndl')
            ->and($series->external_id)->toBe('ベルセルク|三浦建太郎|白泉社')
            ->and($series->artist->name)->toBe('三浦建太郎')
            ->and($series->publisher->name)->toBe('白泉社')
            ->and($series->synced_at)->not->toBeNull()
            ->and($series->volumes()->count())->toBe(46)
            ->and($series->volumes()->where('edition', '!=', '')->count())->toBe(3);

        $this->get(route('library.series', $series))
            ->assertSee('You own 1 of 46 volumes')
            ->assertSee('data-volume="1" data-state="owned"', false)
            ->assertSee('Fetch volumes')
            ->assertSee('Volume list from NDL');
    });

    it('puts a re-scanned volume in the same series without a second sync', function () {
        scanOne(BERSERK_1);
        $this->post(route('scan.single.add'), ['series_name' => 'ベルセルク', 'series_volume' => '1']);
        $synced = Series::sole()->synced_at;
        $this->travel(1)->minute();

        scanOne(BERSERK_2);
        $this->post(route('scan.single.add'), ['series_name' => 'ベルセルク', 'series_volume' => '2']);

        expect(Series::count())->toBe(1)
            ->and(CollectionObject::query()->pluck('serie_id')->unique()->all())->toBe([Series::sole()->id])
            ->and(Series::sole()->synced_at->equalTo($synced))->toBeTrue();
    });

    it('uses a renamed series as a plain series of that name, and none when emptied', function () {
        scanOne(BERSERK_1);
        $this->post(route('scan.single.add'), ['series_name' => 'Berserk (my copy)', 'series_volume' => '1']);

        expect(Series::sole()->only(['name', 'external_source']))->toBe(['name' => 'Berserk (my copy)', 'external_source' => null]);

        scanOne(BERSERK_2);
        $this->post(route('scan.single.add'), ['series_name' => '']);
        expect(CollectionObject::query()->where('barcode', BERSERK_2)->sole()->serie_id)->toBeNull();
    });

    it('rejects a volume that is not a number', function () {
        scanOne(BERSERK_1);

        $this->post(route('scan.single.add'), ['series_name' => 'ベルセルク', 'series_volume' => 'first'])->assertSessionHasErrors('series_volume');
        expect(CollectionObject::count())->toBe(0);
    });

    it('never reuses another user\'s series with the same source', function () {
        $theirs = Series::factory()->create(['external_source' => 'ndl', 'external_id' => 'ベルセルク|三浦建太郎|白泉社']);

        scanOne(BERSERK_1);
        $this->post(route('scan.single.add'), ['series_name' => 'ベルセルク', 'series_volume' => '1']);

        expect(CollectionObject::sole()->serie_id)->not->toBe($theirs->id)
            ->and(Series::query()->ownedBy($this->user)->count())->toBe(1);
    });

    it('links a detected series to a hand-made one of the same name', function () {
        $mine = Series::factory()->for($this->user)->create(['name' => 'ベルセルク']);

        scanOne(BERSERK_1);
        $this->post(route('scan.single.add'), ['series_name' => 'ベルセルク', 'series_volume' => '1']);

        expect(Series::count())->toBe(1)
            ->and($mine->refresh()->external_source)->toBe('ndl')
            ->and($mine->volumes()->count())->toBe(46);
    });
});

describe('batch scan', function () {
    it('applies a high-confidence series and leaves a suggestion for a low one', function () {
        $this->post(route('scan.start'), ['category_id' => $this->books->id, 'sub_category_id' => $this->manga->id, 'language_id' => $this->ja->id, 'mode' => 'batch']);
        $batch = ScanBatch::sole();
        $this->post(route('scan.batches.items.store', $batch), ['barcode' => BERSERK_1]);
        $this->post(route('scan.batches.items.store', $batch), ['barcode' => ONE_PIECE_1]);
        $this->post(route('scan.batches.commit', $batch));

        $berserk = CollectionObject::query()->where('barcode', BERSERK_1)->sole();
        $onePiece = CollectionObject::query()->where('barcode', ONE_PIECE_1)->sole();

        expect($berserk->serie->name)->toBe('ベルセルク')
            ->and($onePiece->serie_id)->toBeNull()
            ->and($onePiece->series_suggestion['reference']['name'])->toBe('ONE PIECE')
            ->and($onePiece->series_suggestion['source'])->toBe('title');

        $this->get(route('objects.show', $onePiece))->assertSee('data-series-suggestion', false)->assertSee('volume 1 of');
        $this->post(route('objects.series-suggestion.accept', $onePiece))->assertRedirect(route('objects.show', $onePiece));

        $onePiece->refresh();
        expect($onePiece->serie->name)->toBe('ONE PIECE')
            ->and($onePiece->series_suggestion)->toBeNull()
            ->and($onePiece->serie->isSyncable())->toBeFalse();
    });

    it('dismisses a suggestion, and keeps suggestions private', function () {
        $object = CollectionObject::factory()->for($this->user)->create([
            'category_id' => $this->books->id,
            'series_suggestion' => ['reference' => ['source' => 'title', 'externalId' => 'x', 'name' => 'X'], 'volume' => '1', 'source' => 'title', 'confidence' => 'low'],
        ]);

        $this->actingAs(User::factory()->create())->post(route('objects.series-suggestion.accept', $object))->assertNotFound();

        $this->actingAs($this->user)->delete(route('objects.series-suggestion.dismiss', $object))->assertRedirect();
        expect($object->refresh()->series_suggestion)->toBeNull()
            ->and(Series::count())->toBe(0);
    });
});

describe('sync', function () {
    it('adds new volumes, merges ISBNs and never deletes a volume', function () {
        $series = Series::factory()->for($this->user)->create(['external_source' => 'ndl', 'external_id' => 'ベルセルク|三浦建太郎|白泉社']);
        SeriesVolume::factory()->for($series)->create(['volume_number' => '1', 'volume_sort' => 1, 'isbns' => ['9784592999999']]);
        SeriesVolume::factory()->for($series)->create(['volume_number' => '99', 'volume_sort' => 99]);

        SyncSeriesVolumes::dispatchSync($series);

        $volume1 = $series->volumes()->where('volume_sort', 1)->where('edition', '')->sole();
        expect($series->volumes()->count())->toBe(47)
            ->and($volume1->isbns)->toContain('9784592999999')
            ->and($volume1->isbns)->toContain(BERSERK_1)
            ->and($series->volumes()->where('volume_sort', 99)->exists())->toBeTrue();
    });

    it('changes nothing when the source returns no volumes', function () {
        $series = Series::factory()->for($this->user)->create(['external_source' => 'ndl', 'external_id' => '存在しない|誰か|どこか']);

        SyncSeriesVolumes::dispatchSync($series);

        expect($series->refresh()->synced_at)->toBeNull()
            ->and($series->volumes()->count())->toBe(0);
    });

    it('syncs from the series page and the API, only for the owner and linked series', function () {
        Queue::fake();
        $linked = Series::factory()->for($this->user)->create(['external_source' => 'wikidata', 'external_id' => 'Q131612237']);
        $plain = Series::factory()->for($this->user)->create();

        $this->post(route('library.series.sync', $linked))->assertRedirect(route('library.series', $linked));
        $this->post(route('library.series.sync', $plain))->assertStatus(409);
        $this->actingAs(User::factory()->create())->post(route('library.series.sync', $linked))->assertNotFound();

        Sanctum::actingAs($this->user);
        $this->postJson("/api/v1/series/{$linked->id}/sync")->assertAccepted()->assertJsonPath('data.status', 'queued');
        $this->postJson("/api/v1/series/{$plain->id}/sync")->assertUnprocessable();
        $this->getJson("/api/v1/series/{$linked->id}")->assertJsonPath('data.syncable', true)->assertJsonPath('data.external_source', 'wikidata');

        // The job is unique per series: the API call right after the page's is not queued twice.
        Queue::assertPushed(SyncSeriesVolumes::class, 1);
    });

    it('re-syncs due series weekly', function () {
        Queue::fake();
        $due = Series::factory()->for($this->user)->create(['external_source' => 'ndl', 'external_id' => 'a|b|c', 'synced_at' => now()->subDays(8)]);
        Series::factory()->for($this->user)->create(['external_source' => 'ndl', 'external_id' => 'd|e|f', 'synced_at' => now()->subDay()]);
        Series::factory()->for($this->user)->create(['synced_at' => null]);

        Artisan::call('series:sync');

        Queue::assertPushed(SyncSeriesVolumes::class, 1);
        Queue::assertPushed(SyncSeriesVolumes::class, fn (SyncSeriesVolumes $job) => $job->series->is($due));

        Artisan::call('schedule:list');
        expect(Artisan::output())->toContain('series:sync');
    });
});

it('detects the series of a book added to the wishlist from search', function () {
    Queue::fake([DetectObjectSeries::class]);

    $this->post(route('wishlist.store'), [
        'category_id' => $this->books->id, 'sub_category_id' => $this->manga->id, 'language_id' => $this->ja->id,
        'barcode' => BERSERK_2, 'name' => 'ベルセルク 2',
    ]);

    Queue::assertPushed(DetectObjectSeries::class, fn (DetectObjectSeries $job) => $job->language === 'ja');

    // The faked queue swallows dispatchSync too: run what the job runs.
    (new DetectObjectSeries(CollectionObject::sole(), 'ja'))->handle(app(DetectSeries::class));
    expect(CollectionObject::sole()->serie->name)->toBe('ベルセルク')
        ->and(CollectionObject::sole()->bookDetail->volume)->toBe('2');
});
