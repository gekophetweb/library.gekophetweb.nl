# Series English name — design (short)

## Intent

Let the owner give a series their own English name. Japanese titles are harder to
read and recognise at a glance, so the English name becomes the series' main title
wherever it is set, and it is searchable.

## Goals

- Owner can set, change or clear an English name per series.
- Where set, it is the main title: series page (heading and page title), category
  shelf list, share-scope picker in settings. The original title, and the romanized
  title when it differs, show underneath as now.
- Search finds a series by it (already true: `SearchesNames` covers `name_english`).

## Non-goals

- No English name for single objects or artists (objects already have one).
- No automatic translation or lookup of English names.
- No change to how series are matched or synced with NDL / other sources: lookups
  keep using the original `name`.

## Approaches

1. **Reuse `series.name_english` (chosen).** The column exists, is fillable,
   searchable and already used by `LinkSeries` for matching, but is never shown or
   editable. A catalogue English name, when a source supplies one, simply arrives
   prefilled and the owner can overwrite it.
2. Separate `alias` column. Keeps the catalogue value apart from the owner's, at the
   cost of a migration, two English-ish fields and a precedence rule. Catalogue
   English names are rare and never shown, so nothing is gained.

Picked by the user: option 1.

## Design

- `Series::displayName()` returns `name_english` when filled, else `name`. Views
  above use it instead of `name`.
- Series page subtitle: when an English name is set, show `name` underneath, then
  `name_romanized` if it differs from `name`. Without one, unchanged.
- Category shelf and share picker: `displayName()` only (one line, as now).
- Editing: owner-only small form on the series page (behind `$links->canEdit()`),
  a single "English name" field, empty clears it. New route
  `PATCH library/series/{series}` → `library.series.update`, same ownership check as
  the sync route; validation `nullable|string|max:255`, trimmed, blank → null.
  Redirects back to the series page with a status message.
- Scan series field: unchanged input (keeps the catalogue name so matching works).
  My call: when the suggested series already exists in the user's library with an
  English name, show that name next to the "from NDL" source hint so it is
  recognisable while scanning.

## Tests

- Owner sets, changes and clears the English name; other users and share-link
  viewers cannot (403 / no form shown).
- Series page shows the English name as heading with the original underneath;
  without one it renders as before.
- Category shelf and share picker show the English name.
- Library search finds the series by its English name.
- Validation: over 255 characters rejected.

## Left to the builder

Exact form markup and placement next to "Fetch volumes" / "Share this series",
status message wording, and whether the edit form is a disclosure or inline.
