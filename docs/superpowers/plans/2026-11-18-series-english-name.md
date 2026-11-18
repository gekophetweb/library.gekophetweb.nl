# Series English Name Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The owner can set an English name on a series. When it is set, it is the series' main title everywhere in the app.

**Architecture:** Reuse the existing `series.name_english` column, with no migration. A `display_name` accessor and an `orderByDisplayName` scope on `Series` drive every view and list. A new owner-only `PATCH` route on `LibraryController` edits the name.

**Tech Stack:** Laravel 13, PHP 8.4, Blade, Pest.

**Spec:** `docs/superpowers/specs/2026-11-17-series-english-name-design.md`

## Global Constraints

- Branch `feature/series-alias`. Every commit is pushed straight away (`.ai/skills/committing-and-pushing/SKILL.md`).
- Tests (from the repo root, stack down):
  `docker compose -f docker-compose.test.yml run --rm test sh -c 'rm -f bootstrap/cache/packages.php bootstrap/cache/services.php; php vendor/bin/pest --compact <path>'`
  Also run Pint `--dirty --format agent` before each commit. 20 failures already exist on develop (the Appearance tests). Those are not regressions.
- Matching and syncing keep using the original `name`. Do not touch `LinkSeries`, `SeriesReference::name` or the lookups.
- A blank English name is stored as `null`, never as `''`.
- Validation: `nullable|string|max:255`.

## Review Focus

- **Whitespace-only input** ("   ") should clear the name, not store a blank title. Laravel's TrimStrings plus ConvertEmptyStringsToNull already do this. Pinned by the "clears" test in Task 1.
- **Sorting:** lists ordered by `name` would put "Berserk" among the kana. Sort lists by display name instead. Pinned in Task 2.
- **Share-link viewers** must never see the edit form, even though they see the English name. Pinned in Task 1.
- **HTML in the name** must be escaped (Blade `{{ }}`). Use `{{ }}` only.
- **Another user's series:** PATCH returns 404, the same as the sync route. Pinned in Task 1.

---

### Task 1: Model accessor, edit route and series page

**Files:**
- Modify: `app/Models/Series.php`
- Create: `app/Http/Requests/UpdateSeriesRequest.php`
- Modify: `app/Http/Controllers/LibraryController.php` (new `updateSeries`)
- Modify: `routes/web.php`. Add next to `library.series.sync`:
  `Route::patch('library/series/{series}', [LibraryController::class, 'updateSeries'])->name('library.series.update');`
- Modify: `resources/views/library/series.blade.php`
- Test: `tests/Feature/Library/SeriesEnglishNameTest.php` (new)

**Interfaces:**
- Produces:
  - `Series::$display_name`: `Attribute` accessor returning `name_english ?: name`.
  - `Series::orderByDisplayName(Builder $query): void`: `#[Scope]`, `orderByRaw('coalesce(name_english, name)')` on the qualified columns, then `orderBy` id.

- [ ] **Step 1: Write the failing tests** in `SeriesEnglishNameTest.php`, with a `beforeEach` that creates `$this->user` and a series `['name' => 'ベルセルク', 'name_romanized' => 'Beruseruku']`:
  - `it('lets the owner set an English name')`: PATCH `library.series.update` with `name_english => 'Berserk'`. Assert a redirect to `library.series`, the session has `status`, and the fresh model's `name_english` is `'Berserk'`.
  - `it('clears the English name when blank')`: start with `'Berserk'`, PATCH `'   '`. Assert `name_english` is `null`.
  - `it('rejects a name over 255 characters')`: `str_repeat('a', 256)`. Assert `assertSessionHasErrors('name_english')`.
  - `it('404s for another user\'s series')`: PATCH on `Series::factory()->create()`. Assert 404, unchanged.
  - `it('shows the English name as the title with the original underneath')`: GET `library.series`. Assert `assertSeeInOrder(['<h1', 'Berserk', 'ベルセルク', 'Beruseruku'], false)` and `assertSee('<title>Berserk', false)`. Check how `x-layout` renders the title and match its format.
  - `it('shows the original title alone without an English name')`: the h1 contains `ベルセルク` and the page has no `data-original-name`.
  - `it('shows the edit form to the owner only')`: the owner sees `name="name_english"`. A guest on a series share link sees `Berserk` but no `name="name_english"`. Mint the share the way `tests/Feature/Sharing/ShareLinkTest.php` does.
  - `it('sorts series by display name')`: `Series::query()->orderByDisplayName()->pluck('id')` puts a series named `'Akira'` (no English name) before `'ベルセルク'` with English name `'Berserk'`, and that before `'あ'` with no English name.

- [ ] **Step 2: Run them to see them fail.** Run `tests/Feature/Library/SeriesEnglishNameTest.php`. Expected: FAIL with a route-not-defined error or a missing scope.

