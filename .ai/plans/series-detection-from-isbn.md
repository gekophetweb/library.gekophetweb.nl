# Plan — Detect the series and volume number from an ISBN

Status: **phases 1–2 implemented 2026-11-09; phases 3–6 implemented 2026-11-12** on
`feature/series-detection-sync` (see §10). Still open: the Google Books and Hardcover
series providers (§7 decision 2). Written 2026-11-09.

Related: [`own-wishlist.md`](own-wishlist.md) (series completion counts owned vs wanted),
[`search-title-and-creator.md`](search-title-and-creator.md) (`ComparesNames`, series
name triple).

> The application code is not in this repository; [`foundation.md`](foundation.md)
> builds it and creates the series tables in the phase-3 shape below. Class, table and column names below
> that already exist (`SeriesVolume`, `SeriesShelf`, `book_details.volume`,
> `series.artist_id`, the API call log, `CatalogueLookup`) are taken from the earlier
> plans — **check them against the code before implementing**, especially column types.

---

## 1. Goal

When a book is scanned:

1. work out **which series** it belongs to and **which volume** it is, and
2. fetch the **full list of volumes** in that series, so the series page can show
   owned / wanted / missing without the user typing anything.

Must work for both English series (test case: *Dungeon Crawler Carl*) and Japanese
manga (test case: *ベルセルク / Berserk*, ISBNs `9784592135746`, `9784592135753`).

---

## 2. Findings (tested live, 2026-11-08/09)

| Source | Key | ISBN → series + volume | All volumes of a series | Verdict |
|---|---|---|---|---|
| **NDL Search** (国立国会図書館) OpenSearch | none | ✅ `dc:title` = `ベルセルク`, `dcndl:volume` = `1` / `2`, plus `dcndl:titleTranscription` (kana) | ✅ `title=ベルセルク&creator=三浦建太郎` → volumes 1–43, none missing | **Primary for Japanese** |
| **Open Library** | none | ✅ English: edition → work + `series: "Dungeon Crawler Carl"`; volume only sometimes (`#1`). ❌ manga: one work per volume, no series | ❌ no series entity | **Primary ISBN → work for English** |
| **Wikidata** SPARQL | none | ❌ no ISBNs on the DCC books or on manga volumes | ✅ DCC: all 8 books with `P1545` series ordinal. Manga: series item exists (Q18558978) but no volume items | **Primary series list for English**; English/romanized name for manga |
| openBD | none | ⚠️ volume only inside title (`ベルセルク. 1`); "series" is the imprint | ❌ | Fallback for cover / publisher only |
| Google Books | user key | ⚠️ `seriesInfo` mainly on Play Books ebook editions; not testable (429 without key) | ⚠️ `series/membership/get` | Optional fallback |
| Hardcover.app GraphQL | user token | not tested | not tested | Optional later provider |
| Rakuten Books | user key | not tested; expected `seriesName` | — | Already a Books provider; add as fallback |

### Pitfalls observed

- **Imprint ≠ series.** NDL `dcndl:seriesTitle` and openBD `series` both return
  `Jets comics` — Hakusensha's imprint — and the `437` next to it is the imprint
  number, not the volume. Never map these fields to `series`.
- **Full-width digits.** NDL returns volumes as `４２`. NFKC-normalize before parsing.
- **ISBN formats.** Old NDL records store `4-592-13574-1` (hyphenated, ISBN-10, also in the `ISBN13` field).
  Normalize to hyphen-free ISBN-13 before comparing.
- **Several records per volume.** Volumes 41–43 each appear twice (regular + special
  edition). The special edition carries `dcndl:edition` (e.g. `ゾッド胸像フィギュア付き特装版`);
  the regular one has none. Keep them as **separate entries** (§7, decision 3).
- **Noise in series searches.** The NDL title search also returns the light novel, art
  books, guidebooks and foreign translations. Filter strictly (§4.2).
- **One English book, many ISBNs.** DCC book 1 has 16 ISBNs in Open Library. Link
  ISBN → work → series; never ISBN → series directly.
