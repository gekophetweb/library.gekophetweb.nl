# Plan — Search by title and by author / artist name

Status: **not built in this repository** (checked 2026-11-10). Written 2026-08-27
against an earlier codebase where it was implemented; the files named below do not
exist here yet. Execute after [`foundation.md`](foundation.md).

Related: [`own-wishlist.md`](own-wishlist.md) — see §8.

---

## 1. What exists today

- There is **no search anywhere in the web UI**. The only entry points into the
  collection are the dashboard → category → series drill-down.
- The public API has one `search` parameter
  (`app/Http/Controllers/Api/V1/CollectionObjectController.php:36`) that `LIKE`-matches
  `objects.name`, `name_romanized`, `name_english` and exact-matches `barcode`. It does
  **not** reach authors or artists, and it does not escape `%` / `_` in the user's term.

### Where the data actually lives

- **Titles** — `objects.name` / `name_romanized` / `name_english`, and the series title
  on `series.name` / `name_romanized` / `name_english`.
- **Creators** — fully normalized into the shared `artists` table
  (`name` / `name_romanized` / `name_english`). Reachable three ways:
  `book_details.artist_id`, `music_details.artist_id`, and `series.artist_id`. The old
  free-text `author` / `author_romanized` columns were dropped by the
  `link_series_and_book_details_to_artists` migration, so **`artists` is the single
  source of creator names** — no string columns to also search.

### Decisions (confirmed with the user)

1. **One search box with a scope toggle** — a single field in the header nav plus an
   All / Title / Creator selector, defaulting to All. One route, one query path, shared
   with the API.
2. **Grouped results** — matching items first, then short sections linking to matching
   series and matching artists. Searching an author name lands the user on that
   artist's page, not just on a wall of their volumes.

---

## 2. Matching semantics

- **`LIKE '%term%'`, not `FULLTEXT`.** The database is MariaDB, which has no `ngram`
  parser, so a fulltext index would fail to tokenize Japanese titles — the bulk of this
  collection. A leading-wildcard `LIKE` is a full scan, but this is one person's
  catalogue (thousands of rows, not millions); revisit only if it ever gets slow.
- **Do not wrap columns in `LOWER()`.** The MariaDB columns use a `utf8mb4_..._ci`
  collation, so `LIKE` is already case- and width-insensitive. Adding `LOWER()` would be
  redundant and would defeat any index.
- **Escape the term.** `%`, `_` and `\` must be escaped before interpolation, otherwise
  a search for `%` matches the whole collection. This is a live bug in the existing API
  `search` parameter and gets fixed as part of this work.
- **Whitespace-insensitive fallback for CJK names.** Catalogue data spells the same name
  both as `三浦建太郎` and `三浦 建太郎` — this is exactly why
  `App\Models\Concerns\ComparesNames::normalizeName()` exists. When the trimmed term
  contains no spaces, also match against
  `REPLACE(REPLACE(col, ' ', ''), '　', '')` so a spaced stored name still hits. Keep
  this to the creator/title name columns; do not apply it to barcodes.
- **Term handling.** Trim, collapse internal whitespace runs, require at least one
  character; a blank term renders the empty search page rather than listing everything.
- **Barcode.** Keep the current exact-match behaviour, active under the All and Title
  scopes.

---

## 3. Phase 1 — Shared query building blocks

1. `app/Enums/SearchScope.php` — backed string enum, cases `All = 'all'`,
   `Title = 'title'`, `Creator = 'creator'` (TitleCase keys per the PHP guidelines), with
   a `label(): string`.
2. `app/Models/Concerns/SearchesNames.php` — a trait for every model carrying the
   `name` / `name_romanized` / `name_english` triple (`CollectionObject`, `Series`,
   `Artist`, and `Publisher` for free). Provides:
   - `public static function escapeLike(string $term): string`
   - `#[Scope] protected function whereNameLike(Builder $query, string $term): void` —
     ORs the three name columns, qualified (`series` and `objects` are joined in several
     places, so unqualified columns are ambiguous), plus the whitespace-collapsed
     fallback from §2.
   This mirrors the guideline that the name triple is deliberately consistent across
   entities so one piece of logic serves all of them (§3.4 of the project guidelines).
3. `app/Models/CollectionObject.php` — add three `#[Scope]` methods built on the trait:
   - `matchingTitle($term)` — own name columns, the linked series' name columns, and the
     exact barcode.
   - `matchingCreator($term)` — `whereHas` across `bookDetail.artist`,
     `musicDetail.artist` and `serie.artist`, each using `whereNameLike`.
   - `matchingSearch($term, SearchScope $scope)` — dispatches to the two above, ORing
     them for `All`.
4. `app/Models/Artist.php` and `app/Models/Series.php` — use the trait so the results
   page can query them directly. `Artist` already has `reachableBy()`, which must be
   combined with the name match so the creators section never lists artists outside the
   user's collection.

## 4. Phase 2 — Web search