- [ ] **Step 3: Implement.**
  - `UpdateSeriesRequest`: `authorize()` returns true. Ownership is checked in the controller with `abort_unless(... , 404)`, the same as `syncSeries`. Rules: `['name_english' => ['nullable', 'string', 'max:255']]`.
  - `LibraryController::updateSeries(UpdateSeriesRequest $request, Series $series): RedirectResponse` updates `name_english` from validated input and redirects to `library.series` with status `'Series name saved.'`.
  - In `series.blade.php`, use `$shelf->series->display_name` for the layout title and the h1. When `name_english` is set, a `<p class="text-stone-500" data-original-name>` holds `name`. Below it, keep the existing romanized line, which shows when the romanized title differs from `name`.
  - The edit form sits inside `@if ($links->canEdit())`: a `<details>` with the summary "English name", holding a PATCH form with `@csrf` and `@method('PATCH')`, an input `id="name_english" name="name_english" maxlength="255" value="{{ old('name_english', $shelf->series->name_english) }}"`, an `@error` line, and a Save button. Style it like the "Fetch volumes" button.

- [ ] **Step 4: Run the tests to see them pass.** Run the same file plus `tests/Feature/Library/LibraryPagesTest.php` and `tests/Feature/Sharing`. Expected: PASS.

- [ ] **Step 5: Commit.** Message: `Let the owner give a series an English name`.

### Task 2: Show and sort by the display name everywhere

**Files:**
- Modify the views:
  - `resources/views/library/category.blade.php:40`
  - `resources/views/components/series-list.blade.php` (covers the artist and search pages)
  - `resources/views/objects/show.blade.php:15,31` and the series `<select>` at about line 170
  - `resources/views/search/index.blade.php:33`
  - `resources/views/settings/shares.blade.php:32`
- Modify the ordering, replacing `orderBy('name')` with `orderByDisplayName()` on Series queries in:
  - `app/Support/Library/CategoryShelf.php:53`
  - `app/Http/Controllers/LibraryController.php:109`
  - `app/Http/Controllers/SettingsController.php:63`
  - `app/Http/Controllers/CollectionObjectController.php:40`
  - `app/Support/Search/SearchResults.php` (the Series query only)
- Test: add to `tests/Feature/Library/SeriesEnglishNameTest.php`

**Interfaces:**
- Consumes: `Series::$display_name`, `Series::orderByDisplayName()` from Task 1.

- [ ] **Step 1: Write the failing tests.** Each test creates an owned item in the series, so the series shows on the category and artist shelves:
  - `it('shows the English name on the category shelf, artist page and settings share picker')`: `assertSee('Berserk')` on each page. On the shelf, assert `assertDontSee('>ベルセルク<', false)`.
  - `it('shows the English name on an item page and in its series select')`: GET `objects.show`. Assert `Berserk` shows in the breadcrumb and details, and `>Berserk</option>` is present.
  - `it('finds a series by its English name in library search')`: GET the library search route used in `search/index.blade.php`, with term `berser`. Assert `Berserk` shows in the Series section.

- [ ] **Step 2: Run them to see them fail.** Expected: FAIL. The pages still show `ベルセルク`, apart from the search test, which may already pass on matching but fails on the label.

- [ ] **Step 3: Implement.** Swap `->name` for `->display_name` on Series in the listed views. The shared `<select>` loop also renders publishers, artists and platforms. Use `{{ $choice->display_name ?? $choice->name }}` there, because other models return null for the unknown attribute. Swap the orderings.

- [ ] **Step 4: Run the tests to see them pass.** Run the whole suite. Expected: only the 20 known failures.

- [ ] **Step 5: Commit.** Message: `Show and sort series by their English name`.

### Task 3: Show the owner's English name while scanning

**Files:**
- Modify: `resources/views/components/scan-series-fields.blade.php`
- Test: `tests/Feature/Series/SeriesScanTest.php` (in the `single scan` describe)

**Interfaces:**
- The suggestion array holds `source` and `reference` (`SeriesReference::toArray()`: `source`, `externalId`, `name`, …).

- [ ] **Step 1: Write the failing test.**
  - `it('shows the owner\'s English name next to a detected series they already have')`: create `Series::factory()->for($this->user)` with `external_source 'ndl'`, the `external_id` that a `BERSERK_1` scan produces (read it from the existing "links the detected series" test), and `name_english 'Berserk'`. Run `scanOne(BERSERK_1)`, then GET `scan.single`. Assert `assertSee('data-series-english', false)`, `assertSee('Berserk')`, and that `value="ベルセルク"` is still there.
  - Also assert that the existing "pre-fills" test page has no `data-series-english`.

- [ ] **Step 2: Run it to see it fail.** Expected: FAIL.

- [ ] **Step 3: Implement.** In the component's `@php` block, when `$suggestion` is set, look up `Series::query()->ownedBy(auth()->user())->where('external_source', $suggestion['reference']['source'])->where('external_id', $suggestion['reference']['externalId'])->value('name_english')`. In the legend, after the source hint, render `<span data-series-english>· your name: {{ $english }}</span>` when it is set.

- [ ] **Step 4: Run the tests to see them pass.** Run `tests/Feature/Series/SeriesScanTest.php`. Expected: PASS.

- [ ] **Step 5: Update the guidelines and commit.** Add one line to `.ai/guidelines/physical-library.md`, in the series section: a series' `name_english` is the owner's display name, shown as the title wherever it is set, and never used for lookups. Run Pint, then commit with the message `Show the owner's English series name while scanning`.
