# Plan — Suggestion box

Status: **confirmed 2026-11-13**, built on `feature/suggestion-box`.

Signed-in users send a suggestion (feedback, a bug, a feature idea) from the web UI;
admins read and triage them.

---

## 1. Design

One `suggestions` row per message, owned by the user who sent it. Admins see every row
through the existing `can:admin` group; everyone else only ever sees their own.

### Decisions (confirmed with the user)

1. **Users see their own past suggestions** with the status of each.
2. **Statuses `new` / `planned` / `done` / `declined`**, set by an admin.
3. **Admin reply: one optional note per suggestion**, shown to the sender.
4. **No e-mail to admins**: the admin page is the inbox, with a count of `new` ones in
   the admin nav.
5. **Not anonymous**: the admin sees who sent it.
6. **Web only**: no phone-app API endpoint for now.

## 2. Phase 1 — Data + model

1. Migration `create_suggestions_table`:

   | column | notes |
   |---|---|
   | `user_id` | fk to `users`, cascade on delete |
   | `body` | text, 10–5000 chars (validated in the Form Request) |
   | `status` | string, default `new` |
   | `admin_reply` | nullable text |
   | timestamps | |

2. `App\Enums\SuggestionStatus` (backed string enum with `label()`), cast on the model.
   A fixed workflow, not user data, so an enum fits here (§7's "data, not enums" rule is
   about catalogue categories).
3. `App\Models\Suggestion` — `belongsTo(User)`; `User::suggestions()`.
4. `SuggestionFactory` with `status()` helpers.

## 3. Phase 2 — Users

5. Routes inside `auth`: `GET suggestions` (form + own list), `POST suggestions`
   (`throttle:10,1`).
6. `SuggestionController` + `StoreSuggestionRequest`. The list is
   `$request->user()->suggestions()`, never a global query.
7. "Suggest something" link in the nav in `components/layout.blade.php`.

## 4. Phase 3 — Admins

8. Under the `can:admin` group: `GET admin/suggestions` (filter on status, newest first,
   paginated at 25, sender name + e-mail) and `PATCH admin/suggestions/{suggestion}`
   (status + reply), via `UpdateSuggestionRequest`.
9. "Suggestions" nav link for admins, with the count of `new` ones.

## 5. Tests

`tests/Feature/Suggestions/SubmitSuggestionTest.php`, `.../AdminSuggestionsTest.php`:
submit + validation + throttle, own list shows only own rows, guests redirected,
non-admins 403 on the admin page and the update, admin filters and updates status/reply.

## 6. Out of scope

- Votes, comment threads, attachments.
- E-mail notifications and the phone-app API (decisions 4 and 6).

## 7. As built (2026-11-13)

- No `planned()` etc. factory states: tests set `status` directly, which reads as well.
- `status` and `admin_reply` are not fillable; `SuggestionController@store` creates
  through `$user->suggestions()` with only `body`, so a forged form field is ignored
  (tested).
- The admin nav badge counts `new` suggestions with one query in the layout, only
  inside `@can('admin')`. An empty reply saves as `null`.
- Tests: `tests/Feature/Suggestions/SubmitSuggestionTest.php` (9) and
  `AdminSuggestionsTest.php` (8).
