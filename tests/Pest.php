<?php

use App\Models\SsoProvider;
use App\Services\Auth\SsoDriverFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Two\User;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => Http::preventStrayRequests())
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Series API fixtures
|--------------------------------------------------------------------------
|
| Real responses recorded once from NDL Search, Open Library and Wikidata
| (2026-11-09) and replayed here — the test suite makes no live calls.
|
*/

function seriesFixture(string $name): string
{
    return file_get_contents(__DIR__.'/Fixtures/series/'.$name);
}

/**
 * Http::fake() stacks: the router from fakeSeriesApis() would still answer first. Call
 * this before a test-specific Http::fake() to start from a clean client.
 */
function withoutSeriesFixtures(): void
{
    Http::swap(new Factory(app('events')));
    Http::preventStrayRequests();
}

function emptyNdlResponse(): string
{
    return <<<'XML'
        <rss xmlns:openSearch="http://a9.com/-/spec/opensearchrss/1.0/" version="2.0">
          <channel><openSearch:totalResults>0</openSearch:totalResults></channel>
        </rss>
        XML;
}

/**
 * Routes NDL / Open Library / Wikidata requests to the recorded fixtures. Unknown ISBNs
 * get an empty NDL result, a 404 from Open Library and no Wikidata bindings.
 */
function fakeSeriesApis(): void
{
    Http::fake(function (Request $request) {
        $url = $request->url();
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        if (str_contains($url, 'ndlsearch.ndl.go.jp')) {
            if (isset($query['isbn'])) {
                $file = __DIR__."/Fixtures/series/ndl-isbn-{$query['isbn']}.xml";

                return Factory::response(is_file($file) ? file_get_contents($file) : emptyNdlResponse());
            }

            return Factory::response(($query['title'] ?? null) === 'ベルセルク'
                ? seriesFixture('ndl-volumes-berserk.xml')
                : emptyNdlResponse());
        }

        if (str_contains($url, 'openlibrary.org/isbn/')) {
            $isbn = basename((string) parse_url($url, PHP_URL_PATH), '.json');
            $file = __DIR__."/Fixtures/series/openlibrary-isbn-{$isbn}.json";

            return is_file($file)
                ? Factory::response(file_get_contents($file))
                : Factory::response(['error' => 'notfound'], 404);
        }

        if (str_contains($url, 'query.wikidata.org')) {
            $sparql = $query['query'] ?? '';

            return Factory::response(match (true) {
                str_contains($sparql, 'wdt:P648 "OL24593432W"') => seriesFixture('wikidata-book-OL24593432W.json'),
                str_contains($sparql, 'ps:P179 wd:Q131612237') => seriesFixture('wikidata-volumes-Q131612237.json'),
                default => json_encode(['head' => ['vars' => []], 'results' => ['bindings' => []]]),
            });
        }

        // Hand-written fixtures (tests/Fixtures/series/README.md).
        if (str_contains($url, 'googleapis.com/books/v1/volumes')) {
            $isbn = str_replace('isbn:', '', $query['q'] ?? '');
            $file = __DIR__."/Fixtures/series/googlebooks-isbn-{$isbn}.json";

            return Factory::response(is_file($file) ? file_get_contents($file) : ['kind' => 'books#volumes', 'totalItems' => 0]);
        }

        if (str_contains($url, 'googleapis.com/books/v1/series/membership/get')) {
            $page = ($query['page_token'] ?? null) === 'page2' ? 'page2' : 'page1';
            $file = __DIR__."/Fixtures/series/googlebooks-membership-{$query['series_id']}-{$page}.json";

            return Factory::response(is_file($file) ? file_get_contents($file) : ['kind' => 'books#series#membership']);
        }

        if (str_contains($url, 'googleapis.com/books/v1/series/get')) {
            $file = __DIR__."/Fixtures/series/googlebooks-series-{$query['series_id']}.json";

            return Factory::response(is_file($file) ? file_get_contents($file) : ['kind' => 'books#series']);
        }

        if (str_contains($url, 'api.hardcover.app')) {
            $variables = $request['variables'] ?? [];
            $file = isset($variables['isbn'])
                ? __DIR__."/Fixtures/series/hardcover-isbn-{$variables['isbn']}.json"
                : __DIR__.'/Fixtures/series/hardcover-volumes-'.($variables['id'] ?? '').'.json';

            return Factory::response(is_file($file) ? file_get_contents($file) : ['data' => ['editions' => [], 'series_by_pk' => null]]);
        }

        return Factory::response('Unexpected request: '.$url, 500);
    });
}

/*
|--------------------------------------------------------------------------
| SSO
|--------------------------------------------------------------------------
|
| Swaps the SSO driver factory for one whose provider "redirects" to a fake identity
| provider and returns $user from the callback, so no OAuth round trip is needed.
|
*/

function fakeSsoUser(string $id, ?string $email, array $raw = [], string $name = 'Sso Person'): User
{
    return (new User)
        ->setRaw($raw + ['sub' => $id, 'email' => $email])
        ->map(['id' => $id, 'email' => $email, 'name' => $name, 'nickname' => null]);
}

function fakeSso(User $user): void
{
    app()->instance(SsoDriverFactory::class, new class($user) implements SsoDriverFactory
    {
        public function __construct(private User $user) {}

        public function for(SsoProvider $provider): Provider
        {
            return new class($this->user) implements Provider
            {
                public function __construct(private User $user) {}

                public function redirect()
                {
                    return redirect()->away('https://idp.test/authorize');
                }

                public function user()
                {
                    return $this->user;
                }
            };
        }
    });
}
