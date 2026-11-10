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
];
