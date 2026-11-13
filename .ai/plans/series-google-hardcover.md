# Plan — Google Books and Hardcover series providers

Status: **implemented 2026-11-13** on `feature/series-google-hardcover`, **against
hand-written fixtures** (§5). The part of `series-detection-from-isbn.md` decision 2
that was left out.

For English editions of manga (e.g. *Berserk Deluxe*), Open Library has one work per
volume and no series, and Wikidata has the series but no volumes. Google Books
(`seriesInfo`) and Hardcover (`book_series`) fill that gap with the user's own keys.

---

## 1. Decisions

1. **After Open Library + Wikidata** in the `default` chain: `[openlibrary+wikidata,
   google_books, hardcover]`. Real English novels stay keyless and quota-free; the keyed
   providers are asked only when that misses or is unsure. The chain still stops at the
   first high-confidence match.
2. **Skipped without a key** — for resolve and for volume syncs. Keys are per user
   (`UserApiKey`); the manager gets the user from the scan, the item's owner, or the
   series' owner (`SyncSeriesVolumes`).
3. **Cache:** the resolve cache key includes which keyed providers took part, so a user
   who adds a key later is not served the keyless answer for 30 days. Volume lists are
   public data and cached per series as before.
4. **Confidence:** high when the provider gives both its own series id and a number,
   medium without a number.
5. Both sources are syncable ("Fetch volumes", weekly `series:sync`).
6. Rakuten `seriesName` stays unused: it holds the imprint (series-detection as-built).

## 2. Build

1. `App\Contracts\KeyedSeriesLookup` (`requiredKey()`, `withKey()`), extends
   `SeriesLookup`.
2. `GoogleBooksSeriesLookup`: `volumes?q=isbn:` → `seriesInfo.volumeSeries[0].seriesId` +
   `bookDisplayNumber` (else `orderNumber`); `series/get` for the title;
   `series/membership/get`, paged (40 × max 5), grouped by number to merge editions.
3. `HardcoverSeriesLookup`: GraphQL `editions(isbn_13)` → `book.book_series[0]`;
   `series_by_pk.book_series` ordered by position; unnumbered entries dropped. Token
   accepted with or without "Bearer "; a 200 with `errors` is a miss.
4. `SeriesLookupManager::resolve(…, ?User)` / `volumes(…, ?User)` build keyed providers
   with the user's key or leave them out. `DetectSeries::resolve()`, `ScanController`
   and `SyncSeriesVolumes` pass the user.
5. `Series::SYNCABLE_SOURCES` gains both; `Series::sourceLabel()` replaces three
   copies of the source-name map in the views.

## 3. Tests

`tests/Feature/Series/KeyedSeriesLookupTest.php` (11) — provider mapping, paging,
no key / unknown ISBN, GraphQL errors, chain order, cache separation, sync with and
without the owner's token. Routes added to `fakeSeriesApis()` in `tests/Pest.php`.

## 4. As built (2026-11-13)

- Built as planned.

## 5. Still to do: record real responses

The plan asked for recorded responses first. Google Books still answers `429` without a
key and Hardcover needs a token, so the fixtures are **hand-written** from the
documented shapes (`tests/Fixtures/series/README.md` lists them and the `curl`
commands). Before relying on this in production, record one English *Berserk* volume
with real keys, replace the fixtures verbatim and fix any field the tests then flag —
the Hardcover field names (`contributions`, `book_series`, `series_by_pk`) are the
least certain.
