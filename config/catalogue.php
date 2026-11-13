<?php

/*
|--------------------------------------------------------------------------
| Catalogue lookups ("what is this item?", guidelines §5)
|--------------------------------------------------------------------------
|
| Endpoints only: keys are the user's own (settings → API keys). Music and Games have
| no provider yet — leave those slots empty until one is chosen.
|
*/

return [
    'timeout' => (int) env('CATALOGUE_TIMEOUT', 10),

    'providers' => [
        'rakuten' => [
            'endpoint' => 'https://app.rakuten.co.jp/services/api/BooksBook/Search/20170404',
        ],
        'google_books' => [
            'endpoint' => 'https://www.googleapis.com/books/v1/volumes',
        ],
    ],

    // Books providers in the order they are asked, by scan language; results are merged.
    'books_order' => [
        'ja' => ['rakuten', 'google_books'],
        'default' => ['google_books', 'rakuten'],
    ],

    // Searching by title / author for books not owned yet (plan search-catalogue-not-owned).
    // NDL and Open Library need no key, so the search works without any set-up. Their
    // endpoints are shared with the series lookup (config/series.php).
    'search' => [
        'books_order' => [
            'ja' => ['rakuten', 'ndl'],
            'default' => ['google_books', 'openlibrary'],
        ],
        'limit' => (int) env('CATALOGUE_SEARCH_LIMIT', 20),
        // Short: the user waits for the answer.
        'timeout' => (int) env('CATALOGUE_SEARCH_TIMEOUT', 6),
        'cache_ttl' => (int) env('CATALOGUE_SEARCH_CACHE_TTL', 60 * 60),
    ],
];
