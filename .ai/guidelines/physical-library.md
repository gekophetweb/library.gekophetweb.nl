# Physical Library — Project Guidelines

Project-specific instructions for AI agents working on this Laravel application.
These rules are always-on context; follow them alongside Boost's Laravel/package guidelines.
When a Laravel/package convention is unclear, use the Boost **Search Docs** MCP tool
rather than guessing.

---

## 1. Purpose

The app lets a user register and manage every **physical object they own** — a
personal collection catalogue. Objects are grouped into three top-level
categories: **Books**, **Music**, and **Games**. Data is entered primarily by
**scanning** items (barcode/ISBN etc.), which triggers lookups against external
catalogue APIs. All data is also exposed through a public API consumed by a
companion phone app.

Every object belongs to exactly one **user**. There is no shared/global library —
scope everything to the authenticated user.

---

## 2. Authentication

- Login is via **SSO** (single sign-on). Treat SSO as the only production auth path.
- In the **local/dev environment**, SSO must be **skippable**. When skipped,
  authenticate as the seeded dev user **`jaap.lam@gmail.com`**.
- Gate the skip behind an environment check (e.g. `app()->environment('local')`
  plus an explicit config flag such as `config('auth.dev_sso_bypass')`). Never allow
  the bypass in production.
- Do not hardcode the dev email in scattered places — put it in config/seeder so it
  is defined once.

---

## 3. Domain model

### 3.1 Core object

Every collectible object shares these fields:

| Field             | Required | Notes                                            |
|-------------------|----------|--------------------------------------------------|
| `user`            | yes      | Owner (belongsTo User)                           |
| `name`            | yes      | Original-language title                          |
| `name_romanized`  | yes      | Romanized title                                  |
| `name_english`    | no       | English title if available                       |
| `category`        | yes      | Books / Music / Games                            |
| `sub_category`    | if avail | e.g. Manga, Novel, CD, Vinyl (see §3.2)          |
| `languages`       | yes      | One or more languages (many-to-many)             |
| `publisher`       | no       | belongsTo Publisher                              |
| `release_date`    | no       | Date                                             |
| `serie`           | no       | Series/collection membership                     |
| `genres`          | no       | Many-to-many, constrained by category (see §3.4) |

Use a shared base for these attributes. Prefer **single-table or STI-style
polymorphism with category-specific extension tables** over duplicating columns.
Category-specific data (§3.3) lives in its own related table, not on the base object.

### 3.2 Categories & subcategories

Model categories and subcategories as **data** (a `categories` table with an
optional self-referencing parent, or a `categories` + `sub_categories` pair), not as
enums hardcoded in PHP — new subcategories should be addable without a code change.

Seed the initial hierarchy:

- **Books** → Manga, Novels
- **Music** → CD, Vinyl, Tape, Minidisc
- **Games** → (no subcategories at launch)

> Note: the source spec lists a Music subcategory "Type" — this is a typo for
> **Tape**. Seed it as Tape.

### 3.3 Category-specific data

- **Music**: an **Artist** must be linkable to the object.
- **Books**: series info (`serie_name`, `serie_name_romanized`,
  `serie_name_english`) and a `volume` / book number.
- **Games**: a **Platform** (PC, or which console).

### 3.4 Supporting entities

**Publisher**
- `name` (required), `name_romanized` (required), `name_english`, `logo`

**Artist**
- `name` (required), `name_romanized` (required), `name_english`, `languages`

**Genre**
- Each genre is **linked to a category**. A genre valid for Books is not
  automatically valid for Games. Enforce this when attaching genres to an object.
- Users can **add and remove** genres on their own linked objects.

Keep `name` / `name_romanized` / `name_english` naming consistent across Publisher,
Artist, Object, and Series so the same accessor/formatting logic is reusable.

---

## 4. Scanning workflow

Scanning is the primary data-entry path and must support **single** and **batch**
modes. The user chooses the mode up front.

Opening the scanner shows a **popup/modal** that requires the user to:
1. Select a **category** (and a **subcategory** if the category has one), and
2. Select the **language**.

These selections scope the subsequent external lookup.

### 4.1 Single-item scan

1. Look the item up via the relevant external API (§5).
2. Check whether the item is **already linked to the user**.
3. If not linked, **prompt the user to add it** to their account.

### 4.2 Multiple-item (batch) scan

1. For each scanned item, check whether it is **already linked to the user**.
2. If not yet linked, add it to a **working list** (a staging list, not yet
   persisted to the account).
3. Items on the list must be **removable** before commit.
4. Committing the list adds all items to the account. **This commit runs
   asynchronously** — dispatch it to a **queued job** (per item or per batch), do
   not block the request. Report progress/results back to the user.

### 4.3 External API call logging

**Every** call to an external catalogue API must be **logged** — store request
context (which provider, the query/scan value, the user, category/language) and the
outcome (success/failure, timing). This log is a first-class table, not just the
application log file, so lookups can be audited and debugged.

---

## 5. External catalogue APIs

Lookups are provider-specific per category. Build these behind a common
**interface/contract** (e.g. `CatalogueLookup`) with one implementation per provider,
resolved by category, so new providers can be added without touching call sites.

