# Plan — First-run setup page (mail, SSO, first admin)

Status: **design, awaiting approval** (2026-11-14).

On a fresh install nobody has an account yet. Today the owner has to edit `MAIL_*` in
`.env`, sign in with an e-mail code, run `php artisan users:make-admin` inside the
container and only then open `/admin/sso`. This plan replaces that with a setup page
the app shows until the first admin exists: set up outgoing mail (Resend or any other
SMTP server), optionally add SSO providers, and create the admin account, all in the
browser.

---

## 1. Goals, non-goals, anti-goals

**Goals**
- A fresh install, reached through the Cloudflare Tunnel, can be finished from the
  browser alone: no `.env` edits for mail, no `artisan` command for the first admin.
- Mail settings live in the app (database) and stay editable later by an admin.
- Resend works without extra packages, as an SMTP preset.

**Non-goals**
- Setting `APP_KEY`, `APP_URL`, database or tunnel settings from the browser: those
  are needed before the app can even serve the page, and stay in `.env`.
- API-based mail transports (Resend HTTP API, Postmark, SES): SMTP covers all of them.
- Re-running setup after an admin exists. Recovery stays `php artisan users:make-admin`.

**Anti-goals** (a failure even if it works)
- A stranger on the public URL becomes admin before the owner does.
- Secrets (SMTP password, SSO secret) shown back in a page, written to a log, or stored
  unencrypted. The setup code in the container log is intended and is the only secret
  printed.
- The owner finishes setup and is locked out: no working mail and no working SSO.

## 2. Approaches considered (mail settings)

| | Approach | Trade-off |
|---|---|---|
| **A** ✅ | Store mail settings in `app_settings`, override the `mail.*` config at runtime | Works in Docker and Laravel Cloud; editable later; `.env` stays the fallback. Needs the queue worker to reload after a change. |
| B | The page writes `.env` | Production `.env` is on the host, not writable from the container; a web page rewriting `.env` is risky. Rejected. |
| C | Mail stays in `.env`; setup only checks it and sends a test | Simplest, but does not do what was asked (set mail up from the page). |

**Picked: A** (user, 2026-11-14).

Setup page protection — **picked: a one-time setup code printed in the container log**
(user, 2026-11-14), over "only reachable from localhost on the Docker host", because the
owner may only have a browser on another machine and the tunnel makes every request
look local anyway.

## 3. Decisions

### 3a. Setup mode

1. **Setup mode = no user with `is_admin = true`.** Checked by a `SetupState` support
   class. Once an admin exists the answer never goes back, so the "done" result is
   remembered in `app_settings` (`setup.completed_at`) and in a per-process static, so
   normal requests cost no query.
2. Middleware `RedirectToSetup` on the `web` group: in setup mode, every web route
   except `/setup*`, `/healthcheck` and asset URLs redirects to `/setup`. `/api/*` and
   `/s/*` answer `503` with a short JSON/text message instead of redirecting (the phone
   app and share links must not land on an HTML form).
3. Outside setup mode, `/setup*` returns `404`.
4. An existing install that already has an admin never sees any of this. Its mail keeps
   coming from `.env` until an admin saves mail settings on the new admin page.

### 3b. Setup code

5. **The code:** 8 characters from an unambiguous alphabet (no `0/O/1/I`), shown as
   `XXXX-XXXX`. Only a hash (`Hash::make`) is stored, in `app_settings`
   (`setup.code_hash`), with `setup.code_created_at`.
6. **Printing it:** a command `php artisan setup:code`:
   - in setup mode: generates a new code (replacing any earlier one), prints it in a
     clearly marked box with the setup URL (`APP_URL/setup`), exits 0;
   - outside setup mode: prints "Setup is already complete" and exits 0, generating
     nothing.
