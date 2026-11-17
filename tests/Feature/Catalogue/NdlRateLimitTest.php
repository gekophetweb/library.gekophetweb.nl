<?php

use App\Models\Category;
use App\Models\User;
use App\Services\Catalogue\NdlCatalogueSearch;
use App\Services\NdlRateLimiter;
use App\Support\Catalogue\CatalogueContext;
use App\Support\Catalogue\CatalogueUnavailable;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    $this->seed(CatalogueSeeder::class);
    config(['series.providers.ndl.per_minute' => 2]);

    Http::preventStrayRequests();
    Http::fake(['ndlsearch.ndl.go.jp/*' => Http::response('<rss><channel></channel></rss>')]);

    $this->context = new CatalogueContext(
        User::factory()->create(),
        Category::query()->where('slug', 'books')->sole(),
        'ja',
    );
});

function ndlCalls(): int
{
    return Http::recorded(fn (Request $request) => str_contains($request->url(), 'ndlsearch.ndl.go.jp'))->count();
}

it('skips NDL on a page once the app has made two calls this minute', function () {
    $ndl = app(NdlCatalogueSearch::class);

    $ndl->search('ベルセルク', $this->context, 10); // title + creator: both slots
    expect($ndl->search('ワンピース', $this->context, 10))->toBe([])
        ->and(ndlCalls())->toBe(2);

    $this->travel(61)->seconds();
    $ndl->search('ワンピース', $this->context, 10);
    expect(ndlCalls())->toBe(4);
});

it('reports NDL as unavailable to a strict lookup over the budget', function () {
    $ndl = app(NdlCatalogueSearch::class);
    $ndl->search('ベルセルク', $this->context, 10);

    expect(fn () => $ndl->lookup('9784592135746', $this->context))->toThrow(CatalogueUnavailable::class)
        ->and(ndlCalls())->toBe(2);
});

it('shares one budget between all users', function () {
    $ndl = app(NdlCatalogueSearch::class);
    $ndl->search('ベルセルク', $this->context, 10);

    $other = new CatalogueContext(User::factory()->create(), $this->context->category, 'ja');
    $ndl->search('ワンピース', $other, 10);

    expect(ndlCalls())->toBe(2);
});

it('waits for a free slot inside a queued job instead of skipping', function () {
    Sleep::fake(syncWithCarbon: true);

    dispatch(fn () => app(NdlCatalogueSearch::class)->search('ベルセルク', test()->context, 10)
        + app(NdlCatalogueSearch::class)->search('ワンピース', test()->context, 10));

    expect(ndlCalls())->toBe(4);
    Sleep::assertSleptTimes(1);

    // Back outside the job, an over-budget call is skipped again rather than waiting.
    expect(app(NdlRateLimiter::class)->acquire())->toBeFalse();
    Sleep::assertSleptTimes(1);
});

it('has no limit when set to 0', function () {
    config(['series.providers.ndl.per_minute' => 0]);
    $ndl = app(NdlCatalogueSearch::class);

    foreach (['a', 'b', 'c'] as $term) {
        $ndl->search($term, $this->context, 10);
    }

    expect(ndlCalls())->toBe(6);
});
