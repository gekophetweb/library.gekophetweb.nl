# Plan — Foundation: auth, admin user list and the core catalogue

Status: **not started**. Written 2026-11-10.

Every other plan in this folder assumes an application that is **not in this
repository**: `objects` / `CollectionObject`, categories, series, artists, publishers,
the scanner, the library pages, settings, the V1 API. The repository today has only the
Laravel skeleton, `User`, the API call log and the series lookup code from
[`series-detection-from-isbn.md`](series-detection-from-isbn.md) phases 1–2. This plan
builds that base, so the feature plans can be executed on top of it.

Rules come from [`physical-library.md`](../guidelines/physical-library.md); section
numbers below (§n) refer to it.

---

## 1. Order of work

| # | Plan | Depends on |
|---|---|---|
| 1 | **This plan**, phases 1–6 | — |
| 2 | [`own-wishlist.md`](own-wishlist.md) | 1 |
| 3 | [`search-title-and-creator.md`](search-title-and-creator.md) | 1 (2 for the ownership badge) |
| 4 | [`search-catalogue-not-owned.md`](search-catalogue-not-owned.md) | 1, 2, per-user keys (phase 5 here) |
| 5 | [`share-library-read-only.md`](share-library-read-only.md) | 1, 2 |
| 6 | [`series-detection-from-isbn.md`](series-detection-from-isbn.md) phases 3–6 | 1, 2 |

Each phase ends with its tests green and its own commit on a `feature/*` branch.

---

## 2. Phase 1 — Authentication and roles

1. **SSO** as the only production login (§2), with the local-only bypass
   (`app()->environment('local')` + `config('auth.dev_sso_bypass')`) signing in as the
   dev user from config / seeder. The SSO provider is an open item (§9) — build the
   bypass and the user model first, leave the provider behind one config switch.
2. Migration `add_is_admin_to_users_table` — `boolean('is_admin')->default(false)`.
   A boolean, not a roles table: there is one privileged role and nothing else asks
   for more. Cast to `bool` on `User`.
3. Gate `admin` (`fn (User $user) => $user->is_admin`) and a `can:admin` middleware
   group for admin routes.
4. Artisan command `users:make-admin {email}` to promote the first admin (there is no
   UI to become admin from nothing). The seeded dev user is an admin.

## 3. Phase 2 — Admin user list

Requirement (user, 2026-11-10): **in the user list, administrators can see themselves
and the other admin users** — the list is not filtered to "normal" users only.

5. `app/Http/Controllers/Admin/UserController.php@index`, route `GET /admin/users`
   (`admin.users.index`) behind `auth` + `can:admin`.
   - Lists **every** user, admins included, the signed-in admin included. No
     `where('is_admin', false)` and no `whereKeyNot(auth()->id())`.
   - Columns: name, email, an **Admin** badge, a **You** marker on the current user's
     row, item count (owned items only, once [`own-wishlist.md`](own-wishlist.md) lands),
     created at.
   - Ordered admins first, then by name; paginated 25.
   - A filter All / Admins / Users (`?role=`), default All.
6. The list is **read-only towards collections**: an admin sees *that* a user exists and
   how many items they have, never the items themselves. Everything else stays scoped to
   the owner (§1, §7).
7. Promoting / demoting from the list (`PATCH /admin/users/{user}`) — see open
   question 2. If built: an admin cannot remove their **own** admin flag, and the last
   admin cannot be demoted, so the app can never end up with no admin.
8. Non-admins get 403 on `/admin/*`; the nav link only renders for admins.

## 4. Phase 3 — Core catalogue data (§3)

9. `categories` (self-referencing `parent_id`) seeded Books → Manga, Novels; Music → CD,
   Vinyl, Tape, Minidisc; Games (§3.2). `languages`, `genres` (with `category_id`),
   `publishers`, `artists`, `platforms`.
10. `objects` (`CollectionObject`) with the shared fields from §3.1, `barcode`,
    `user_id`, `category_id`, `sub_category_id`, `serie_id`; pivots `object_language`
    and `genre_object` (genre must belong to the object's category — validated).
11. Extension tables `book_details` (`artist_id`, `volume`), `music_details`
    (`artist_id`), `game_details` (`platform_id`).
12. `series` (`user_id`, name triple, `artist_id`, `publisher_id`) and `series_volumes`.
    Use the column shapes from [`series-detection-from-isbn.md`](series-detection-from-isbn.md)
    phase 3 from the start (decimal `volume_sort`, `edition`, `isbns`,
    `external_source` / `external_id`), so no follow-up migration is needed.
13. `#[Scope] ownedBy(User)` on every user-owned model; Form Requests carry the required
    fields from §7.

## 5. Phase 4 — Library pages

14. Dashboard (categories with counts), category page (series + standalone items),
    series page (volumes), item page with edit form. These are the pages the wishlist,
    search and share plans extend — keep their view data in support classes
    (`CategoryShelf`, `CategoryCounts`, `SeriesShelf`) from day one so the share plan's
    phase 4 extraction is already done.

## 6. Phase 5 — Scanning and per-user keys

15. `CatalogueLookup` contract + Rakuten and Google Books providers (§5), every call
    through `ApiCallLogger` (§4.3).
16. Per-user API keys in settings, encrypted casts (§5.1). Also unblocks the Google
    Books / Hardcover series providers.
17. Scanner modal (category, subcategory, language), single scan and batch scan with a
    queued commit (§4).

## 7. Phase 6 — Public API

18. Sanctum tokens for the phone app, `api/v1` resources for objects, series, categories
    (§6), all user-scoped.

---

## 8. Tests (Pest)

- Admin list: an admin sees their own row (marked *You*), every other admin and every
  normal user; the role filter works; a non-admin gets 403; a guest is redirected to
  login; no collection item of another user is rendered on the page.
- If promote/demote is built: an admin cannot demote themselves; the last admin cannot
  be demoted; a non-admin cannot call the endpoint.
- SSO bypass works only in `local` with the flag on, never in `production`.
- Ownership scoping: another user's objects, series and artists never leak through any
  page or API endpoint.

## 9. Open questions (confirm before implementing)

1. **SSO provider** for production (§9 of the guidelines).
2. **Admin actions.** Is the user list view-only, or can an admin also promote / demote
   admins and remove users from it?
3. **Hidden users before this change?** The requirement reads as if admins were hidden
   from the list somewhere. If that refers to an existing app elsewhere, its code should
   be brought into this repository instead of rebuilding phases 1–6.
