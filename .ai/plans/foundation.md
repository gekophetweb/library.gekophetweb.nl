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

Requirement (user, 2026-11-10): **several SSO providers can be chosen and set up**, and
SSO can be **skipped by logging in with a temporary code sent by e-mail**.

The login page shows one button per enabled SSO provider plus an "E-mail me a login
code" option. There is no password anywhere.

### 1a. SSO providers, configured in the app

1. Laravel Socialite, plus `socialiteproviders/microsoft` and a generic OpenID Connect
   driver, so an admin can add, for example, Google + Microsoft + a self-hosted
   Authentik / Keycloak.
2. Migration `create_sso_providers_table`:

   | column | notes |
   |---|---|
   | `driver` | `google`, `microsoft`, `github`, `oidc` — a PHP enum `SsoDriver`: closed set, each case is code |
   | `name` | button label, e.g. "Work account" |
   | `client_id` | |
   | `client_secret` | `encrypted` cast |
   | `settings` | JSON, driver-specific: Microsoft `tenant`, OIDC `issuer` (discovery URL), `scopes` |
   | `enabled` | boolean |
   | `allow_registration` | boolean, default false: may an unknown e-mail create an account via this provider? |
   | `sort` | button order |

3. `App\Services\Auth\SsoProviderFactory` builds the Socialite driver at runtime from a
   row (`Socialite::buildProvider(...)`), so providers are data, not `config/services.php`
   entries. Redirect URL per provider: `/auth/sso/{provider}/callback`, built from
   `APP_URL` (guidelines §8).
4. Admin UI `/admin/sso` (`can:admin`): list, add, edit, enable/disable, reorder, and a
   "Test login" button. The callback URL to register at the identity provider is shown
   with a copy button. The secret is write-only: never rendered back.
