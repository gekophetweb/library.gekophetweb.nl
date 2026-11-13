# Plan — Comment threads on suggestions

Status: **implemented 2026-11-13** on `feature/suggestion-comments`. Follow-up from
`suggestion-box.md` §6; builds on `suggestion-votes.md` (the board).

Each suggestion gets its own page with a flat comment thread.

---

## 1. Decisions (defaults)

1. **Who can read and comment = who can see the suggestion:** the sender and admins
   always; everyone once an admin put it on the board (`Suggestion::isVisibleTo`).
   Anything else is a 404.
2. **Flat thread, oldest first.** No nesting, editing, reactions or attachments.
3. **Names only for admins.** Everyone else sees roles — "You", "Admin", "Sender",
   "Another user" — so a board thread keeps the board's anonymity.
4. **Deleting:** the author deletes their own comment; admins delete any (moderation).
5. The admin's single `admin_reply` stays as the official answer shown on lists; the
   thread is for discussion.
6. No notifications here (that is `suggestion-notifications.md`).

## 2. Build

1. Migration `create_suggestion_comments_table` (`suggestion_id`, `user_id`, `body`).
2. `SuggestionComment` (+ factory) with `authorLabelFor(User)` and
   `canBeDeletedBy(User)`; `Suggestion::comments()`.
3. `SuggestionCommentController`: `GET suggestions/{suggestion}`,
   `POST suggestions/{suggestion}/comments` (`throttle:20,1`),
   `DELETE suggestions/{suggestion}/comments/{comment}` (scoped binding).
4. `suggestions/show` view (body, reply, vote button, thread, form); "N comments" links
   on the user's list, the board and the admin inbox.

## 3. Tests

`tests/Feature/Suggestions/SuggestionCommentsTest.php` (6).

## 4. As built (2026-11-13)

- Built as planned.
