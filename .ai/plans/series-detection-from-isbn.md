# Plan — Detect the series and volume number from an ISBN

Status: **phases 1–2 implemented 2026-11-09** (parsing, providers, manager); phases 3–6
wait for the domain models (`series`, `SeriesVolume`, scan flow). Written 2026-11-09.

Related: [`own-wishlist.md`](own-wishlist.md) (series completion counts owned vs wanted),
[`search-title-and-creator.md`](search-title-and-creator.md) (`ComparesNames`, series
name triple).

> The application code is not in this repository. Class, table and column names below
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
  edition). Group by volume number, keep all ISBNs.
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
- `SeriesVolumeData` — `VolumeNumber`, title, ISBNs (array), release date.
- `VolumeNumber` — decimal-capable value object (`1`, `3.5`), parsed from strings like
  `1`, `４２`, `第12巻`, `Vol. 12`, `#3`, `(12)`.

### Provider chain, chosen by the scan language

| Scan language | `resolve()` order | `volumes()` |
|---|---|---|
| Japanese | NDL → Rakuten `seriesName` → title parsing | NDL |
| English (and other Latin-script) | Open Library → Wikidata → Google Books `seriesInfo` → title parsing | Wikidata |

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
  - and group by volume number, merging ISBNs (special editions).
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
    matched to a known volume by any of its edition ISBNs.

### Phase 4 — Scan integration
13. Single scan: after the catalogue lookup, call `SeriesLookupManager::resolve()`.
    Show the result in the "add to collection" prompt as a **pre-filled, editable**
    series + volume, with the source shown ("from NDL").
14. Batch scan: resolve per item inside the queued job. High confidence → apply;
    otherwise attach as a suggestion visible on the item page.
15. When a series is linked for the first time, dispatch `SyncSeriesVolumes` (queued).

### Phase 5 — Series sync
16. `app/Jobs/SyncSeriesVolumes.php` — `volumes()` → upsert `SeriesVolume` rows by
    volume number, merge ISBNs, never delete volumes the user owns.
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
  guidebook / translations filtered out, special editions merged.
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

## 7. Open questions (confirm before implementing)

1. **Batch confidence.** Auto-apply high-confidence series matches in batch mode, or
   always leave them as suggestions?
2. **Translated manga** (e.g. the English *Berserk* editions). The English chain has
   little data for manga volumes; accept title parsing + manual linking, or add
   Hardcover / Google Books as required providers?
3. **Special editions** (Berserk 41–43 twice). Treat as the same volume (owning either
   counts as owned), or as separate entries?
4. **Romanized names.** Prefer the Wikidata English/romaji label, then a kana
   transliteration suggestion, then empty — is that the desired order?
5. **Provider list in the guidelines.** Adding NDL, Open Library and Wikidata changes
   §5 of [`physical-library.md`](../guidelines/physical-library.md); update it once this
   plan is approved.

## 8. Out of scope

- Series detection for Music and Games.
- Hardcover provider (kept as a slot in the chain only).
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

Still open: phases 3–6 and the questions in §7.