- **Books**: Rakuten, Google Books
- **Music**: _(provider TBD — leave the slot in place; do not invent one)_
- **Games**: _(provider TBD — leave the slot in place; do not invent one)_

Where a category has multiple providers (Books), support querying them in a defined
order and merging/deduplicating results.

**Series lookup (Books)** is a separate contract, `SeriesLookup` ("which series is this
and which volumes does it have?"), resolved by scan language by `SeriesLookupManager`:

- **Japanese**: NDL Search (国立国会図書館) OpenSearch → Rakuten `seriesName` → title parsing
- **English / Latin script**: Open Library → Wikidata → Google Books `seriesInfo` →
  title parsing

NDL, Open Library and Wikidata need **no API key** and are not per-user keys; only the
Rakuten / Google Books fallbacks use the user's stored keys (§5.1). See
[`series-detection-from-isbn.md`](../plans/series-detection-from-isbn.md).

### 5.1 Per-user API keys

Each user links **their own API keys** for these providers through **user settings**.
- Store keys per user, **encrypted at rest** (Laravel encrypted casts).
- Lookups use the **authenticated user's** keys, never a shared global key.
- Handle the "no key configured for this provider" case gracefully in the UI.

---

## 6. Public API + phone app

- All catalogue data must be reachable through a **public HTTP API**; a phone app is
  a first-class consumer.
- Design endpoints API-first (resourceful, versioned, JSON). Use API Resources for
  serialization and token-based auth suitable for a mobile client.
- Every API response is scoped to the authenticated user's objects.

---

## 7. Conventions for agents

- Model category/subcategory/genre/language as **data + relationships**, never as
  hardcoded enums or `if`-chains on strings.
- Keep the required-field rules from §3 in **Form Requests** (validation), not only
  in migrations.
- Batch commits and any external-lookup-heavy work go through **queues**, not
  synchronous requests.
- Persist external API interactions to the **API call log table** (§4.3) on every
  call.
- Respect the user scope on every query — default to `where user_id = auth id`.
- Prefer Eloquent relationships and Laravel conventions; consult Boost's **Search
  Docs** tool for the installed versions before introducing a pattern.
- Fields marked required (`*` in the spec / "yes" in the tables above):
  `name`, `name_romanized`, `category`, `languages` on objects;
  `name` + `name_romanized` on Publisher and Artist.

---

## 8. Runtime & Docker

The app runs as **separate website and database containers**, defined in
`docker-compose.yml` at the repo root:

- `web` — nginx + PHP-FPM (`serversideup/php:8.4-fpm-nginx` base, built from
  `Dockerfile`), port 8080. The only container that runs migrations and caches on boot
  (`AUTORUN_ENABLED`).
- `queue` — same image, `php artisan queue:work`. Batch scan commits and other queued
  work (§4.2, §7) depend on it; don't assume work runs synchronously.
- `scheduler` — same image, `php artisan schedule:work`. Required for `Prunable`
  models (`LibraryShare`, `PairingCode`) to be pruned.
- `db` — `mariadb:11.4`, utf8mb4. Hostname `db` inside the compose network.

Rules for agents:

- `DB_HOST` / `DB_PORT` / `DB_CONNECTION` are set by Compose; don't hardcode them
  elsewhere. Credentials come from `.env` and are shared by the app and `db` services.
- Run artisan, tests and Pint inside the container:
  `docker compose exec web php artisan …`.
- A new PHP extension goes into the `install-php-extensions` line of the `Dockerfile`,
  not into ad-hoc container commands.
- A new long-running process (e.g. Reverb, Horizon) becomes its own service reusing the
  `x-app` anchor, not a second process inside `web`.
- Persistent files live in `storage/app` (volume `app-storage`); anything written
  elsewhere in the container is lost on rebuild.
- The Docker host is reached **only through a Cloudflare Tunnel** (`cloudflared` on the
  host → `http://localhost:8080`). Consequences:
  - Never add a published port, reverse proxy or certificate setup for public access;
    TLS terminates at Cloudflare.
  - `web` is published on `${APP_BIND:-127.0.0.1}:${APP_PORT:-8080}`. Keep the
    localhost default; LAN access is an opt-in via `APP_BIND=0.0.0.0` in `.env`
    (documented in the README). The database stays on `127.0.0.1` always.
  - The app must trust proxies (`trustProxies(at: '*')` in `bootstrap/app.php`) so
    `https` URLs, signed URLs and `request()->ip()` are correct — rate limits on share
    links (`throttle:60,1`) depend on the real client IP.
  - Build absolute URLs from `APP_URL` (`https://library.gekophetweb.nl`), never from
    the internal host or port. SSO callbacks use that public hostname.
  - `/api/*` (phone app) and `/s/*` (share links) must stay reachable without a
    Cloudflare Access login.
- Production hosting may also be Laravel Cloud (see `.ai/skills/deploying-laravel-cloud`);
  keep the app free of Docker-only assumptions so it runs on both.

---

## 9. Open items (do not silently invent)

These are unspecified in the source and should be confirmed before implementation:

- Music and Games external providers.
- Which SSO provider is used in production.
- Barcode/scan format expected per category (ISBN, EAN, UPC, catalogue number).
