# Plan — Share a library with viewers who have no account

Status: **not built in this repository** (checked 2026-11-10). Written 2026-09-20
against an earlier codebase where it was implemented; the files named below do not
exist here yet, and §11 records how it was built there. Execute after
[`foundation.md`](foundation.md) and [`own-wishlist.md`](own-wishlist.md).

> **Open question (2026-11-10).** The request "share options with only read rights" is
> covered by this plan's read-only links. If sharing with a **named account** that gets
> read-only access is also wanted (§10 keeps it out of scope), that is a second share
> type to add here: a `library_share_user` pivot to an existing user, checked through the
> same `exposes()` gate.

Related: [`own-wishlist.md`](own-wishlist.md) — shared links show wishlist items the same
way the owner sees them.

---

## 1. Design

A **capability URL**: the owner mints an unguessable link in settings, hands it to
whoever they like, and that person browses the collection read-only without an account.
Revoking the link is deleting the row.

### Decisions (confirmed with the user)

1. **Scope is per link, three shapes.** Whole library, a single category, or a single
   series. One polymorphic `library_shares` row covers all three, so adding a narrower
   scope later is a new morph target rather than a second feature.
2. **Protections: revoke + optional expiry only.** No password, no view counter, no
   per-share wishlist hiding. Each of those is one nullable column on the same row if
   it is ever wanted.
3. **Read-only is structural, not cosmetic.** No write route exists under the share
   prefix, and a share request never establishes an authenticated identity, so a viewer
   cannot act even if a stray form were rendered.

**Why a token table and not a signed URL.** A signed URL cannot be revoked without
rotating the app key, and the owner has to be able to see and re-copy an existing link
from settings.

**Why the token is stored in plaintext.** Unlike `PairingCode` — single-use, short
lived, never shown twice — a share link is long lived and must be re-readable in
settings. Hashing it would buy nothing: anyone with database read access already has
the library the link exposes.

---

## 2. Phase 1 — Data + model

1. Migration `create_library_shares_table`:

   | column | notes |
   |---|---|
   | `user_id` | fk to `users`, cascade on delete |
   | `token` | 32-char unguessable string, unique |
   | `label` | nullable, e.g. "For my brother" |
   | `shareable_type` / `shareable_id` | nullable morph to `Category` or `Series`; **both null = whole library** |
   | `expires_at` | nullable datetime |
   | timestamps | |

2. `app/Models/LibraryShare.php` — `belongsTo(User)`, `morphTo(shareable)`,
   `#[Scope] usable()` (`expires_at` null or future), `isUsable()`, `url()`, and
   `Prunable` on `expires_at < now()->subMonth()`, following `PairingCode`.
3. `database/factories/LibraryShareFactory.php` — default whole-library, never expiring,
   with `forCategory()`, `forSeries()` and `expired()` states.

## 3. Phase 2 — Scope enforcement

4. `LibraryShare::exposes(Model $model): bool` — the single gate every share route runs
   through:
   - **library share** — any `Category` / `Series` / `CollectionObject` owned by
     `$share->user`;
   - **category share** — objects whose `category_id` is X or whose sub-category's
     parent is X, the series those objects sit in, and that category alone;
   - **series share** — objects with `serie_id` Y, that series, and its category as a
     breadcrumb only.
5. `LibraryShare::objectsQuery(): Builder` — the pre-scoped `$share->user->objects()`
   query every share controller builds on, so a listing can never widen past its scope
   by accident.

## 4. Phase 3 — Resolution + routes

6. `app/Http/Middleware/ResolveLibraryShare.php`, aliased `share`. Resolves
   `{shareToken}`, `abort(404)` when it is unknown *or* lapsed (a bad token and an
   expired one must look identical), then binds a scoped
   `App\Support\Library\LibraryContext` holding the share and calls
   `URL::defaults(['shareToken' => $token])` so views need not thread the token through
   every `route()` call.
7. `routes/web.php`, outside the `auth` group, GET only:

   ```php
   Route::middleware(['share', 'throttle:60,1'])
       ->prefix('s/{shareToken}')->name('share.')->group(function () {
           Route::get('/', [Share\LibraryController::class, 'index'])->name('index');
           Route::get('categories/{category}', [Share\CategoryController::class, 'show'])->name('category');
           Route::get('series/{series}', [Share\SeriesController::class, 'show'])->name('series');
           Route::get('objects/{object}', [Share\ObjectController::class, 'show'])->name('object');
       });
   ```

   A category-scoped link's index redirects to its category page, a series-scoped one to
   its series page.

## 5. Phase 4 — Controllers (extract, do not duplicate)

The owner controllers build their view data inline. Lift it into support classes so the
two paths cannot drift apart:

8. `app/Support/Library/CategoryShelf.php` — from the body of `CategoryController@show`
   (owned / series / standalone / wishlist grouping), taking a scoped `Builder`.
