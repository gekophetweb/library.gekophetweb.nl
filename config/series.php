<?php

use App\Services\Series\NdlSeriesLookup;
use App\Services\Series\OpenLibraryWikidataSeriesLookup;

return [

    /*
    |--------------------------------------------------------------------------
    | Provider chains per scan language
    |--------------------------------------------------------------------------
    |
    | SeriesLookupManager tries these in order and stops at the first match with
    | high confidence. Keys are ISO 639-1 codes; `default` covers every other
    | language. Slots for later fallbacks that need the user's own API keys:
    | Rakuten `seriesName` (ja), Google Books `seriesInfo` (default), Hardcover.
    |
    */

    'chains' => [
        'ja' => [NdlSeriesLookup::class],
        'default' => [OpenLibraryWikidataSeriesLookup::class],
    ],

    /*
    | NDL Search and Wikidata ask clients to identify themselves.
    */
    'user_agent' => env('SERIES_USER_AGENT', 'library.gekophetweb.nl/1.0 (+'.env('APP_URL', 'https://library.gekophetweb.nl').')'),

    'timeout' => (int) env('SERIES_HTTP_TIMEOUT', 15),

    'cache' => [
        // A batch scan of 40 volumes of one series should cost one volumes() call.
        'volumes_ttl' => (int) env('SERIES_VOLUMES_CACHE_TTL', 60 * 60 * 24 * 7),
        'resolve_ttl' => (int) env('SERIES_RESOLVE_CACHE_TTL', 60 * 60 * 24 * 30),
    ],

    'providers' => [
        'ndl' => [
            'endpoint' => env('SERIES_NDL_ENDPOINT', 'https://ndlsearch.ndl.go.jp/api/opensearch'),
            // NDL returns at most 200 records per page.
            'page_size' => 200,
            'max_pages' => 5,
        ],
        'openlibrary' => [
            'endpoint' => env('SERIES_OPENLIBRARY_ENDPOINT', 'https://openlibrary.org'),
        ],
        'wikidata' => [
            'endpoint' => env('SERIES_WIKIDATA_ENDPOINT', 'https://query.wikidata.org/sparql'),
        ],
    ],

];
