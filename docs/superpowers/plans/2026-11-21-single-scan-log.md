# Single Scan Log Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Record every single-item scan as a code in an automatic "single" scan batch, so admins can see, redo and romanize it like batch scans.

**Architecture:** A `mode` column on `scan_batches` (`batch` | `single`). A new action `RecordSingleScan` finds or creates the user's single batch for the current scan setup, records each lookup as a `ScanBatchItem` (API calls linked through `Context::scope`, as `ProcessScanBatch` does), and marks it added / promoted when the user adds it. `ScanController` calls it; existing admin pages and redo logic are reused.

**Tech Stack:** Laravel 13, PHP 8.4, Pest, Blade.

**Spec:** `docs/superpowers/specs/2026-11-21-single-scan-log-design.md`

## Global Constraints

- Branch: `feature/single-scan-log` from `develop`.
- Tests never call external APIs: reuse the `Http::fake()` set-up and `startScan()` helper from `tests/Feature/Scan/ScanTest.php`.
- Run tests: `docker compose -f docker-compose.test.yml run --rm test php vendor/bin/pest --compact --filter=<name>`; Pint: `... test php vendor/bin/pint --format agent <files>`.
- Status strings stay plain strings, as in the rest of the batch code. New status: `not_added`, label "Not added".
- No visible change to the user's scan pages.

## Review Focus

- Same code scanned twice with the same setup → one item row, updated, not a unique-constraint error.
- User changes setup (another language) between scans → a second single batch; the first keeps its language.
- Lookup throws → item marked `failed` with "Something went wrong; try scanning it again.", the scan page still works.
- Added by hand after "not found" → item becomes `added` with the new object.
- Session from before this ships (pending scan without `scan_item_id`) → adding still works, nothing recorded.

---

### Task 1: Record single scans

**Files:**
- Create: `database/migrations/2026_11_21_000000_add_mode_to_scan_batches_table.php`
- Create: `app/Actions/Scan/RecordSingleScan.php`
- Modify: `app/Models/ScanBatch.php` (constants, fillable, `isSingle()`)
- Modify: `app/Http/Controllers/ScanController.php` (`lookUpBarcode`, `add`)
- Test: `tests/Feature/Scan/SingleScanLogTest.php`

**Interfaces:**
- Produces: `ScanBatch::MODE_BATCH = 'batch'`, `ScanBatch::MODE_SINGLE = 'single'`, `ScanBatch::isSingle(): bool`; `mode` fillable, default `batch`.
- Produces: `RecordSingleScan::batchFor(User $user, ScanSetup $setup): ScanBatch` — first or create by user, category, sub-category, language, ownership, `mode = single`; created with `status = done`, `committed_at` and `finished_at` = now.
- Produces: `RecordSingleScan::start(User $user, ScanSetup $setup, string $barcode): ScanBatchItem` — first or create item by barcode in that batch, reset to `status = pending`, `message = null`, `object_id = null`.
- Produces: `RecordSingleScan::outcome(ScanBatchItem $item, CatalogueOutcome $outcome): void` — same statuses and messages as `ProcessScanBatch::handleItem`, but a found result becomes `not_added` (no object).
- Produces: `RecordSingleScan::existing(ScanBatchItem $item, CollectionObject $existing): void` — `already_owned` / `already_wanted` with `object_id`.
- Produces: `RecordSingleScan::added(?int $itemId, CollectionObject $object, string $status = 'added'): void` — no-op for null or an item of another user.
- Session: `scan.pending.scan_item_id` holds the item id.

- [ ] **Step 1: Write the failing tests** in `SingleScanLogTest.php`:
  - `it('records a single scan as a code in a single batch, with its API calls')`: start single, look up `9784592135746`; expect one `ScanBatch` with `mode = single`, `status = done`, the setup's language; one item with `status = not_added`, `object_id` null; `ApiCallLog::where('scan_batch_item_id', $item->id)->exists()` true. Then `post(route('scan.single.add'))`; item is `added`, `object_id` = the new object.
  - `it('updates the same code on a re-scan and starts a new single batch for another setup')`: look up the same code twice → one item; start again with Korean → second single batch.
  - `it('records not found, and an item added by hand')`: unknown code → `not_found`; add with `name` / `name_romanized` → `added`.
  - `it('records a code already in the collection')`: owned object with the code → item `already_owned`, `object_id` set, no HTTP sent.
  - `it('still adds a scan looked up before scans were recorded')`: put `scan.pending` without `scan_item_id` in the session, add → object created, no item.