5. `app/Http/Requests/SearchRequest.php` — `q` (`nullable|string|max:255`),
   `scope` (`nullable`, `Rule::enum(SearchScope::class)`, defaults to `All` in a
   `prepareForValidation()` / accessor), `page`.
6. `app/Http/Controllers/SearchController.php` — a single `index()`:
   - items: `$request->user()->objects()->matchingSearch(...)` with
     `with(['category', 'subCategory', 'serie', 'bookDetail.artist'])`, ordered by name,
     `paginate(25)->withQueryString()`.
   - series: `Series::ownedBy($user)->whereNameLike($term)->withCount('objects')`,
     capped at 10, skipped when the scope is Creator.
   - artists: `Artist::reachableBy($user)->whereNameLike($term)`, capped at 10, skipped
     when the scope is Title.
   Every query stays user-scoped — `objects()` off the user, `ownedBy()` on series,
   `reachableBy()` on artists.
7. `routes/web.php` — `Route::get('search', [SearchController::class, 'index'])->name('search.index')`
   inside the existing `auth` group.
8. `resources/views/components/layout.blade.php` — a GET form in the header nav: one
   text input (`name="q"`, keeping the current term via `request('q')`) and a compact
   `scope` select. It sits alongside the Dashboard / Scan / Settings links and collapses
   to icon-width on small screens.
9. `resources/views/search/index.blade.php` — three sections reusing the existing list
   styling from `library/category.blade.php`: **Items** (paginated, each row linking to
   `objects.show`, showing series · volume · author), **Series** (linking to
   `library.series`), **Creators** (linking to `artists.edit`, which is the artist page).
   Plus an empty state naming the term and the scope, and a result count per section.

## 5. Phase 3 — Public API

10. `app/Http/Controllers/Api/V1/CollectionObjectController::index()` — replace the
    inline `where(...)->orWhere(...)` block with `matchingSearch()`, and accept a new
    `search_by` parameter validated with `Rule::enum(SearchScope::class)`, defaulting to
    `All`.
    **Compatibility:** `search` alone keeps working and now *also* matches creator names.
    That is an additive widening of results; a client wanting the old behaviour passes
    `search_by=title`. Note it in the phone-app changelog.
11. `app/Http/Controllers/Api/V1/SearchController.php` + route
    `GET /api/v1/search` — returns the same grouped payload as the web page
    (`{objects: [...], series: [...], artists: [...]}`) using
    `CollectionObjectResource`, `SeriesResource` and a new
    `app/Http/Resources/V1/ArtistResource.php`, so the phone app does not have to fire
    three requests to build one search screen.

## 6. Phase 4 — Tests (Pest)

- `tests/Unit` — `SearchesNames::escapeLike()` neutralizes `%`, `_` and `\`;
  `SearchScope` labels.
- `tests/Feature/Library/SearchTest.php` (new):
  - title scope matches on `name`, `name_romanized` and `name_english`;
  - title scope matches an item through its **series** name;
  - creator scope finds items via a book author, via a music artist, and via the series
    artist;
  - All scope returns the union, and an exact barcode still resolves;
  - a term of `%` returns nothing rather than the whole collection (the escaping
    regression test);
  - `三浦 建太郎` is found when searching `三浦建太郎` (the whitespace fallback);
  - another user's items, series and artists never appear in any section;
  - the creators section only lists artists reachable by the user;
  - a blank term renders the empty state and runs no query.
- `tests/Feature/Api/CollectionApiTest.php` — extend the existing
  "the collection can be searched by name and barcode" test: `search_by=creator` filters
  to the author's items, `search_by=title` reproduces the old behaviour, and the default
  covers both.
- A new API test for `GET /api/v1/search` returning the three groups, scoped to the
  authenticated user.

Finish with `vendor/bin/pint --dirty --format agent` and `php artisan test --compact`
scoped to the touched files.

## 7. Performance notes

- No new indexes. A leading-wildcard `LIKE` cannot use one, and the creator branch runs
  as `EXISTS` subqueries over `book_details` / `music_details` / `series`, whose
  `artist_id` foreign keys are already indexed.
- The three result sections are three queries; the series and artist sections are capped
  at 10 rows each, so only the item list paginates.
- Eager-load `bookDetail.artist` on the item list or the author column will N+1 across
  25 rows.

## 8. Interaction with the own/wishlist plan

If [`own-wishlist.md`](own-wishlist.md) lands first (or afterwards), two small additions
follow: the results page labels wishlist items with the ownership badge, and the API
`ownership` filter composes with `search` / `search_by`. Neither changes the design
above — do whichever plan lands second as a small follow-up commit.

## 9. Deliberately out of scope

- Publisher and genre search. The `SearchesNames` trait makes it a one-line addition
  later, but it is not part of the requested scope.
- Relevance ranking. Results are ordered by name; "starts with the term" scoring is a
  later refinement if the flat ordering proves noisy.
- Search over catalogue providers (finding items you do *not* own). A different
  feature, now planned in [`search-catalogue-not-owned.md`](search-catalogue-not-owned.md)
  as a fourth `SearchScope` case on the same search box.
