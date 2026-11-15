<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\ClearUserLibrary;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRoleRequest;
use App\Models\ScanBatch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Every account, admins and the signed-in admin included. An admin sees that a user
 * exists and how much is in their library, never their items. The changes an admin
 * makes here are another user's role (plan admin-manage-roles) and emptying a user's
 * library (scan batches, collection, wishlist and series).
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $role = $request->validate(['role' => ['nullable', 'in:all,admins,users']])['role'] ?? 'all';

        $users = User::query()
            ->withCount(['objects' => fn ($query) => $query->owned()])
            ->withCount(['objects as library_objects_count', 'scanBatches'])
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

    /**
     * Removes every scan batch, object (collection and wishlist) and series of the user;
     * the account, keys and settings stay. Not while a batch job is still adding items,
     * which would put objects back as it goes.
     */
    public function clearLibrary(User $user, ClearUserLibrary $clear): RedirectResponse
    {
        if ($user->scanBatches()->whereIn('status', [ScanBatch::QUEUED, ScanBatch::PROCESSING])->exists()) {
            return back()->withErrors(['library' => "{$user->name} has a scan batch that is still adding items. Try again when it is done."]);
        }

        $removed = $clear->handle($user);

        return back()->with('status', sprintf(
            'Removed %d %s, %d %s and %d series from %s’s library.',
            $removed['objects'], str('item')->plural($removed['objects']),
            $removed['batches'], str('scan batch')->plural($removed['batches']),
            $removed['series'], $user->name,
        ));
    }
}
