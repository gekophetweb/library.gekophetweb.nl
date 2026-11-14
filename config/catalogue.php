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
            // Rakuten moved its API to openapi.rakuten.co.jp in 2026; the old app.rakuten.co.jp host is retired.
            'endpoint' => 'https://openapi.rakuten.co.jp/services/api/BooksBook/Search/20170404',
            // This server's public IP address, shown in the key guide: Rakuten only answers
            // calls from the IP addresses a user allows for their app.
            'server_ip' => env('RAKUTEN_ALLOWED_IP'),
        ],
        'google_books' => [
            'endpoint' => 'https://www.googleapis.com/books/v1/volumes',
        ],
        // Endpoint shared with the search and series code (config/series.php). NDL often
        // needs 5-15 s for an ISBN, so it gets more time than the others.
        'ndl' => [
            'timeout' => (int) env('CATALOGUE_NDL_TIMEOUT', 30),
        ],
    ],

    // Books providers in the order they are asked, by scan language; results are merged
    // (earlier providers win). Each chain has a keyless catalogue (NDL, Open Library), so a
    // scan finds books without any key: keyless Google Books has a daily quota of zero.
    'books_order' => [
        'ja' => ['rakuten', 'ndl', 'google_books'],
        'default' => ['google_books', 'openlibrary', 'rakuten'],
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
