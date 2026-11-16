<?php

use App\Actions\UpdateCollectionObject;
use App\Models\ApiCallLog;
use App\Models\Artist;
use App\Models\Category;
use App\Models\CollectionObject;
use App\Models\Language;
use App\Models\Publisher;
use App\Models\ScanBatch;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
 * Japanese and Korean titles, publishers and authors are romanized when an item is
 * scanned (kana readings from the catalogue, kana / Hangul as written, Wikidata for
 * kanji names), and an admin can redo it for placeholders. Answers come from
 * tests/Fixtures/catalogue and tests/Fixtures/romanization, Korean ones inline.
 */

const KOREAN_ISBN = '9791163027003';

beforeEach(function () {
    $this->seed(CatalogueSeeder::class);
    $this->user = User::factory()->withApiKeys()->create();
    $this->books = Category::query()->where('slug', 'books')->sole();
    $this->manga = Category::query()->where('slug', 'manga')->sole();
    $this->ja = Language::query()->where('code', 'ja')->sole();
    $this->ko = Language::query()->where('code', 'ko')->sole();
    $this->actingAs($this->user);

    Http::fake(function (Request $request) {
        $url = $request->url();
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        if (str_contains($url, 'query.wikidata.org')) {
            return str_contains($query['query'] ?? '', '"白泉社"@ja')
                ? Http::response(file_get_contents(base_path('tests/Fixtures/romanization/wikidata-latin-hakusensha.json')))
                : Http::response(['head' => ['vars' => ['latin']], 'results' => ['bindings' => []]]);
        }

        if (str_contains($url, 'rakuten')) {
            return ($query['isbn'] ?? '') === '9784592135746'
                ? Http::response(file_get_contents(base_path('tests/Fixtures/catalogue/rakuten-isbn-9784592135746.json')))
                : Http::response(['count' => 0, 'Items' => []]);
        }

        if (str_contains($url, 'googleapis') && ($query['q'] ?? '') === 'isbn:'.KOREAN_ISBN) {
            return Http::response(['kind' => 'books#volumes', 'totalItems' => 1, 'items' => [['volumeInfo' => [
                'title' => '나 혼자만 레벨업 1', 'authors' => ['추공'], 'publisher' => '디앤씨미디어', 'language' => 'ko',
            ]]]]);
        }

        // NDL, Open Library, series lookups: nothing known.
        return Http::response('', 404);
    });
});

function romanizeScan(Language $language, string $mode = 'single'): void
{
    test()->post(route('scan.start'), ['category_id' => test()->books->id, 'sub_category_id' => test()->manga->id, 'language_id' => $language->id, 'mode' => $mode]);
}

/** A Berserk item still carrying placeholder names (the original copied), as before this feature. */
function placeholderBerserk(User $owner, array $object = [], array $artist = [], array $publisher = []): CollectionObject
{
    $item = CollectionObject::factory()->for($owner)->create($object + [
        'category_id' => test()->books->id, 'barcode' => '9784592135746', 'name' => 'ベルセルク 1', 'name_romanized' => 'ベルセルク 1',
        'publisher_id' => Publisher::query()->create($publisher + ['name' => '白泉社', 'name_romanized' => '白泉社'])->id,
    ]);
    $item->languages()->attach(test()->ja);
    $item->bookDetail()->create(['artist_id' => Artist::query()->create($artist + ['name' => '三浦 建太郎', 'name_romanized' => '三浦 建太郎'])->id]);

    return $item;
}

describe('on scan', function () {
    it('romanizes a Japanese title, author and publisher', function () {
        romanizeScan($this->ja);
        $this->post(route('scan.single.lookup'), ['barcode' => '9784592135746']);
        $this->post(route('scan.single.add'));

        $object = CollectionObject::sole();
        $artist = $object->bookDetail->artist;

        expect($object->name_romanized)->toBe('Beruseruku 1')       // from Rakuten's titleKana, with the volume
            ->and($object->name_romanized_auto)->toBeTrue()
            ->and($artist->name_romanized)->toBe('Miura Kentarō')   // from authorKana
            ->and($artist->name_romanized_auto)->toBeTrue()
            ->and($object->publisher->name_romanized)->toBe('Hakusensha') // kanji only: Wikidata
            ->and($object->publisher->name_romanized_auto)->toBeTrue()
            ->and(ApiCallLog::query()->where('provider', 'wikidata')->where('operation', 'romanize')->sole()->query)->toBe('白泉社');
    });

    it('romanizes a Korean title, author and publisher from their Hangul, in a batch', function () {
        romanizeScan($this->ko, 'batch');
        $batch = ScanBatch::sole();
        $this->post(route('scan.batches.items.store', $batch), ['barcode' => KOREAN_ISBN]);
        $this->post(route('scan.batches.commit', $batch));

        $object = CollectionObject::sole();

        expect($object->name_romanized)->toBe('Na honjaman rebereop 1')
            ->and($object->bookDetail->artist->name_romanized)->toBe('Chugong')
            ->and($object->publisher->name_romanized)->toBe('Diaenssimidieo');
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'wikidata'));
    });

    it('leaves publishers and authors that already exist alone, without asking Wikidata', function () {
        Publisher::query()->create(['name' => '白泉社', 'name_romanized' => 'Hakusensha Inc.']);
        Artist::query()->create(['name' => '三浦 建太郎', 'name_romanized' => 'Kentaro Miura']);

        romanizeScan($this->ja);
        $this->post(route('scan.single.lookup'), ['barcode' => '9784592135746']);
        $this->post(route('scan.single.add'));

        $object = CollectionObject::sole();
        expect($object->publisher->name_romanized)->toBe('Hakusensha Inc.')
            ->and($object->bookDetail->artist->name_romanized)->toBe('Kentaro Miura')
            ->and(Publisher::count())->toBe(1);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'wikidata'));
    });

    it('marks a romanization edited by hand as the user\'s own', function () {
        $object = placeholderBerserk($this->user, ['name_romanized' => 'Beruseruku 1', 'name_romanized_auto' => true]);
        $data = ['name' => $object->name, 'languages' => [$this->ja->id]];

        app(UpdateCollectionObject::class)->handle($object, $data + ['name_romanized' => 'Beruseruku 1']);
        expect($object->refresh()->name_romanized_auto)->toBeTrue();

        app(UpdateCollectionObject::class)->handle($object, $data + ['name_romanized' => 'Berserk 1']);
        expect($object->refresh()->name_romanized_auto)->toBeFalse()
            ->and($object->hasPlaceholderRomanization())->toBeFalse();
    });
});

