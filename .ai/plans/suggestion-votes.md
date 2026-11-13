# Plan — Votes on suggestions

Status: **implemented 2026-11-13** on `feature/suggestion-votes`. Follow-up from
`suggestion-box.md` §6 (out of scope there).

Users vote on suggestions so admins can see what people want most.

---

## 1. Decisions (defaults)

1. **Votes need a public board, and admins choose what goes on it.** Suggestions stay
   private to sender + admins by default (suggestion-box decision 1); an admin ticks
   "Show on the board". Senders cannot publish their own.
2. **The board hides the sender** — name and e-mail. Status and the admin reply show.
3. **One vote per user per suggestion**, can be taken back; no down-votes. You cannot
   vote on your own suggestion.
4. Board order: most votes, then newest. Admins can sort their inbox by votes.
5. Web only (the API is its own plan, `suggestion-api.md`).

## 2. Build

1. Migration: `suggestions.is_public` (bool, default false) and `suggestion_votes`
   (`suggestion_id`, `user_id`, unique pair).
2. `Suggestion`: `onBoard()` and `withVotesFor(User)` scopes, `voters()`,
   `isVisibleTo(User)`, `canBeVotedOnBy(User)`. `is_public` is not fillable.
3. `SuggestionVoteController`: `GET suggestions/board`, `POST|DELETE
   suggestions/{suggestion}/vote` (`throttle:60,1`). A private suggestion is a 404.
4. Views: board page, a "Your suggestions / Board" tab bar, "On the board · N votes" on
   the sender's list and the admin inbox, the admin checkbox, admin "Sort by votes".

## 3. Tests

`tests/Feature/Suggestions/SuggestionVotesTest.php` (7).

## 4. As built (2026-11-13)

- Built as planned.
