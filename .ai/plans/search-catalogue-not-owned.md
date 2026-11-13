# Plan — Search for books you do not own yet, and add them to the wishlist

Status: **implemented 2026-11-12** on `feature/search-catalogue-not-owned`. Written
2026-11-10. See §7 for what differs from the text below.

Depends on: [`foundation.md`](foundation.md) (catalogue providers, per-user keys) and
[`own-wishlist.md`](own-wishlist.md) (wishlist items). Related:
[`search-title-and-creator.md`](search-title-and-creator.md), which searches **your own**
collection — this plan searches **the catalogues** for books that are not in it.

---

## 1. Goal

From the web UI (and the phone app), type a title or author, see matching books from
the external catalogues, see at a glance which ones you already own or want, and put a
book on the wishlist in one click — without having the book in hand to scan.

## 2. Design

- **Same box, extra scope.** The header search from
  [`search-title-and-creator.md`](search-title-and-creator.md) gets a fourth
  `SearchScope` case, `Catalogue = 'catalogue'` ("Not owned yet"). Your own results stay
  the default; the catalogue scope is an explicit choice because it costs external
  calls.
- **Contract.** `CatalogueLookup` gains
  `search(string $term, Category $category, Language $language): Collection` (of
  `CatalogueResult`: name triple, creator, publisher, release date, ISBNs, cover URL,
  `source`). Barcode lookup stays as it is.
- **Providers, Books only at first** (Music and Games have no provider yet, §5):
  - Japanese: Rakuten Books (`title` / `author` search) → NDL OpenSearch (no key).
  - English: Google Books `q=intitle:/inauthor:` → Open Library `search.json` (no key).
  - Keyed providers are skipped when the user has no key (§5.1); the keyless ones keep
    the feature usable without any setup.
  - Results are merged and de-duplicated by ISBN-13 (§5).
- **Mark what you have.** Each result is matched against the user's objects by any ISBN
  and labelled **Owned**, **On wishlist**, or nothing. Owned results show a link to the
  item instead of an add button.
- **Add to wishlist.** `POST /wishlist` with the chosen result creates a
  `CollectionObject` with `ownership = wishlist`, reusing the create path of
  `AddScannedObject` (same validation, same series detection via `SeriesLookupManager`),
  so a later scan of that ISBN auto-promotes it to owned (own-wishlist decision 2).
- **Interactive, not queued.** One search is a handful of calls the user waits for, so
  it runs in the request, with a short timeout per provider and results cached per
  term + category + language (e.g. 1 hour). Adding the item is synchronous; series
  sync after it stays queued.
- Every provider call goes through `ApiCallLogger` (§4.3).

## 3. Phases

1. `CatalogueLookup::search()` + `CatalogueResult` DTO; implement on the Rakuten, Google
   Books, NDL and Open Library providers; a manager that picks the chain by language and
   merges by ISBN.
2. `SearchScope::Catalogue`; `SearchController` branches to the catalogue manager for
   that scope (category + language selects shown, defaulting to Books and the user's
   last scan language).
3. Results view: cover, title, author, publisher, year, Owned / On wishlist badge, and
   an "Add to wishlist" button. Empty state when no provider is available names the
   missing keys and links to settings.
4. `WishlistController@store` + `StoreWishlistItemRequest`; reuse the scan create path.
5. API: `GET /api/v1/search?search_by=catalogue&…` returns `CatalogueResult`s with the
   ownership label; `POST /api/v1/wishlist` adds one.

## 4. Tests (Pest)

Recorded responses in `tests/Fixtures/catalogue/`, replayed with `Http::fake()`.

- Japanese term uses Rakuten then NDL; English uses Google Books then Open Library;
  providers without a user key are skipped and not called.
- Results from two providers with the same ISBN collapse into one.
- An ISBN the user owns is labelled Owned, a wishlist one On wishlist; another user's
  items never influence the labels.
- Adding a result creates a wishlist item; adding it twice does not duplicate; scanning
  its ISBN afterwards promotes it to owned.
- Every provider call writes an API call log row.
- The catalogue scope never runs for a blank term.

## 5. Open questions (confirm before implementing)

1. Is this the intended meaning of "search option for books not owned yet" — searching
   external catalogues to add to the wishlist? The alternative reading is a filter on
   your own search for wishlist items only, which [`own-wishlist.md`](own-wishlist.md) +
   [`search-title-and-creator.md`](search-title-and-creator.md) §8 already give.
2. Should "Add missing volume to wishlist" on the series page
   ([`own-wishlist.md`](own-wishlist.md) §7) be built in the same change? It uses the
   same create path.

## 6. Out of scope

- Music and Games catalogue search (no provider yet).
- Price / availability lookups.

## 7. As built (2026-11-12)

- **Open questions (§5) were not answered;** built on the plan's own reading: question 1
  as "search the external catalogues and add to the wishlist", question 2 left out (no
  "add missing volume" on the series page).
- **A separate contract, `App\Contracts\CatalogueSearch`,** instead of a `search()` on
  `CatalogueLookup`: NDL and Open Library only search, they do no barcode lookup.
  `RakutenBooksLookup` and `GoogleBooksLookup` implement both; `NdlCatalogueSearch` and
  `OpenLibraryCatalogueSearch` are new. Results reuse `CatalogueResult` with the ISBN-13
  as `barcode`; results without an ISBN are dropped.
- **Queries:** Rakuten asks `title=` then `author=`; NDL `title=` then `creator=` and keeps
  printed books (`図書`) only; Google Books a plain `q=` (it covers title and author, where
  `intitle:`/`inauthor:` would be AND); Open Library `search.json?q=` and the first valid
  ISBN-13 of the work.
- **`CatalogueSearchManager`** picks the chain from `catalogue.search.books_order`
  (`ja`: rakuten → ndl, else google_books → openlibrary), skips keyed providers without
  the user's key (reported as `missingKeys`), merges by ISBN-13 and caches for an hour per
  user + term + category + language + providers used. Timeout 6 s per call.
- **Web:** the header scope select gets "Not owned yet"; that scope renders
  `search/catalogue.blade.php` with category, an "Add as" sub-category and language
  (defaulting to Books and the last scan's language, else Japanese). Owned / On wishlist
  badges link to the item; "Add to wishlist" posts to `POST /wishlist`
  (`WishlistController`, `StoreWishlistItemRequest`) through `AddScannedObject`, so adding
  twice returns the existing item and an owned item is never demoted.
- **Series detection is not run** on add: `AddScannedObject` does not call
  `SeriesLookupManager` yet (that is series-detection phases 3–6). The volume number is
  parsed from the title as for scans.
- **API:** `GET /api/v1/search?scope=catalogue&q=…&category_id=…&language_id=…` (the plan
  said `search_by=catalogue`; `scope` matches the web request) returns `data.catalogue[]`
  with `ownership` (`owned` / `wishlist` / null) and `object_id`; `POST /api/v1/wishlist`
  answers 201 when created, 200 when the book was already there.
- **Rate limit** `catalogue-search`: 20 catalogue searches per minute per user; searching
  your own library is not limited.
- Tests: `tests/Feature/Catalogue/CatalogueSearchTest.php` (16 tests); recorded NDL and
  Open Library fixtures listed in `tests/Fixtures/catalogue/README.md`.