- **Kana is not romaji-as-used.** `ベルセルク` transliterates to *Beruseruku*, not
  *Berserk*. Kana alone cannot fill `name_romanized`/`name_english` well; use the
  Wikidata series labels (`en`, `ja-Latn` where present) for those, and fall back to
  transliteration only as a suggestion the user can edit.
- **Creator format.** NDL gives `三浦, 建太郎, 1966-2021` — strip the life dates and the
  comma before matching against `artists` via `ComparesNames::normalizeName()`.

---

## 3. Design

A separate contract next to `CatalogueLookup` — series resolution is a different
question ("which series is this and what else is in it?") from "what is this item?",
and its providers are different.

```php
interface SeriesLookup
{
    /** ISBN → series reference + this book's volume number, or null. */
    public function resolve(Isbn $isbn, Language $language): ?SeriesMatch;

    /** Series reference → every known volume, ordered. */
    public function volumes(SeriesReference $series): Collection; // of SeriesVolumeData
}
```

- `SeriesMatch` — `SeriesReference $series`, `?VolumeNumber $volume`,
  `Confidence $confidence`, `string $source`.
- `SeriesReference` — `source` (`ndl`, `wikidata`, …), `external_id`, name triple
  (`name`, `name_romanized`, `name_english`), creator name, publisher name.
- `SeriesVolumeData` — `VolumeNumber`, edition label (null for the regular edition),
  title, ISBNs (array), release date.
- `VolumeNumber` — decimal-capable value object (`1`, `3.5`), parsed from strings like
  `1`, `４２`, `第12巻`, `Vol. 12`, `#3`, `(12)`.

### Provider chain, chosen by the scan language

| Scan language | `resolve()` order | `volumes()` |
|---|---|---|
| Japanese | NDL → Rakuten `seriesName` → title parsing | NDL |
| English (and other Latin-script) | Open Library → Wikidata → Google Books `seriesInfo` → Hardcover → title parsing | Wikidata, then Google Books / Hardcover when Wikidata has no volume items (translated manga) |

The chain stops at the first match with `Confidence::High`; lower-confidence matches
are kept as suggestions. Resolved by a `SeriesLookupManager`, mirroring how
`CatalogueLookup` providers are resolved by category — new providers plug in without
touching call sites.

### Series identity (external IDs)

| Source | `external_id` |
|---|---|
| Wikidata | the series QID (e.g. `Q…` for Dungeon Crawler Carl) |
| NDL | NDL has no series ID → a stable key: NFKC-normalized title + normalized creator + publisher, e.g. `ndl:ベルセルク|三浦建太郎|白泉社` |

Before creating a series, match an existing user series by `external_id`, then by
`ComparesNames` on name + artist, so re-scans and manual series never duplicate.

---

## 4. Provider details

### 4.1 Open Library + Wikidata (English)

1. `GET https://openlibrary.org/isbn/{isbn}.json` → `works[0].key`, `series[]`,
   `title`. Parse a trailing `#N` from `series[]` if present.
2. Work → Wikidata book item: SPARQL on Open Library ID `wdt:P648 "OL…W"`; if none,
   match by series label + book title.
3. Book item → series via `wdt:P179`, volume via the `pq:P1545` qualifier.
4. `volumes()`: one SPARQL query — all `?book wdt:P179 ?series` with `P1545`, `P577`
   (publication date) and `P212` (ISBN-13, usually absent), ordered by ordinal.

### 4.2 NDL Search (Japanese)

- Endpoint: `https://ndlsearch.ndl.go.jp/api/opensearch` (RSS/XML; namespaces `dc`,
  `dcndl`, `xsi`).
- `resolve()`: `?isbn={isbn}` → `dc:title` (series title), `dcndl:volume`,
  `dcndl:titleTranscription`, `dc:creator`, `dc:publisher`.
