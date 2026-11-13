# Plan — Suggestion box in the phone-app API

Status: **implemented 2026-11-13** on `feature/suggestion-api`. Revisits suggestion-box
decision 6 ("web only"); covers votes and comments too.

---

## 1. Decisions (defaults)

1. **User features only:** send, list your own, the board, vote / unvote, read a
   suggestion with its thread, comment, delete your comment. Admin triage (status,
   reply, board flag) stays on the web.
2. **Same rules as the web:** `Suggestion::isVisibleTo()` gates every endpoint; a
   hidden suggestion is a 404; you cannot vote on your own (403). Same throttles.
3. **No sender identity in the API**, for admins too: `is_mine` instead. Comment
   authors use the web labels (`author`: "You", "Admin", "Sender", "Another user").
4. The same e-mails go out as on the web (`SuggestionMailer`).

## 2. Endpoints (`/api/v1`, Sanctum)

| Method | Path | Notes |
|---|---|---|
| GET | `suggestions` | own, newest first, 50 per page |
| POST | `suggestions` | `body` 10–5000; 201; `throttle:10,1` |
| GET | `suggestions/board` | public ones, most votes first |
| GET | `suggestions/{id}` | with `comments` |
| POST / DELETE | `suggestions/{id}/vote` | returns the suggestion |
| POST | `suggestions/{id}/comments` | 201 |
| DELETE | `suggestions/{id}/comments/{comment}` | 204 |

`SuggestionResource`: `id, body, status, status_label, admin_reply, on_board, is_mine,
can_vote, votes_count, voted, comments_count, comments, created_at`.
`SuggestionCommentResource`: `id, body, author, is_mine, can_delete, created_at`.

## 3. Tests

`tests/Feature/Api/SuggestionApiTest.php` (5).

## 4. As built (2026-11-13)

- Built as planned; reuses the web Form Requests.