describe('admin redo', function () {
    beforeEach(function () {
        $this->admin = User::factory()->admin()->withApiKeys()->create();
    });

    it('redoes the romanization of an item from its page, placeholders only', function () {
        $item = placeholderBerserk($this->admin);
        $handTyped = placeholderBerserk(User::factory()->withApiKeys()->create(), ['name_romanized' => 'Berserk Vol. 1']);

        $this->actingAs($this->admin)->get(route('objects.show', $item))->assertSee('data-romanize-object', false);

        $this->post(route('admin.objects.romanize', $item))
            ->assertRedirect(route('objects.show', $item))
            ->assertSessionHas('status', 'Romanizing the title, publisher and author again…');

        $item->refresh();
        expect($item->name_romanized)->toBe('Beruseruku 1')
            ->and($item->publisher->name_romanized)->toBe('Hakusensha')
            ->and($item->bookDetail->artist->name_romanized)->toBe('Miura Kentarō');

        // An admin may redo anyone's item; a hand-typed title stays.
        $this->post(route('admin.objects.romanize', $handTyped));
        expect($handTyped->refresh()->name_romanized)->toBe('Berserk Vol. 1');
    });

    it('keeps hand-typed publisher and author names', function () {
        $item = placeholderBerserk($this->admin, artist: ['name' => '三浦建太郎', 'name_romanized' => 'Kentaro Miura'], publisher: ['name' => '白泉社', 'name_romanized' => 'HAKUSENSHA']);

        $this->actingAs($this->admin)->post(route('admin.objects.romanize', $item));

        $item->refresh();
        expect($item->name_romanized)->toBe('Beruseruku 1')
            ->and($item->publisher->name_romanized)->toBe('HAKUSENSHA')
            ->and($item->bookDetail->artist->name_romanized)->toBe('Kentaro Miura');
    });

    it('redoes the romanization of ticked codes on a batch page', function () {
        $item = placeholderBerserk($this->user);
        $batch = new ScanBatch(['category_id' => $this->books->id, 'language_id' => $this->ja->id, 'ownership' => 'owned', 'status' => ScanBatch::DONE]);
        $batch->forceFill(['user_id' => $this->user->id])->save();
        $found = $batch->items()->create(['barcode' => '9784592135746', 'status' => 'added', 'object_id' => $item->id]);
        $missing = $batch->items()->create(['barcode' => '9784088725093', 'status' => 'not_found']);

        $this->actingAs($this->admin)->get(route('admin.scan-batches.show', $batch))
            ->assertSee('data-romanize-items', false)
            ->assertSee('data-romanize-item="'.$found->id.'"', false)
            ->assertDontSee('data-romanize-item="'.$missing->id.'"', false);

        $this->post(route('admin.scan-batches.items.romanize', $batch), ['items' => [$missing->id]])
            ->assertSessionHas('status', 'None of those codes reached the collection; nothing to romanize.');
        expect($item->refresh()->name_romanized)->toBe('ベルセルク 1');

        $this->post(route('admin.scan-batches.items.romanize', $batch), ['items' => [$found->id, $missing->id]])
            ->assertRedirect(route('admin.scan-batches.show', $batch))
            ->assertSessionHas('status', 'Romanizing 1 item again…');
        expect($item->refresh()->name_romanized)->toBe('Beruseruku 1');

        $this->post(route('admin.scan-batches.items.romanize', $batch), [])->assertSessionHasErrors('items');
    });

    it('is for admins only', function () {
        $item = placeholderBerserk($this->user);

        $this->get(route('objects.show', $item))->assertOk()->assertDontSee('data-romanize-object', false);
        $this->post(route('admin.objects.romanize', $item))->assertForbidden();
        expect($item->refresh()->name_romanized)->toBe('ベルセルク 1');
    });
});