- `volumes()`: `?title={series title}&creator={creator}&cnt=200`, then keep only items
  where
  - `dc:title` equals the series title exactly (after NFKC),
  - the publisher matches (or the ISBN publisher prefix matches, e.g. `4592`),
  - `dcndl:volume` parses to a number,
  - and group by volume number **plus edition** (`dcndl:edition`, empty for the regular
    edition): a special edition is its own entry with its own ISBNs (§7, decision 3).
- Ignore `dcndl:seriesTitle` for series purposes; optionally store it as the imprint.

### 4.3 Common rules

- Every HTTP call goes through the existing **API call log table** (provider, query,
  user, category/language, outcome, timing) — guideline §4.3.
- Send a descriptive `User-Agent` (app name + URL); Wikidata and NDL both ask for it.
- Cache `volumes()` results per `external_id` (e.g. 7 days) and `resolve()` per ISBN;
  a batch scan of 40 manga volumes should cost one `volumes()` call, not 40.
- These sources need no API key, so they are **not** per-user keys; only the optional
  Google Books / Hardcover / Rakuten fallbacks use the user's stored keys (§5.1).
- Check NDL Search and Wikidata usage terms (attribution, rate limits) before release.

---

## 5. Phases

### Phase 1 — Value objects and parsing
1. `app/Support/Isbn.php` — normalize (strip hyphens/spaces), validate checksum,
   ISBN-10 → ISBN-13.
2. `app/Support/Series/VolumeNumber.php` — NFKC + the patterns in §3; decimal-capable.
3. `app/Support/Series/TitleVolumeParser.php` — fallback: split `ベルセルク 41`,
   `ONE PIECE 1`, `Berserk Volume 1`, `Title, Vol. 3` into series title + volume.
4. `app/Support/Series/CreatorName.php` — strip NDL life dates / comma order.

### Phase 2 — Providers
5. `app/Contracts/SeriesLookup.php` + DTOs from §3.
6. `app/Services/Series/NdlSeriesLookup.php`
7. `app/Services/Series/OpenLibraryWikidataSeriesLookup.php`
8. `app/Services/Series/SeriesLookupManager.php` — language → chain, confidence handling.
9. Config `config/series.php` — endpoints, user agent, cache TTLs, chain order.

### Phase 3 — Data
10. Migration on `series`: `external_source` (nullable string), `external_id`
    (nullable string), unique on `['user_id', 'external_source', 'external_id']`.
11. Check `book_details.volume` and `SeriesVolume`'s number column: if integer, migrate
    to `decimal(6,2)` (or add `volume_sort`) so `3.5` side stories fit. Keep the display
    string separate from the sortable number.
12. `SeriesVolume` gets an `isbns` JSON column (or a child table) so a scan can be
    matched to a known volume by any of its ISBNs, and a nullable `edition` string.
    Unique on `['series_id', 'volume_sort', 'edition']` — a special edition is its own
    row (§7, decision 3).

### Phase 4 — Scan integration
13. Single scan: after the catalogue lookup, call `SeriesLookupManager::resolve()`.
    Show the result in the "add to collection" prompt as a **pre-filled, editable**
    series + volume, with the source shown ("from NDL").
14. Batch scan: resolve per item inside the queued job. High confidence → apply
    automatically (§7, decision 1); otherwise attach as a suggestion visible on the item page.
15. When a series is linked for the first time, dispatch `SyncSeriesVolumes` (queued).

### Phase 5 — Series sync
16. `app/Jobs/SyncSeriesVolumes.php` — `volumes()` → upsert `SeriesVolume` rows by
    volume number + edition, merge ISBNs, never delete volumes the user owns.
17. Hook the existing series "sync" button to the same job.
18. Schedule a periodic re-sync for ongoing series (e.g. weekly) to pick up new volumes.

### Phase 6 — API
19. Expose `series.external_source` / `external_id` and per-volume `volume_number` in
    the V1 resources; add `POST /api/v1/series/{series}/sync` for the phone app.

---

## 6. Tests (Pest)

Record real responses once into `tests/Fixtures/series/` and replay with `Http::fake()`
— no live calls in the test suite.