- [ ] **Step 2: Run them**, expect FAIL (no `mode` column / no items).
- [ ] **Step 3: Implement** the migration (`string('mode')->default('batch')->after('ownership')`), the model additions, the action, and the controller wiring: in `lookUpBarcode` call `start()`, then run the catalogue lookup and series detection inside `Context::scope(fn () => ..., hidden: [ApiCallLogger::SCAN_BATCH_ITEM => $item->id])`, wrap the lookup in try/catch → `failed` + report, then `outcome()` / `existing()`; store `scan_item_id`. In `add` call `added()` with `promoted` for the promote path.
- [ ] **Step 4: Run** `--filter=SingleScanLog` and `--filter=ScanTest|ScanRedo`, expect PASS.
- [ ] **Step 5: Commit** "Record single scans as codes in a single scan batch".

### Task 2: Admin redo adds a single scan the user didn't add

**Files:**
- Modify: `app/Models/ScanBatch.php` (`REDOABLE`)
- Modify: `resources/views/admin/scan-batches/show.blade.php`, `resources/views/admin/scan-batches/item.blade.php`, `resources/views/scan/batch.blade.php` (label `not_added` => 'Not added', neutral tone)
- Test: `tests/Feature/Scan/SingleScanLogTest.php`

**Interfaces:**
- Consumes: Task 1's single batch and `not_added` items.
- Produces: `ScanBatch::REDOABLE = ['failed', 'not_found', 'not_added']`.

- [ ] **Step 1: Write the failing test** `it('lets an admin redo a single scan the user did not add, which adds it')`: user looks up the code without adding; admin posts `admin.scan-batches.redo` for that batch; item `added`, `CollectionObject` owned by the user with that barcode; the admin batch page shows "Not added" before the redo.
- [ ] **Step 2: Run it**, expect FAIL.
- [ ] **Step 3: Implement** the `REDOABLE` change and the labels.
- [ ] **Step 4: Run** `--filter=SingleScanLog|AdminScanBatches`, expect PASS.
- [ ] **Step 5: Commit** "Let admins redo single scans that weren't added".

### Task 3: Show single batches to admins only

**Files:**
- Modify: `app/Http/Controllers/Admin/ScanBatchController.php` (`index`: `mode` filter `nullable|in:batch,single`)
- Modify: `resources/views/admin/scan-batches/index.blade.php` ("Single" badge with `data-single`, Batch / Single filter links keeping the other filters)
- Modify: `app/Http/Controllers/ScanBatchController.php` (`index`), `app/Http/Controllers/LibraryController.php` (`hasScanBatches`): only `mode = batch`
- Test: `tests/Feature/Scan/SingleScanLogTest.php`

**Interfaces:**
- Consumes: `ScanBatch::MODE_*`.

- [ ] **Step 1: Write the failing tests**:
  - `it('marks single batches on the admin list and filters on them')`: one batch and one single batch; admin index shows `data-single` once; `?mode=single` lists only the single one, `?mode=batch` only the other.
  - `it('keeps single batches out of the user\'s own batch list')`: user's `scan.batches` page does not show the single batch; with only a single batch, the dashboard's batch link is absent (match the existing `hasScanBatches` assertion in `ScanBatchListTest`).
- [ ] **Step 2: Run them**, expect FAIL.
- [ ] **Step 3: Implement** the filter, badge and exclusions.
- [ ] **Step 4: Run** the full suite (`pest --compact`, in the background — it takes ~15 min) and Pint on the changed files, expect PASS / clean.
- [ ] **Step 5: Commit** "Show single scan batches to admins only", push the branch.