9. `app/Support/Library/CategoryCounts.php` — from the per-category counts query in
   `DashboardController@index`. The scan-batch half stays owner-only.
10. `SeriesShelf` already has this shape and is reused untouched.
11. `app/Http/Controllers/Share/{Library,Category,Series,Object}Controller.php` — each
    one `abort_unless($share->exposes($model), 404)`, build through the support class,
    return the shared view. The series page drops `canSync`, the author / publisher
    suggestions and the edit-form data.

## 6. Phase 5 — Views

One set of blades, two link modes:

12. `app/Support/Library/LibraryLinks.php`, resolved from `LibraryContext` and shared
    into every view by a view composer as `$links`: `category()`, `series()`,
    `object()`, `home()` each return the owner route or the share route, plus
    `canEdit()` (false for shares).
13. `library/category.blade.php`, `library/series.blade.php`, `objects/show.blade.php` —
    swap `route('library.*' | 'objects.show')` for `$links->…`, and wrap every editing
    affordance in `@if ($links->canEdit())`: the series details form and sync button,
    the ownership form, the item edit form, and the `artists.edit` / `publishers.edit` /
    `objects.lookups` links, which become plain text for viewers.
14. `components/layout.blade.php` — a `:share` prop swapping the nav for a
    "**{owner}'s collection** · read only" bar and emitting
    `<meta name="robots" content="noindex, nofollow">`. The existing `@auth` / `@else`
    block already degrades correctly for a guest.
15. `resources/views/share/index.blade.php` — the dashboard's category grid without the
    scan and batch sections.

## 7. Phase 6 — Owner UI

16. A "Shared links" section in `settings/edit.blade.php`: each share's label, scope
    ("Whole library" / category / series), full URL with a copy button, expiry or
    "Never", and a revoke button; plus a create form (scope select, optional label,
    optional expiry date).
17. `app/Http/Controllers/Settings/LibraryShareController.php` (`store` / `destroy`) and
    `StoreLibraryShareRequest`, validating that a chosen category or series actually
    belongs to the user. Routes sit with the other settings routes inside `auth`.
18. A "Share this series" / "Share this category" button on those two pages when
    `$links->canEdit()`, posting to the same endpoint with the scope pre-filled — the
    path people will actually use.

## 8. Leak checks

- The public API (`routes/api.php`) stays token-authed and untouched; shares are
  web-only.
- A share request creates no session identity, so `auth` routes keep redirecting to
  login.
- Every share query starts from `LibraryShare::objectsQuery()`, never `auth()->user()`.
- Route-model binding for `Category`, `Series` and `CollectionObject` is global, so
  `exposes()` is the only thing standing between a token and another user's data. It
  gets direct tests.

## 9. Tests

`tests/Feature/Sharing/ShareLinkTest.php`

- a guest holding a library token reaches the index, a category, a series and an item;
- unknown, expired and revoked tokens all 404;
- a category-scoped token 404s on an item in another category; likewise series-scoped;
- no token ever reaches another user's object, even within scope;
- share pages render no edit form, no ownership form and no POST target;
- share pages carry the `noindex` meta.

`tests/Feature/Sharing/ManageSharesTest.php`

- the owner mints a library / category / series share and the link shows in settings;
- a share for someone else's category fails validation;
- revoking deletes it and the link then 404s;
- another user cannot revoke it;
- lapsed shares prune.

`OwnershipScopeTest` and `BrowseLibraryTest` are re-run to prove the phase-4 extraction
changed nothing for owners.

## 10. Out of scope

- Password-protected links, view counters, per-share wishlist hiding.
- Sharing to a **named account** with view rights — this plan is unauthenticated
  capability URLs only.
- Any change to the phone-app API.

---

## 11. Deviations from the plan, as built

- **`LibraryContext` holds no state.** It reads the share back off the current request,
  where the middleware put it, instead of being a container singleton. A long-lived
  instance that remembered the last share would answer the next request wrongly.
- **`$links` is bound with `View::share`**, not a view composer — the object is
  stateless, so one instance serves every request.
- **A series-scoped link exposes no category at all.** The plan left the surrounding
  category as a breadcrumb; in practice a breadcrumb that 404s is worse than none, so
  `LibraryLinks::category()` returns `null` there and the view omits it.
  `LibraryLinks::hasHome()` likewise suppresses the "back" link on a link whose landing
  page *is* the page you are on.
- **The scope is one select, not three fields.** The settings form posts
  `library` / `category:7` / `series:3` and `StoreLibraryShareRequest::prepareForValidation()`
  splits it, which is what lets the "Share this category" buttons post a single hidden
  input.
- **`Relation::morphMap`, not `enforceMorphMap`.** Sanctum's own token morph is outside
  this map and enforcing would break it.
- **Owner-facing copy is branched, not rewritten.** "You own 3 of 5 volumes" and "1 on
  your wishlist" stay exactly as they were for the owner — existing tests assert on that
  wording — and viewers get a neutral variant.
