# Plan — Redesign: logo, light/dark themes and user styles

Status: **built 2026-11-15** on `feature/redesign-themes` (see §6).

Design canvas (private, owner only): <https://claude.ai/artifact/X8CqpMNFrdmYdLeZ4Q5dzt>
Boards: Brand & themes, Log in, Library (desktop + phone), Category, Series, Item,
Batch scan, Search, Settings · Profile & appearance, Admin overview, Shared library,
Appearance · your own style.

---

## 1. Decisions

1. **Logo**: the white "Gek op het Web" script PNG (253 × 172, transparent, soft
   shadow). Store as `public/images/gekophetweb-logo.png`. On dark it shows as drawn;
   on light it is inked with `filter: brightness(0) opacity(.85)`. Sizes: 88 px wide
   in the top bar, 84 px on phones, 160 px on log in.
2. **Two themes**, chosen per user: *Same as device* (default), *Light*, *Dark*. A
   sun/moon button in the top bar flips it; the choice is saved on the user and set as
   `data-theme` on `<html>` (no flash: inline the value server-side).
3. **Backgrounds**
   - Light, "index-card paper": cream `#f3eee4`, ruled lines every 32 px, a red margin
     rule 48 px from the left (16 px on phones).
   - Dark, "midnight ledger": `#111622`, same ruling at very low contrast.
4. **Type**: Bricolage Grotesque (headings), Instrument Sans (body, already in use),
   JetBrains Mono (barcodes, small caps labels). Self-host via `fonts-manifest`.
5. **Status colours** stay meaningful in both themes: owned = green, wanted = amber,
   info/suggestion = blue, not found = the accent red. Every pair passes 4.5:1.
6. **Touch targets** ≥ 44 px; pills/chips are real buttons or links.

## 2. Tokens

CSS custom properties on `[data-theme]`, mapped into Tailwind `@theme` so classes like
`bg-surface text-muted border-line` work.

| Token         | Light      | Dark       |
|---------------|------------|------------|
| `bg`          | `#f3eee4`  | `#111622`  |
| `bg-line`     | `rgba(52,72,112,.085)` | `rgba(150,175,225,.055)` |
| `margin`      | `rgba(196,65,43,.22)`  | `rgba(255,122,92,.18)`   |
| `surface`     | `#fffdf8`  | `#192030`  |
| `surface-2`   | `#f7f1e6`  | `#1f283a`  |
| `ink`         | `#1d2230`  | `#eef0f4`  |
| `muted`       | `#5d5a52`  | `#a3abbb`  |
| `line`        | `#ddd3c2`  | `#2a3447`  |
| `line-strong` | `#bcae95`  | `#3c4960`  |
| `accent`      | `#c4412b`  | `#ff7a5c`  |
| `accent-ink`  | `#ffffff`  | `#1c0c07`  |
| `accent-soft` | `#f6ddd5`  | `#3b2421`  |
| `owned` / soft  | `#1f6f5c` / `#d8ede5` | `#5fd0ae` / `#163a33` |
| `wanted` / soft | `#875800` / `#f7e6c1` | `#f2c066` / `#3a2e16` |
| `info` / soft   | `#1f5f8b` / `#dcebf5` | `#7cc0f0` / `#192f45` |

Radii: 10 px controls, 16–20 px cards, pills full. Cards get a soft shadow
(`--shadow`).

## 3. User styles (Settings › Appearance)

Users can set up their own look on top of the two themes. Stored as one JSON column
`users.appearance` (validated, defaults applied when missing):

- `mode`: `system` | `light` | `dark`
- `accent`: one of six presets (`stamp` red, `teal`, `indigo`, `plum`, `mustard`,
  `forest`) — each preset has a light and a dark value that pass contrast. No free
  hex: it cannot be checked for contrast in both themes.
- `background`: `ruled` (default) | `plain` | `dots` | `grid` | `photo`
- `photo`: an uploaded image (jpeg/png/webp, ≤ 4 MB, resized to 2400 px), stored on the
  private disk and served through a signed route; shown behind a theme-coloured veil
  (`veil` 60–95 %, default 85 %) so text stays readable.
- `headingFont`: `bricolage` (default) | `fraunces` | `instrument`
- `density`: `comfortable` (default) | `compact`

Rendered as CSS variables on `<html>` in the layout; share links (`/s/…`) always use
the owner's defaults, never the visitor's settings or photo.

## 4. Build (when picked up)

1. Tokens in `resources/css/app.css` (`@theme` + `[data-theme]` blocks), fonts.
2. Layout + nav redesign (`components/layout.blade.php`), logo, theme toggle (small JS).
3. Restyle pages board by board; keep every `data-*` hook the tests use.
4. `users.appearance` migration, Settings › Appearance page, photo upload + route.
5. Feature tests: saving each option, invalid values rejected, share pages ignore the
   visitor's appearance, photo route needs a signature.

## 6. As built (2026-11-15)

- **Tokens** in `resources/css/app.css`: `[data-theme]` blocks, `@theme inline` (classes
  `bg-surface text-muted border-line bg-accent …`), `dark:` follows `data-theme`. The
  stone ramp is re-tinted per theme, so pages not redrawn by hand still match. Components:
  `.btn(-primary/-secondary/-ghost) .card .field .chip .tile .pill(-owned/-wanted/…)`.
- **Redrawn**: layout + top bar (logo, search pill, sun/moon), log in / code / name, library
  dashboard, settings and admin menus (`<x-section-menu>`), Profile, Appearance. Other pages
  use the re-tinted palette and accent primary buttons.
- **Appearance** is `App\Support\Appearance\Appearance` (enums for every option, cast
  `AsAppearance`; the standard look is stored as null). Photos: `AppearancePhotos`, private
  `local` disk, re-encoded to WebP by GD (now in the Dockerfile), served by
  `appearance.photo` (signed + owner only). Account deletion removes them.
- **Theme toggle**: `PUT settings/appearance/theme`; guests keep their pick in localStorage.
- **MCP server** (`laravel/mcp`, `routes/ai.php`): `POST /api/mcp/appearance`, Sanctum token
  from Settings › Phone app. Tools `get-appearance`, `update-appearance`,
  `set-background-photo` (base64, never a URL), `reset-appearance`.
- Tests: `tests/Feature/Settings/AppearanceTest.php`, `tests/Feature/Mcp/AppearanceMcpTest.php`.
