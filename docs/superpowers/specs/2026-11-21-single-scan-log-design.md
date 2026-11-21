# Logging single scans like batches — design

## Why

Single-item scans leave no record: the result lives only in the user's session, and the
API calls are logged against the user but not tied to a scan. When a lookup goes wrong
(a provider didn't answer, a key was missing, the data came back odd), an admin can't see
what was scanned or what the APIs returned, and can't redo it. Batch scans already have
all of that. Single scans should get the same.

## Goals

- Every single-scan lookup is recorded: the code, its outcome, the item it added (if any)
  and the raw answers of every API call made for it.
- Admins see single scans in **Admin → Scan batches**, with the same pages, raw-answer view,
  redo and romanize buttons as batches.
- An admin redo of a single scan the user **didn't add** looks it up again **and adds it**
  to the owner's collection or wishlist, exactly like a batch redo.

## Non-goals

- No change to how scanning looks or works for the user.
- Users don't get a new page for their single scans. These batches stay out of their own
  batch list.
- No backfill: scans made before this ships stay unrecorded.

## Approaches

- **A. Single scans as a kind of batch (chosen).** Reuses the batch pages, redo, API-log
  linking and romanization almost unchanged. Cost: "batch" is a slightly loose word for it.
- B. A separate single-scan log with its own table and admin pages. Cleaner naming, but it
  duplicates most of the batch code.
- C. Only show raw API calls per user and barcode. Can't redo or add, so it doesn't meet
  the goal.

## Design

- **Batch kind.** A new column `scan_batches.mode`: `batch` (the default, so existing rows
  keep their meaning) or `single`.
- **One single batch per scan setup.** On a single-scan lookup, find or create the user's
  `single` batch for the current setup (category, sub-category, language, ownership). It is
  created as `done` with `committed_at` / `finished_at` set, so admin redo works on it right
  away. This keeps the setup with each scan, so a redo uses the right language and category
  even if the user changed setup later.
- **Recording a lookup.** Each lookup creates or updates the batch item for that code
  (unique per batch and barcode, so a re-scan updates its row). The catalogue lookup and
  series detection run inside `Context::scope` with the item's id, the same way
  `ProcessScanBatch` does it, so their API calls are linked to it. The item's status:
  - found but not added yet: new status **`not_added`** ("Not added")
  - `not_found`, `failed` or `invalid`, with the same messages the batch job uses
  - already in the collection: `already_owned` / `already_wanted`
- **Adding.** When the user adds the item (found, or typed in by hand after not found), the
  item becomes `added` with its `object_id`. A promotion from the wishlist becomes
  `promoted`. The user's own redo button on the scan page updates the same item.
- **Admin redo.** `not_added` joins `ScanBatch::REDOABLE`. Redo, ticked redo and retry
  then work unchanged: they look the code up again with the owner's keys and add it.
- **Admin list.** The Scan batches index shows a "Single" label on single batches and gets
  a Single / Batch filter. The batch page and item page are reused as they are.
- **User list.** The user's own batch list (`scan.batches`) and the dashboard's "has scan
  batches" check only count `mode = batch`.

## Left to the builder

- Exact label text and where the "Single" badge sits on the index.
- Whether the find-or-create lives on `ScanBatch` or in a small action.
