# Plan — Add missing series volumes to the wishlist

Status: **implemented 2026-11-13** on `feature/series-missing-to-wishlist`. The follow-up
named in `series-detection-from-isbn.md` (§ out of scope) and `own-wishlist.md`.

On a series page with a known volume list, a missing volume gets "Add to wishlist", and
the page gets "Add all N missing volumes to wishlist".

---

## 1. Decisions (defaults)

1. **Where the new item goes** — category, sub-category and languages — is copied from
   the newest item already in the series. A series with no items (possible only by hand)
   shows no buttons; the action answers 409.
2. **Barcode = the volume's first ISBN**, so a later Own scan promotes it (own-wishlist
   decision 2). A volume without ISBNs (Wikidata) is added without a barcode and matches
   its volume by number.
3. **Name:** the volume's own title when known, else "{series} {number} {edition}";
   romanized and English names follow the series' names the same way. Publisher and
   author come from the series.
4. **Never a duplicate:** only volumes in state `missing` are added, and a volume whose
   ISBN the user already has (owned or wanted, in any series) is skipped.
5. The "add all" button shows only when more than one volume is missing.
6. Owner only — share-link viewers see no buttons. No API endpoint for now.

## 2. Build

1. `App\Actions\Series\WishMissingVolumes` (`handle(Series, ?Collection $only)`,
   `canWish(Series)`), using `SeriesShelf` to find missing volumes.
2. `LibraryController@wishMissing` behind `POST library/series/{series}/wishlist` and
   `POST library/series/{series}/volumes/{volume}/wishlist` (scoped binding).
3. `AddScannedObject::date()` made public as `normalizeDate()` to turn "1990-12" into a
   date.
4. Buttons in `library/series.blade.php`.

## 3. Tests

`tests/Feature/Series/WishMissingVolumesTest.php` (7): one volume, all volumes (with a
no-ISBN volume), no duplicates, ISBN already held elsewhere, owner-only buttons, other
user's series / foreign volume 404, no template item 409.

## 4. As built (2026-11-13)

- Built as planned.
