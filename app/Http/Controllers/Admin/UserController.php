<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Every account, admins and the signed-in admin included. Read-only towards collections:
 * an admin sees that a user exists, never their items.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $role = $request->validate(['role' => ['nullable', 'in:all,admins,users']])['role'] ?? 'all';

        $users = User::query()
            ->withCount('objects')
            ->when($role === 'admins', fn ($query) => $query->where('is_admin', true))
            ->when($role === 'users', fn ($query) => $query->where('is_admin', false))
            ->orderByDesc('is_admin')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', ['users' => $users, 'role' => $role]);
    }
}
