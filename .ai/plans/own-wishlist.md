# Plan — Own / Wishlist status per item

Status: **not built in this repository** (checked 2026-11-10). Written 2026-08-27
against an earlier codebase where it was implemented; the files named below do not
exist here yet. Execute after [`foundation.md`](foundation.md).

---

## 1. Design

Add a single `ownership` column on `objects` with two values, `owned` and `wishlist`,
backed by a PHP enum. An item is one *or* the other — a wishlist item is a real
`CollectionObject` row (keeping its barcode, series, volume, publisher, genres) that
simply has not been acquired yet. Buying it is a status flip, not a re-scan.

**Why not a taxonomy table.** The "model as data, not enums" rule in the project
guidelines (§7) targets user-extensible taxonomies (category / subcategory / genre /
language). Ownership is a closed two-value domain concept that branches code paths
(scan dedup, series completion, counts), so a `string` column plus a PHP enum is the
right shape.

**Why not two booleans.** "Owned *and* wishlisted" is meaningless, and every query
would have to handle the impossible combination.

### Decisions (confirmed with the user)

1. **Counts: owned only, wishlist separate.** Dashboard and category totals count
   owned items only. Wishlist items live in their own section with a secondary
   "N wanted" figure. "How big is my collection" stays an honest number.
2. **Re-scan: auto-promote.** Scanning a barcode that is already on the collection as
   a wishlist item, in Own mode, is treated as the acquisition event — the existing
   row flips to `owned`, no duplicate is created, and the response reports it as
   promoted. Never demote: scanning an owned item in Wishlist mode leaves it owned.

---

## 2. Phase 1 — Data + model

1. `app/Enums/OwnershipStatus.php` — backed string enum, cases `Owned = 'owned'` and
   `Wishlist = 'wishlist'` (TitleCase keys per the PHP guidelines), with a
   `label(): string`.
2. Migration `add_ownership_to_objects_table` —
   `string('ownership')->default('owned')->after('barcode')` and an index on
   `['user_id', 'ownership']`. Existing rows land on `owned` through the default, so
   there is no backfill script.
3. `app/Models/CollectionObject.php` — add `'ownership'` to `#[Fillable]`, cast it to
   `OwnershipStatus::class`, and add `#[Scope]` methods `owned()` and `wishlisted()`.
   Qualify the column the way `ownedBy()` already does — `series` joins are common on
   this model and an unqualified name would be ambiguous.
4. `database/factories/CollectionObjectFactory.php` — default
   `'ownership' => OwnershipStatus::Owned`, plus a `wishlisted()` state.

## 3. Phase 2 — Write paths (scan)

5. `app/Http/Requests/Scan/Concerns/ValidatesScanScope.php` — add
   `'ownership' => ['nullable', Rule::enum(OwnershipStatus::class)]` to `scopeRules()`,
   with a `prepareForValidation()` that defaults it to `owned`. (Implemented as optional
   rather than required, on the user's call: a required field would break every existing
   phone-app scan request, and a scan is normally an acquisition anyway. An invalid value
   is still rejected.) This propagates automatically to
   `StartScanSessionRequest`, `StartBackgroundScanRequest`, `StoreScannedObjectRequest`
   and `CommitScanBatchRequest`.
6. `resources/views/components/scanner-dialog.blade.php` — an Own / Wishlist radio pair
   next to the Mode radios, same `has-checked:` styling, defaulting to Own.
   `App\View\Components\ScannerDialog` needs no data change.
7. `resources/views/scan/session.blade.php` + `resources/js/scan-session.js` — carry
   `ownership` from the query string into the lookup, store and commit payloads, and
   show the chosen mode in the session header so a batch cannot be silently filed
   under the wrong status.
8. `app/Actions/Scan/AddScannedObject.php` — set `ownership` on create, and change the
   idempotency branch at the top of `handle()`: when the existing row is `wishlist` and
   the incoming scan is `owned`, promote it (`$existing->update([...])`) and return it;
   never demote. This is the main behavioural subtlety of the whole change.
