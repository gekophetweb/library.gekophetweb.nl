<?php

use App\Models\Category;
use App\Models\User;
use App\Services\Catalogue\OpenLibraryCatalogueSearch;
use App\Support\Catalogue\CatalogueContext;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
 * Open Library's search returns the work, which groups every translation under the
 * original title. Seen in production (batch 20): the English VIZ edition of
 * Fullmetal Alchemist 4 came back as "鋼の錬金術師 4" and joined the owner's Japanese
 * series. The edition record of the scanned ISBN carries the title of that edition.
 */

function openLibraryFixture(string $name): string
{
    return file_get_contents(base_path('tests/Fixtures/catalogue/'.$name));
}

beforeEach(function () {
    $this->seed(CatalogueSeeder::class);
    $this->context = new CatalogueContext(User::factory()->create(), Category::query()->where('slug', 'books')->sole(), 'en');
});

it('takes the edition title when the work title is in another script', function () {
    Http::fake([
        'openlibrary.org/search.json*' => Http::response(openLibraryFixture('openlibrary-isbn-9781591169291.json')),
        'openlibrary.org/isbn/9781591169291.json' => Http::response(openLibraryFixture('openlibrary-edition-9781591169291.json')),
    ]);

    $result = app(OpenLibraryCatalogueSearch::class)->lookup('9781591169291', $this->context);

    expect($result->name)->toBe('Fullmetal Alchemist 4')
        ->and($result->nameRomanized)->toBe('Fullmetal Alchemist 4');
});

it('keeps the work title when the edition has none', function () {
    Http::fake([
        'openlibrary.org/search.json*' => Http::response(openLibraryFixture('openlibrary-isbn-9781591169291.json')),
        'openlibrary.org/isbn/*' => Http::response(['error' => 'notfound'], 404),
    ]);

    expect(app(OpenLibraryCatalogueSearch::class)->lookup('9781591169291', $this->context)->name)->toBe('鋼の錬金術師 4');
});

it('does not ask for the edition when the work title is already Latin', function () {
    Http::fake([
        'openlibrary.org/search.json*' => Http::response(['docs' => [['title' => 'Fullmetal Alchemist, Vol. 5', 'isbn' => ['9781421501758']]]]),
    ]);

    expect(app(OpenLibraryCatalogueSearch::class)->lookup('9781421501758', $this->context)->name)->toBe('Fullmetal Alchemist, Vol. 5');
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/isbn/'));
});