7. **At container start:** the `Dockerfile` adds an entrypoint script for the
   serversideup image (`/etc/entrypoint.d/`, numbered to run **after** the image's
   Laravel automations, so migrations have run) that calls `php artisan setup:code`
   only when `AUTORUN_ENABLED=true` (only `web` sets that, so `queue` and `scheduler`
   don't each print and overwrite a code). Its output goes to the container's stdout,
   so `docker compose logs web` shows it. On Laravel Cloud the owner runs the command by
   hand; the README says so.
8. The setup page itself says where to find the code: `docker compose logs web`, or
   run `docker compose exec web php artisan setup:code` for a new one.
9. **Entering it:** `/setup` (step 1) asks for the code. Wrong codes are rate-limited
   (5 per minute per IP, 20 per hour in total: the total limit stops a botnet guessing).
   A correct code marks the session as `setup.unlocked`; every later setup step requires
   that flag. The code stays valid until setup finishes, so a browser restart mid-setup
   only means entering it again.
10. On finishing, `setup.code_hash` is deleted.

### 3c. The steps

The setup is a short wizard at `/setup`, one step per page, with a step indicator.
Progress (what has been saved) is shown from the data itself, not a separate state
machine: mail is "done" when mail settings exist and the admin address was verified;
SSO is optional.

**Step 1 — Setup code.** See 3b.

**Step 2 — Your account.** Name and e-mail address of the first admin. Kept in the
session until step 3 verifies it; no user row yet.

**Step 3 — Outgoing mail.**
- Preset: **Resend** or **Other SMTP server**.
  - Resend fills in host `smtp.resend.com`, port `465`, encryption SSL, username
    `resend`, and asks only for the **API key** (used as password) and the sender.
  - Other SMTP: host, port, encryption (`SSL/TLS` = `smtps`, `STARTTLS` = `smtp`,
    `None`), username, password.
- Both: **From address** and **From name** (default `APP_NAME`).
- Button **Send verification code**: saves nothing yet; builds a mailer from the form
  values and sends a 6-digit code to the admin address from step 2. Connection or auth
  errors are shown in plain words next to the form (the SMTP password never appears in
  the message).
- Entering the code proves both that mail works and that the admin owns the address.
  Only then are the mail settings saved (3d) and the step marked done.
- The code is single-use, valid 10 minutes, 5 attempts; reuse the hashing and
  attempt-counting approach of `EmailLoginCodes` where it fits.

**Step 4 — Sign-in with SSO (optional).** The existing provider form
(`admin/sso/form`, `SsoProviderRequest`) embedded in the wizard. After saving, it shows
the callback URL to register at the identity provider (built from `APP_URL`, so the
page warns when `APP_URL` is `http://localhost…`). "Test login" is not offered here
(no admin yet); it is on `/admin/sso` afterwards. **Skip** goes to the last step.

**Step 5 — Finish.** Summary (admin name and address, mail sender, SSO providers),
then **Finish setup**, which in one transaction:
- creates the user (`email_verified_at = now()`, `is_admin = true`, set explicitly as
  `User` requires);
- sets `setup.completed_at`, deletes `setup.code_hash`;
- logs the user in (`session()->regenerate()`), then redirects to the welcome flow /
  home.
The `SsoController` callback then links an SSO login to this user by verified e-mail,
as it does today.

### 3d. Mail settings in the app

11. One `app_settings` row, key `mail.smtp`: `{preset, host, port, scheme, username,
    password, from_address, from_name}`. The **password is encrypted** with `Crypt`
    inside the JSON value (the `value` cast is plain JSON). A `MailSettings` support
    class reads/writes it and never returns the decrypted password to views.
12. **Applying it:** in `AppServiceProvider::boot()`, when `mail.smtp` exists, set
    `mail.default = smtp` and the `mail.mailers.smtp.*` and `mail.from.*` config values
    from it. Read through `Cache::rememberForever('mail.smtp')`, forgotten on save, so a
    request costs no query. Guard for a missing `app_settings` table (first boot before
    migrations) so the app still boots.
13. **Queue worker:** `queue:work` keeps the config it booted with. Saving mail settings
    calls `Artisan::call('queue:restart')`; the worker exits after its current job and
    Docker restarts it (`restart: unless-stopped`) with the new settings.
14. **`.env` stays the fallback:** no `mail.smtp` row → `MAIL_*` from `.env`, exactly as
    now. The dev stack's Mailpit settings keep working unless someone saves mail settings
    there.
15. **Admin page `/admin/mail`** (`can:admin`, linked next to "Sign-in" in the admin
    menu): the same form, prefilled except the password (blank = keep), a **Send test
    mail** button (to the admin's own address) and **Save**. Saving there does not need a
    verification code; the test button is advisory.

## 4. Build

1. `SetupState`, `RedirectToSetup` middleware (registered in `bootstrap/app.php`).
2. `setup:code` command; entrypoint script + `Dockerfile` line.
3. `MailSettings` (read/write/encrypt/apply/cache) and the `AppServiceProvider` hook.
4. `SetupController` (steps 1–5), form requests, views under `resources/views/setup/`
   in the existing guest layout and Tailwind style.
5. `Admin\MailSettingsController` + view.
6. README: replace the `users:make-admin` / `.env` mail steps in "First start" with the
   setup page, mention `setup:code`, and that mail can be changed at `/admin/mail`.
   Guidelines: setup mode and mail settings in the app.

## 5. Tests (Pest, feature)

- Setup mode: with no admin, `/` and `/login` redirect to `/setup`; `/api/*` and
  `/s/*` give 503; `/healthcheck` works. With an admin, `/setup` is 404.
- Setup code: `setup:code` prints a code and stores only a hash; a second run replaces
  it; outside setup mode it generates nothing. Wrong codes are rejected and
  rate-limited; steps 2–5 need the unlocked session.
- Mail step: with `Mail::fake()`, "Send verification code" sends to the admin address;
  the right code saves the settings with an encrypted password; a wrong/expired code
  does not. An SMTP failure (mocked transport exception) shows a message without the
  password.
- Finish: creates exactly one verified admin, logs them in, clears the code; a second
  finish attempt is 404.
- `MailSettings`: saved settings override `.env` config on the next boot; none saved →
  `.env` untouched; saving calls `queue:restart`.
- `/admin/mail`: admin only; blank password keeps the old one; the password never
  appears in the HTML.

## 6. Left to the builder

- Exact copy and layout of the wizard pages (follow the login page's look).
- The serversideup entrypoint script's file name/number, after checking the image's
  own script order.
- Whether the SMTP connection check uses Symfony's transport `start()` before sending,
  or just the send's exception.
