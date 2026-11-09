# library.gekophetweb.nl

A personal catalogue of every **physical object you own** — Books, Music and Games.
Items are added mainly by **scanning** barcodes / ISBNs, which triggers lookups
against external catalogue APIs. All data is also exposed through a versioned public
API used by a companion phone app.

Built with Laravel on MariaDB.

## Features

- **Collection** — items grouped by category → subcategory → series, with genres,
  languages, publishers and artists. Titles are stored as original, romanized and
  English names.
- **Scanning** — single or batch mode. Batch commits run as queued jobs; every external
  catalogue call is logged to its own table.
- **Catalogue providers** — Rakuten and Google Books for Books (Music and Games
  providers to be decided). Each user stores their own, encrypted, API keys.
- **Own / Wishlist** — every item is either owned or wanted; scanning a wanted item
  promotes it to owned.
- **Search** — one box across titles, series and creators (All / Title / Creator).
- **Read-only share links** — revocable, optionally expiring links to the whole
  library, one category or one series, for viewers without an account.
- **Public API** — `/api/v1`, token auth, scoped to the authenticated user.

## Running with Docker

The website and the database run in **separate containers**:

| Service     | Image                              | Role                                             |
|-------------|------------------------------------|--------------------------------------------------|
| `web`       | built from `Dockerfile`            | nginx + PHP-FPM serving the app on port 8080     |
| `queue`     | same image                         | `queue:work` — batch scan commits, lookups        |
| `scheduler` | same image                         | `schedule:work` — pruning of expired shares etc. |
| `db`        | `mariadb:11.4`                     | database, data in the `db-data` volume           |