9. `app/Http/Controllers/ScanLookupController.php` — the short-circuit currently returns
   `already_owned: true` for any linked barcode. Keep that key (the phone app reads it)
   but set it true only for `owned` rows. For a `wishlist` row return
   `already_owned: false` plus a new `existing: {id, ownership}` payload so the UI can
   offer "mark as owned" instead of "already in your collection". Update the two
   `already_owned` branches in `resources/js/scan-session.js` (lines ~106 and ~244) to
   match.
10. Migration `add_ownership_to_scan_batches_table` plus the `ScanBatch` `#[Fillable]`
    entry; `ScanBatchController` persists it and `App\Jobs\ProcessScanBatch` passes it
    into `AddScannedObject`.

## 4. Phase 3 — Read paths (web UI)

11. `app/Http/Requests/UpdateCollectionObjectRequest.php` — add
    `'ownership' => ['nullable', Rule::enum(OwnershipStatus::class)]`;
    `CollectionObjectController::update()` writes it, falling back to the item's current
    status so an edit that omits the field cannot silently demote a wishlist item.
12. `resources/views/objects/show.blade.php` — an ownership badge in the Details list,
    a one-click toggle form ("Move to wishlist" / "Mark as owned") above the edit
    `<details>`, and a select inside the edit form.
13. `app/Http/Controllers/CategoryController.php` + `resources/views/library/category.blade.php`
    — split the fetched objects by status. The existing series and standalone lists stay
    owned-only; a new "Wishlist" section lists the wanted items in that category. Series
    entries carry an owned count and a wanted count.
14. `app/Http/Controllers/SeriesController.php` — needs care. `$ownedBarcodes` and
    `$ownedVolumes` must be derived from **owned** objects only, otherwise a wishlisted
    volume marks itself owned in the completion list; `ownedCount` likewise. Give volumes
    a third state in `resources/views/library/series.blade.php`: owned / wanted / missing.
15. `app/Http/Controllers/DashboardController.php` — the `withCount` becomes two counts
    (owned, wishlist); the `whereHas` stays "has any item".
    `resources/views/dashboard.blade.php` shows "N owned · M wanted", with the owned
    figure primary.

## 5. Phase 4 — Public API

16. `app/Http/Resources/V1/CollectionObjectResource.php` — add
    `'ownership' => $this->ownership->value`.
17. `app/Http/Controllers/Api/V1/CollectionObjectController::index()` — validate and
    apply an `ownership` filter. Absent means both, so existing phone-app clients see no
    change. `update()` writes the new field through the shared form request.

## 6. Phase 5 — Tests (Pest)

- `tests/Unit` — enum labels; the `owned()` / `wishlisted()` model scopes.
- `tests/Feature/Scan/AddScannedObjectTest.php` — creates as owned; creates as wishlist;
  re-scanning a wishlist item in Own mode promotes it without duplicating; re-scanning an
  owned item in Wishlist mode does not demote.
- `tests/Feature/Scan/BarcodeLookupTest.php` — an existing wishlist barcode returns
  `already_owned: false` with `existing.ownership = wishlist`; an owned barcode is
  unchanged.
- `tests/Feature/Scan/ScannerTest.php` and `BackgroundScanTest.php` — an omitted
  `ownership` defaults to owned and an invalid one is rejected; a batch persists and
  applies it.
- `tests/Feature/Library/UpdateObjectTest.php` — toggle in both directions; an invalid
  value is rejected.
- `tests/Feature/Library/SeriesCompletionTest.php` — a wishlisted volume shows as
  *wanted*, not owned, and is excluded from `ownedCount`.
- `tests/Feature/Library/BrowseLibraryTest.php` and the Dashboard tests — wishlist items
  appear in their own section and do not inflate the owned counts.
- `tests/Feature/Api/CollectionApiTest.php` — the resource exposes `ownership`, the
  filter works, and PATCH updates it.

Finish with `vendor/bin/pint --dirty --format agent` and
`php artisan test --compact` scoped to the touched files.

## 7. Deliberately out of scope

"Add missing volume to wishlist" on the series completion list. A synced `SeriesVolume`
that is not owned could become a wishlist `CollectionObject` in one click, without a
scan — it reuses everything above but adds a new creation path, so it belongs in a
separate change.

Adding books you do not own from a catalogue search is planned in
[`search-catalogue-not-owned.md`](search-catalogue-not-owned.md), which uses the same
wishlist create path.
