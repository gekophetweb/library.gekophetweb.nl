# Plan — Share with a named account

Status: **implemented 2026-11-13** on `feature/share-with-account`. The follow-up left
out of `share-library-read-only.md` (link sharing only).

A share can be made for one e-mail address. It then opens only for the signed-in
account with that address, and shows on that person's dashboard under "Shared with you".

---

## 1. Decisions (defaults)

1. **Built on the existing share links**, not a second mechanism: a `library_shares` row
   with a `recipient_email`. Same scopes (library / category / series), same expiry,
   same read-only pages, revoke = delete.
2. **By e-mail address, not by picking a user.** The owner can share before the person
   signs up (sign-up is open, foundation step 9), and the form never reveals which
   addresses have an account.
3. **Access:** a guest is sent to the login page and back (`url.intended`); any other
   signed-in account — the owner included — gets the same 404 as an unknown token.
   Matching is case-insensitive; addresses are stored lower-cased.
4. **No e-mail to the recipient.** The owner sends the link themselves; the recipient
   also finds it on their dashboard. (Avoids the app mailing arbitrary addresses.)
5. **Read only, always.** Pages under `/s` still never act, signed in or not; the user is
   only read to check the recipient.
6. You cannot share with your own address.

## 2. Build

1. Migration `add_recipient_email_to_library_shares_table` (nullable, indexed).
2. `LibraryShare`: `recipient_email` fillable, `sharedWith(User)` scope, `isPersonal()`,
   `admits(?User)`.
3. `ResolveLibraryShare`: guest → login redirect, wrong account → 404.
4. `StoreLibraryShareRequest` + settings form: optional "Only for" e-mail field; the
   share list shows "Only for …".
5. Dashboard: "Shared with you" with the usable personal shares.

## 3. Tests

`tests/Feature/Sharing/ShareWithAccountTest.php` (8).

## 4. As built (2026-11-13)

- Built as planned. The "Share this category / series" buttons still make plain link
  shares; a personal share is made from settings.