The app image is a three-stage build: Composer dependencies, Vite assets (Node 22),
then the [`serversideup/php:8.4-fpm-nginx`](https://serversideup.net/open-source/docker-php/)
runtime with `intl` and `bcmath` added.

### First start

```bash
cp .env.example .env
```

Set at least these in `.env` (Compose reads the same file for both the app and the
database):

```dotenv
APP_KEY=            # generate below
APP_URL=http://localhost:8080
DB_DATABASE=library
DB_USERNAME=library
DB_PASSWORD=change-me
DB_ROOT_PASSWORD=change-me-too
QUEUE_CONNECTION=database
```

`DB_HOST`, `DB_PORT` and `DB_CONNECTION` are forced by `docker-compose.yml`
(`db`, `3306`, `mariadb`), so you don't need to change them in `.env`.

```bash
docker compose up -d --build
```

```bash
docker compose exec web php artisan key:generate --show
```

Put the printed key into `APP_KEY` in `.env`, then:

```bash
docker compose up -d
```

```bash
docker compose exec web php artisan db:seed
```

The site is at <http://localhost:8080>. Migrations run automatically when the `web`
container starts.

### Local development login

With `APP_ENV=local` and the SSO bypass flag enabled (see
[`.ai/guidelines/physical-library.md`](.ai/guidelines/physical-library.md) §2), you are
signed in as the seeded dev user without SSO. Never enable the bypass in production.

### Everyday commands

```bash
docker compose logs -f web queue
```

```bash
docker compose exec web php artisan test --compact
```

```bash
docker compose down
```

### Development stack with live changes

`docker-compose.dev.yml` runs the app on your checkout, so edits show up without a
rebuild or restart: PHP, Blade, routes and config on the next page load (opcache and
Laravel's caches are off); CSS and JS instantly through the Vite dev server, which also
reloads the page when a Blade file changes. It uses the local `.env` (SQLite, no
database container).

```bash
docker compose -f docker-compose.dev.yml up -d
```

Open <http://localhost:8000>. Vite runs on port 5173; set `DEV_APP_PORT` to move the app.
The first start installs the npm packages into a Docker volume and takes a minute.

```bash
docker compose -f docker-compose.dev.yml exec app php artisan migrate
```

```bash
docker compose -f docker-compose.dev.yml down
```

- Rebuild (`up -d --build`) only after changing the `Dockerfile`.
- After changing `package.json`, reinstall the packages:
  `docker compose -f docker-compose.dev.yml run --rm vite npm ci`.
- If the page looks unstyled after Vite stopped uncleanly, delete `public/hot`.

### Running the tests without the stack

`docker-compose.test.yml` runs the suite in the app image (PHP 8.4 + `intl`) with the
checkout mounted. It needs no `.env`, no database and no running containers; the first
run builds the image. Install the dev dependencies into `vendor/` first.

```bash
docker compose -f docker-compose.test.yml run --rm test
```

Any other command runs the same way, e.g. Pint:

```bash
docker compose -f docker-compose.test.yml run --rm test php vendor/bin/pint --dirty --format agent
```

`docker compose down -v` also deletes the database and uploaded files volumes.

### Ports and isolation

- The website listens on `${APP_BIND:-127.0.0.1}:${APP_PORT:-8080}` — localhost only
  by default (see below).
- MariaDB is published on `127.0.0.1:${FORWARD_DB_PORT:-3306}` only, for database
  GUIs on the host. Remove that `ports:` entry in `docker-compose.yml` to keep the
  database reachable from the `backend` network alone.

## Public access via Cloudflare Tunnel

The machine running Docker is **not reachable directly from the internet**. Public
traffic reaches it only through a **Cloudflare Tunnel**: `cloudflared` on that machine
opens an outbound connection to Cloudflare, and Cloudflare forwards requests for
`library.gekophetweb.nl` down the tunnel to the `web` container.

```
browser / phone app ──HTTPS──▶ Cloudflare edge ──tunnel──▶ cloudflared (Docker host)
                                                              └─HTTP─▶ web :8080 ──▶ db
```

- **No inbound ports.** Don't forward 80/443 on the router or open firewall ports;
  the tunnel is outbound only. The database is never exposed through the tunnel.
- **Ingress rule.** In the tunnel's public hostname settings (Zero Trust dashboard →
  Networks → Tunnels), map `library.gekophetweb.nl` to `http://localhost:8080` (or to
  `${APP_PORT}` if you changed it). TLS ends at Cloudflare; the hop from `cloudflared`
  to `web` is plain HTTP on the same machine.
- **Localhost only by default.** `web` binds to `127.0.0.1`, so the site is reachable
  only through the tunnel (and from a browser on the Docker host itself). See
  [Choosing who can reach the website](#choosing-who-can-reach-the-website) to also
  allow access from the LAN.
- **Run `cloudflared` as a service** (`cloudflared service install <token>`) so the
  tunnel comes back after a reboot, just like the containers (`restart: unless-stopped`).

### Choosing who can reach the website

Two settings in `.env` control where the `web` container is published on the host:

| Setting    | Default     | Meaning                                      |
|------------|-------------|----------------------------------------------|
| `APP_BIND` | `127.0.0.1` | Host network interface the website binds to  |
| `APP_PORT` | `8080`      | Host port the website listens on             |

**Option 1 — tunnel only (default).** Leave `APP_BIND` unset or set:

```dotenv
APP_BIND=127.0.0.1
```

The site answers only on `http://localhost:8080` on the Docker host itself, which is
where `cloudflared` connects. Other machines on the LAN get "connection refused";
everyone uses `https://library.gekophetweb.nl`.

**Option 2 — tunnel + LAN.** To also open the site to other devices on your network
(e.g. the phone app over Wi-Fi while testing):

```dotenv
APP_BIND=0.0.0.0
```

The site is then also at `http://<docker-host-ip>:8080` from the LAN. The tunnel keeps
working unchanged. To limit it to one network card, use that card's IP instead of
`0.0.0.0` (e.g. `APP_BIND=192.168.1.20`).

Apply a change by recreating the container (a plain restart doesn't pick up new port
mappings):

```bash
docker compose up -d web
```

Check the active binding — `127.0.0.1:8080->8080/tcp` means localhost only,
`0.0.0.0:8080->8080/tcp` means LAN too:

```bash
docker compose ps web
```

Notes for LAN mode:

- LAN access is plain HTTP with no Cloudflare in front of it. With
  `SESSION_SECURE_COOKIE=true` browsers won't send the session cookie over HTTP, so
  logging in over the LAN address fails — use the public hostname for the website and
  keep LAN access for API testing, or set `SESSION_SECURE_COOKIE=false` on a dev
  machine only.
- Links the app generates still point to `APP_URL` (the public hostname).
- On Windows/macOS hosts the OS firewall may still block port 8080 from the LAN; allow
  it there if needed.

The database port follows the same pattern but is fixed to `127.0.0.1` on purpose and
should not be opened to the LAN.

### Required app settings behind the tunnel

```dotenv
APP_ENV=production
APP_URL=https://library.gekophetweb.nl
SESSION_SECURE_COOKIE=true
```

- **Trust the proxy.** Laravel sees every request as plain HTTP from `cloudflared`.
  Trust forwarded headers in `bootstrap/app.php`
  (`$middleware->trustProxies(at: '*')`) — otherwise generated URLs, redirects and
  signed URLs use `http://`, and `request()->ip()` returns the tunnel's address
  instead of the visitor's. The `throttle:60,1` limit on share links depends on the
  real client IP.
- **SSO callback.** Register the redirect/callback URL with the SSO provider under the
  public `https://library.gekophetweb.nl` hostname, not `localhost`.
- **Cloudflare Access.** If you put an Access policy in front of the hostname, exclude
  `/api/*` (the phone app uses token auth and can't complete an Access login) and
  `/s/*` (read-only share links are for viewers without an account).

## Project documentation

- [`.ai/guidelines/physical-library.md`](.ai/guidelines/physical-library.md) — domain
  model, scanning workflow, providers, API and conventions.
- [`.ai/plans/`](.ai/plans/) — design plans: implemented (own/wishlist, search,
  read-only sharing) and draft
  ([series detection from ISBN](.ai/plans/series-detection-from-isbn.md)).
- [`.ai/skills/deploying-laravel-cloud/`](.ai/skills/deploying-laravel-cloud/) —
  deploying to Laravel Cloud.