5. Migration `create_user_identities_table` — `user_id`, `sso_provider_id`, `subject`
   (the provider's stable user id), `email`; unique on `['sso_provider_id', 'subject']`.
   One user can link several providers.
6. Callback flow (`SsoCallbackController`):
   - known `(provider, subject)` → log that user in;
   - else a user with the same **verified** e-mail → link the identity and log in;
   - else, if `allow_registration` → create the user and identity;
   - else → back to the login page with "No account for this e-mail".
   - `session()->regenerate()` on every successful login; the OAuth `state` is checked by
     Socialite.
7. Settings → "Linked sign-in methods": a user sees, links and unlinks their identities.
   They cannot unlink the last one if e-mail codes are off.

### 1b. Skip SSO: e-mail login code

8. Migration `create_login_codes_table` — `email`, `code_hash`, `expires_at`,
   `attempts`, `used_at`, `ip`; `Prunable` after a day.
9. Flow: enter **any** e-mail → mail a **6-digit code** that expires after
   **10 minutes** and works **once** → enter the code → logged in.
   - **Any e-mail can sign up this way** (user, 2026-11-10), so people whose e-mail is
     not covered by any configured SSO provider can still get an account. Entering a
     correct code for an e-mail with no account **creates the account** on the spot;
     the code proves the address, so `email_verified_at` is set.
   - A new account lands on a short "Your name" step before the dashboard; nothing else
     is asked.
   - The account is only created **after** the code is verified, never when the code is
     requested, so typing a stranger's address leaves no account behind.
   - The response is the same for known and new e-mails ("Check your inbox for a code"),
     so the form cannot be used to find out who has an account.
   - Later, the user can link an SSO provider in settings; an SSO login with the same
     verified e-mail links to this account automatically (step 6).
   - The code is stored hashed (`Hash::make`) and compared with `Hash::check`; a new
     request invalidates the previous code.
   - Max 5 wrong attempts per code, then it is burnt. Rate limits: 3 code requests per
     e-mail per 10 minutes and 10 per IP per hour (`RateLimiter`, real IP via the trusted
     proxy, guidelines §8). Because anyone can now request a code for any address, these
     limits are also what stops the form being used to flood someone's inbox — they are
     not optional.
   - The mail (`LoginCodeMail`, queued) contains the code and a one-click signed link
     to the same verification, valid as long as the code.
10. The e-mail code login can be switched off by an admin (`app_settings` row
    `auth.email_codes_enabled`, default on). It cannot be switched off while no SSO
    provider is enabled, so nobody gets locked out. A second setting,
    `auth.email_registration_enabled` (default **on**), lets an admin close sign-up by
    e-mail code later while existing users keep logging in with codes.
11. Mail: SMTP settings come from `.env` (`MAIL_*`). The dev stack gets a **Mailpit**
    service so codes can be read at `http://localhost:8025`.

### 1c. Dev bypass and bootstrap

12. The local-only bypass from the guidelines (§2) stays: in `local` with
    `config('auth.dev_sso_bypass')` on, a "Sign in as dev user" button logs in as the
    seeded dev user. Never available outside `local`.
13. First admin: with no providers set up yet, the first login is by e-mail code. The
    command `users:make-admin {email}` creates the user if needed and makes them admin;
    after that the admin sets up SSO in `/admin/sso`.

### 1d. Roles

14. Migration `add_is_admin_to_users_table` — `boolean('is_admin')->default(false)`.
   A boolean, not a roles table: there is one privileged role and nothing else asks
   for more. Cast to `bool` on `User`.
15. Gate `admin` (`fn (User $user) => $user->is_admin`) and a `can:admin` middleware
   group for admin routes.

## 3. Phase 2 — Admin user list

Requirement (user, 2026-11-10): **in the user list, administrators can see themselves
and the other admin users** — the list is not filtered to "normal" users only.

16. `app/Http/Controllers/Admin/UserController.php@index`, route `GET /admin/users`
   (`admin.users.index`) behind `auth` + `can:admin`.
   - Lists **every** user, admins included, the signed-in admin included. No
     `where('is_admin', false)` and no `whereKeyNot(auth()->id())`.
   - Columns: name, email, an **Admin** badge, a **You** marker on the current user's
     row, **Total items** — the number of items that user owns, all categories together
     (user, 2026-11-10) — and created at. One `withCount` query, no N+1. Once
     [`own-wishlist.md`](own-wishlist.md) lands the total counts owned items only;
     wishlist items are not included.
   - Ordered admins first, then by name; paginated 25.
   - A filter All / Admins / Users (`?role=`), default All.
17. The list is **read-only towards collections**: an admin sees *that* a user exists and
   how many items they have, never the items themselves. Everything else stays scoped to
   the owner (§1, §7).
18. Promoting / demoting from the list (`PATCH /admin/users/{user}`) — see open
   question 2. If built: an admin cannot remove their **own** admin flag, and the last
   admin cannot be demoted, so the app can never end up with no admin.
19. Non-admins get 403 on `/admin/*`; the nav link only renders for admins.

## 4. Phase 3 — Core catalogue data (§3)

20. `categories` (self-referencing `parent_id`) seeded Books → Manga, Novels; Music → CD,
   Vinyl, Tape, Minidisc; Games (§3.2). `languages`, `genres` (with `category_id`),
   `publishers`, `artists`, `platforms`.
21. `objects` (`CollectionObject`) with the shared fields from §3.1, `barcode`,
    `user_id`, `category_id`, `sub_category_id`, `serie_id`; pivots `object_language`
    and `genre_object` (genre must belong to the object's category — validated).
22. Extension tables `book_details` (`artist_id`, `volume`), `music_details`
    (`artist_id`), `game_details` (`platform_id`).
23. `series` (`user_id`, name triple, `artist_id`, `publisher_id`) and `series_volumes`.
    Use the column shapes from [`series-detection-from-isbn.md`](series-detection-from-isbn.md)
    phase 3 from the start (decimal `volume_sort`, `edition`, `isbns`,
    `external_source` / `external_id`), so no follow-up migration is needed.
24. `#[Scope] ownedBy(User)` on every user-owned model; Form Requests carry the required
    fields from §7.

## 5. Phase 4 — Library pages

25. Dashboard (categories with counts), category page (series + standalone items),
    series page (volumes), item page with edit form. These are the pages the wishlist,
    search and share plans extend — keep their view data in support classes
    (`CategoryShelf`, `CategoryCounts`, `SeriesShelf`) from day one so the share plan's
    phase 4 extraction is already done.

## 6. Phase 5 — Scanning and per-user keys

26. `CatalogueLookup` contract + Rakuten and Google Books providers (§5), every call
    through `ApiCallLogger` (§4.3).
27. Per-user API keys in settings, encrypted casts (§5.1). Also unblocks the Google
    Books / Hardcover series providers.
28. Scanner modal (category, subcategory, language), single scan and batch scan with a
    queued commit (§4).

## 7. Phase 6 — Public API

29. Sanctum tokens for the phone app, `api/v1` resources for objects, series, categories
    (§6), all user-scoped.

---

## 8. Tests (Pest)

- Admin list: an admin sees their own row (marked *You*), every other admin and every
  normal user; the role filter works; a non-admin gets 403; a guest is redirected to
  login; no collection item of another user is rendered on the page.
- If promote/demote is built: an admin cannot demote themselves; the last admin cannot
  be demoted; a non-admin cannot call the endpoint.
- SSO providers: a row per driver builds the right Socialite driver; disabled providers
  get no button and their redirect/callback 404; the secret is encrypted in the
  database and never rendered; callbacks are faked (`Socialite::fake()` / mocked user).
- SSO callback: a known identity logs in; an unknown identity with a matching verified
  e-mail links and logs in; an unknown e-mail is refused unless `allow_registration`;
  one user can link two providers; the session id changes on login.
- E-mail code: `Mail::fake()` — a code is mailed to an existing user and to a new
  e-mail, with the same response; requesting a code creates no user; verifying it for a
  new e-mail creates a verified user and shows the name step; with
  `auth.email_registration_enabled` off a new e-mail gets the same response but no mail
  and no account; the code works once; an expired code, the sixth
  wrong attempt and a superseded code are all rejected; the rate limits trigger; the
  signed link logs in and expires with the code; codes are stored hashed.
- E-mail codes cannot be switched off while no SSO provider is enabled.
- Dev bypass works only in `local` with the flag on, never in `production`.
- Ownership scoping: another user's objects, series and artists never leak through any
  page or API endpoint.

## 9. Open questions (confirm before implementing)

1. **SSO providers — decided 2026-11-10:** several, configurable by an admin in the app,
   plus e-mail login codes to skip SSO (phase 1). Still to confirm: which drivers to
   ship first (planned: Google, Microsoft, GitHub, generic OIDC).
   **Registration — decided 2026-11-10:** anyone can sign up with an e-mail code, for any
   address (step 9); SSO providers create accounts only when `allow_registration` is on.
2. **Admin actions.** Is the user list view-only, or can an admin also promote / demote
   admins and remove users from it?
3. **Hidden users before this change?** The requirement reads as if admins were hidden
   from the list somewhere. If that refers to an existing app elsewhere, its code should
   be brought into this repository instead of rebuilding phases 1–6.
