<?php

use App\Jobs\SyncSeriesVolumes;
use App\Models\BookDetail;
use App\Models\Category;
use App\Models\CollectionObject;
use App\Models\Series;
use App\Models\User;
use App\Services\Series\NdlSeriesLookup;
use App\Support\Isbn;
use App\Support\Series\SeriesVolumeData;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Support\Facades\Http;

/*
 * 鋼の錬金術師 as NDL lists it (from production lookups): one record for the whole set
 * (volume "1", only volume 1's ISBN, 2002-2010) that also answers ISBN searches for
 * later volumes, and a publisher renamed from エニックス to スクウェア・エニックス
 * from volume 5 on.
 */

function fmaRecord(string $volume, string $isbn, string $publisher = 'スクウェア・エニックス', string $issued = '2008.4'): string
{
    return "<item><category>図書</category><dc:title>鋼の錬金術師</dc:title><dc:creator>荒川, 弘</dc:creator><dcndl:volume>{$volume}</dcndl:volume>"
        ."<dc:publisher>{$publisher}</dc:publisher><dcterms:issued>{$issued}</dcterms:issued><dc:identifier xsi:type=\"dcndl:ISBN\">{$isbn}</dc:identifier></item>";
}

function fmaRss(string ...$items): string
{
    return '<rss xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcndl="http://ndl.go.jp/dcndl/terms/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:openSearch="http://a9.com/-/spec/opensearchrss/1.0/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="2.0"><channel>'
        .'<openSearch:totalResults>'.count($items).'</openSearch:totalResults>'.implode('', $items).'</channel></rss>';
}

function fmaSetRecord(): string
{
    return fmaRecord('1', '4757506201', issued: '2002-2010');
}

beforeEach(fn () => withoutSeriesFixtures());

it('takes the volume from the record that carries the ISBN, not the whole-set record', function () {
    Http::fake(['ndlsearch.ndl.go.jp/*' => Http::response(fmaRss(fmaSetRecord(), fmaRecord('23', '978-4-7575-2602-0')))]);

    $match = app(NdlSeriesLookup::class)->resolve(Isbn::fromString('9784757526020'), 'ja');

    expect((string) $match->volume)->toBe('23')
        ->and($match->series->name)->toBe('鋼の錬金術師');
});

it('keeps the volumes published after the publisher was renamed', function () {
    Http::fake(['ndlsearch.ndl.go.jp/*' => Http::response(fmaRss(
        fmaSetRecord(),
        fmaRecord('1', '4757506201', 'エニックス', '2002.1'),
        fmaRecord('4', '4757508557', 'エニックス', '2003.2'),
        fmaRecord('5', '9784757509665'),
        fmaRecord('27', '9784757530546'),
        fmaRecord('1', '9999999999', '白泉社'),
    ))]);

    $series = Series::factory()->make(['external_source' => 'ndl', 'external_id' => '鋼の錬金術師|荒川弘|エニックス'])->reference();
    $volumes = app(NdlSeriesLookup::class)->volumes($series);

    expect($volumes->map(fn (SeriesVolumeData $v) => (string) $v->volume)->all())->toBe(['1', '4', '5', '27'])
        ->and($volumes[0]->isbns)->toHaveCount(1);
});

it('puts right an item volume that the scan got wrong when the series syncs', function () {
    $this->seed(CatalogueSeeder::class);
    $user = User::factory()->create();
    $series = Series::factory()->for($user)->create(['external_source' => 'ndl', 'external_id' => '鋼の錬金術師|荒川弘|エニックス']);
    $books = Category::query()->where('slug', 'books')->sole();
    $wrong = CollectionObject::factory()->for($user)->create(['category_id' => $books->id, 'serie_id' => $series->id, 'barcode' => '9784757526020']);
    BookDetail::query()->create(['object_id' => $wrong->id, 'volume' => '1', 'volume_sort' => 1]);
    $unknown = CollectionObject::factory()->for($user)->create(['category_id' => $books->id, 'serie_id' => $series->id, 'barcode' => '9784000000000']);
    BookDetail::query()->create(['object_id' => $unknown->id, 'volume' => '7', 'volume_sort' => 7]);

    Http::fake(['ndlsearch.ndl.go.jp/*' => Http::response(fmaRss(fmaSetRecord(), fmaRecord('23', '978-4-7575-2602-0')))]);
    SyncSeriesVolumes::dispatchSync($series);

    expect($wrong->bookDetail()->first()->volume)->toBe('23')
        ->and((float) $wrong->bookDetail()->first()->volume_sort)->toBe(23.0)
        ->and($unknown->bookDetail()->first()->volume)->toBe('7');
});
