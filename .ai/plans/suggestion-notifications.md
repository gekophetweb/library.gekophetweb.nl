# Plan — E-mail notifications for suggestions

Status: **implemented 2026-11-13** on `feature/suggestion-notifications`. Revisits
suggestion-box decision 4 ("no e-mail to admins"), which was listed as a follow-up.

---

## 1. Decisions (defaults)

1. **Three e-mails:**
   - a new suggestion → every admin (not the sender, if they are an admin);
   - an admin changes the status or the reply → the sender (not when only the board
     flag changes, and not when the admin is the sender);
   - a new comment → the sender, every admin and everyone who commented before, minus
     the author and anyone who can no longer see the thread.
2. **One opt-out per user**, `users.suggestion_emails` (default on), in settings under
   "E-mail". No per-type settings, no digests.
3. **Anonymity carries into e-mail:** a comment e-mail names the author the way the
   recipient sees them on the page (`SuggestionComment::authorLabelFor`). The "new
   suggestion" e-mail to admins names the sender, as the admin inbox does.
4. **Queued Laravel mail notifications** with the default mail layout; the admin nav
   badge stays the inbox for anyone who opts out.

## 2. Build

1. Migration `add_suggestion_emails_to_users_table`; `User` casts it and makes it fillable.
2. `App\Support\Suggestions\SuggestionMailer` — `created()`, `updated()`, `commented()` —
   decides the recipients; called from the three controllers.
3. Notifications: `NewSuggestionNotification`, `SuggestionUpdatedNotification`,
   `NewSuggestionCommentNotification` (all `ShouldQueue`, mail only).
4. `PUT settings/notifications` + a checkbox in settings.

## 3. Tests

`tests/Feature/Suggestions/SuggestionNotificationsTest.php` (5), with
`Notification::fake()`.

## 4. As built (2026-11-13)

- Built as planned.