- `Isbn`: hyphenated ISBN-10 `4-592-13574-1` (the plan first said `-6`, a bad checksum) → `9784592135746`; bad checksum rejected.
- `VolumeNumber`: `1`, `４２`, `第12巻`, `Vol. 12`, `#3`, `3.5`.
- `TitleVolumeParser`: `ベルセルク 41`, `ベルセルク. 1`, `Berserk Volume 1`.
- NDL `resolve()`: `9784592135746` → `ベルセルク`, volume 1; `9784592135753` → volume 2;
  `Jets comics` is **not** the series.
- NDL `volumes()`: Berserk → 43 volumes, 1..43 contiguous, light novel / art book /
  guidebook / translations filtered out; volumes 41–43 also have a separate special
  edition entry (46 entries in all).
- Open Library + Wikidata: DCC book 1 ISBN `9780593820247` → series *Dungeon Crawler
  Carl*, volume 1; `volumes()` → 8 books in order.
- Manager: Japanese scan never calls Open Library; English scan never calls NDL;
  chain falls through on a miss.
- Every provider call writes an API call log row.
- Re-scanning a volume of an existing series links to that series (no duplicate);
  another user's series with the same `external_id` is never reused.
- Sync job does not delete owned volumes and adds newly published ones.

Finish with `vendor/bin/pint --dirty --format agent` and `php artisan test --compact`
(inside the `web` container).

---

## 7. Decisions and open questions

Answered by the user on 2026-11-10:

1. **Batch confidence — auto-apply.** High-confidence series matches in a batch scan
   are applied automatically; lower-confidence ones stay suggestions.
2. **Translated manga — Google Books and Hardcover become real providers.** For English
   editions of manga (e.g. the English *Berserk*), Open Library has one work per volume
   and no series, and Wikidata has the series but no volume items, so title parsing
   alone is not enough. Google Books (`seriesInfo`, `series/membership/get`) and
   Hardcover.app (GraphQL) are built as full providers in the English chain, not just
   config slots. Both use the user's own key/token, so **per-user API keys (guidelines
   §5.1) are a prerequisite** and come first. Without a key, the chain skips that
   provider and falls through to title parsing + manual linking. Record real responses
   for an English *Berserk* volume as fixtures before writing the providers.
3. **Special editions — separate entries.** A special edition (NDL `dcndl:edition`
   set) is its own volume entry with its own ISBNs, next to the regular edition of the
   same number. Owning one does not mark the other as owned.
4. **Romanized names — confirmed.** Wikidata English / romaji label first, then a kana
   transliteration as an editable suggestion, then empty.
5. **Provider list — done.** NDL, Open Library and Wikidata are added to §5 of
   [`physical-library.md`](../guidelines/physical-library.md).

## 8. Out of scope

- Series detection for Music and Games.
- "Add missing volume to wishlist" from the series page — still the follow-up named in
  [`own-wishlist.md`](own-wishlist.md) §7; this plan makes it easier (volumes now carry
  ISBNs) but does not build it.

## 9. Implementation notes (phases 1–2, 2026-11-09)

The repository had no application code, so a fresh Laravel 13 app was scaffolded and
only the parts of this plan that do not need the domain models were built:

- `app/Support/Isbn.php`, `app/Support/Series/*` (value objects, `TitleVolumeParser`,
  `CreatorName`, `Text`), `app/Contracts/SeriesLookup.php`,
  `app/Services/Series/{NdlSeriesLookup,OpenLibraryWikidataSeriesLookup,SeriesLookupManager}.php`,
  `config/series.php`.
- The API call log did not exist yet: added `api_call_logs` + `ApiCallLog` +
  `App\Services\ApiCallLogger`, which every provider call goes through.
- `resolve()` takes the scan language as an ISO 639-1 string (`ja`, `en`; `jpn` and
  `ja-JP` are mapped) until a `Language` model exists.
- `SeriesLookupManager::resolve()` returns a `SeriesResolution` (best match first, the
  rest as suggestions) and takes an optional catalogue title for the title-parsing
  fallback. Empty results are not cached, so a failed call is retried on the next scan.
