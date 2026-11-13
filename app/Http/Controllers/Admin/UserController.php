<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRoleRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Every account, admins and the signed-in admin included. Read-only towards collections:
 * an admin sees that a user exists, never their items. The only change an admin makes
 * here is another user's role (plan admin-manage-roles).
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $role = $request->validate(['role' => ['nullable', 'in:all,admins,users']])['role'] ?? 'all';

        $users = User::query()
            ->withCount(['objects' => fn ($query) => $query->owned()])
            ->when($role === 'admins', fn ($query) => $query->where('is_admin', true))
            ->when($role === 'users', fn ($query) => $query->where('is_admin', false))
            ->orderByDesc('is_admin')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', ['users' => $users, 'role' => $role]);
    }

    /**
     * Promote or demote another user. An admin never changes their own role, so the
     * acting admin stays one and there is always at least one admin left.
     */
    public function updateRole(UpdateUserRoleRequest $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403, 'You cannot change your own role.');

        $user->is_admin = $request->boolean('is_admin');
        $user->save();

        return back()->with('status', $user->is_admin
            ? "{$user->name} is now an admin."
            : "{$user->name} is no longer an admin.");
    }
}
