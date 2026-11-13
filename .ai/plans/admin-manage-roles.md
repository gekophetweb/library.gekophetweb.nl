# Plan — Admins promote and demote other admins

Status: **implemented 2026-11-13** on `feature/admin-manage-roles`. Follows up
foundation open question 2, which was left view-only.

Admins can make another account an admin, or take admin away, from the user list.

---

## 1. Decisions (defaults; no one answered foundation question 2)

1. **Promote and demote only.** Removing a user is out of scope: it deletes their whole
   collection and is better done deliberately (`tinker`) until someone asks for it.
2. **An admin cannot change their own role.** So the acting admin always stays an admin
   and the app can never end up with no admins. Promoting yourself is moot.
3. **No confirmation page.** One button per row; demoting is reversible by any admin.
4. `users:make-admin` stays for bootstrapping the first admin.

## 2. Build

1. `PATCH admin/users/{user}/role` inside the `can:admin` group →
   `Admin\UserController@updateRole` with `Admin\UpdateUserRoleRequest`
   (`is_admin` boolean, required).
2. The controller refuses (403) when `$user` is the signed-in admin. `is_admin` is not
   fillable, so it is set explicitly.
3. Users table gets an "Actions" column: "Make admin" / "Remove admin" buttons; none on
   your own row.

## 3. Tests

`tests/Feature/Admin/ManageRolesTest.php`: promote, demote, cannot change own role,
non-admins get 403, validation, buttons not shown on own row.

## 4. As built (2026-11-13)

- Built as planned. Flash message names the user ("Ann is now an admin.").