- `config/cache.php` keeps `serializable_classes` locked down and allowlists only the
  series value objects.
- NDL paging uses `cnt` + `idx`; up to 5 pages of 200.
- Volumes whose `dcndl:volume` is not just a number (`EPISODE:１`, `L-1`,
  `黄金時代篇 1 …`) and records not in category `図書` are dropped.
- Rakuten and Google Books fallbacks are config slots only: per-user API keys (§5.1 of
  the guidelines) do not exist yet.

Still open: phases 3–6, per-user API keys, and the Google Books + Hardcover providers
(§7, decision 2).

Decision 3 was applied on 2026-11-10: `NdlSeriesLookup::volumes()` groups by volume
number + `dcndl:edition`, so Berserk lists 46 entries (43 regular + special editions of
41–43), each regular edition followed by its special edition. `SeriesVolumeData` carries
the `edition` label (null = regular) and `isSpecialEdition()`.

## 10. Implementation notes (phases 3–6, 2026-11-12)

- **Phase 3 needed no migration:** the foundation migration already created `series`
  with `external_source` / `external_id` (unique per user), and `series_volumes` with
  `volume_number` (display), decimal `volume_sort`, `edition` (`''` = regular), `isbns`
  and the `(series_id, volume_sort, edition)` unique index. One migration was added:
  `objects.series_suggestion` (JSON) for matches not sure enough to apply.
- **`App\Actions\Series\LinkSeries`** puts an item in a series: matches the user's series
  by `external_source` + `external_id`, then by name (any of the name triple, via
  `Text::key()`), else creates it with the reference's artist and publisher. A hand-made
  series of the same name gets the source attached so it can sync. Another user's series
  is never reused. A newly linked syncable series (`ndl`, `wikidata`) that was never
  synced dispatches `SyncSeriesVolumes`.
- **`Series::reference()`** rebuilds the `SeriesReference` for `volumes()` from the stored
  external ID, not the editable name: NDL's ID is `title|creator|publisher`, exactly what
  its volume search needs.
- **Single scan (step 13):** the lookup resolves the series for books with a valid ISBN
  and stores the best match in the pending scan; the add form shows "Series" + "Volume"
  pre-filled with the source ("from NDL"). Left as suggested → linked to the source;
  renamed → a series of that name without a source; emptied → no series.
- **Batch scan (step 14):** `ProcessScanBatch` runs `DetectSeries` per added item. A
  high-confidence match is applied (decision 1); otherwise it is stored as
  `series_suggestion`, which the item page offers to accept ("Add to this series") or
  dismiss. A detection failure is reported and leaves the item added.
- **Wishlist adds** from catalogue search (web and API) queue `DetectObjectSeries`, the
  same detection, so the user does not wait on it.
- **Phase 5:** `SyncSeriesVolumes` (queued, unique per series for 10 minutes) upserts
  volumes through the series relation by `volume_sort` + edition, merges ISBNs, never
  deletes, and leaves `synced_at` alone on an empty answer (a failed call looks the same).
  No sync button existed; the series page now has "Fetch volumes" plus "Volume list from
  NDL · updated …" for linked series. `series:sync` re-queues series last synced 7+ days
  ago and is scheduled weekly.
- **Phase 6:** `SeriesResource` adds `syncable` and `synced_at` (`external_source` /
  `external_id` and per-volume `volume_number` were already there);
  `POST /api/v1/series/{series}/sync` answers 202, or 422 for an unlinked series.
- **Not built — Google Books and Hardcover series providers (decision 2):** the plan asks
  for real recorded responses for an English *Berserk* volume first, and neither API can
  be recorded without the user's key (Google Books answers 429 keyless). The English chain
  is still Open Library + Wikidata → title parsing. Rakuten `seriesName` was also left
  out: the recorded fixture shows it holds the imprint (`ジェッツコミックス`), the same
  pitfall as NDL's `dcndl:seriesTitle`.
- Tests: `tests/Feature/Series/SeriesScanTest.php` (14 tests).
