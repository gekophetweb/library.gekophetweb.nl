<x-layout title="Users">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold">Users</h1>
        <nav class="flex gap-1 rounded-md border border-stone-200 bg-white p-1 text-sm dark:border-stone-800 dark:bg-stone-900" aria-label="Filter users">
            @foreach (['all' => 'All', 'admins' => 'Admins', 'users' => 'Users'] as $value => $label)
                <a href="{{ route('admin.users.index', $value === 'all' ? [] : ['role' => $value]) }}"
                   @class(['rounded px-3 py-1', 'bg-stone-900 text-white dark:bg-stone-100 dark:text-stone-900' => $role === $value, 'hover:bg-stone-100 dark:hover:bg-stone-800' => $role !== $value])
                   @if ($role === $value) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
    </div>

    <div class="overflow-x-auto rounded-lg border border-stone-200 bg-white dark:border-stone-800 dark:bg-stone-900">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-stone-200 text-xs uppercase tracking-wide text-stone-500 dark:border-stone-800">
                <tr>
                    <th class="px-4 py-3 font-medium">Name</th>
                    <th class="px-4 py-3 font-medium">E-mail</th>
                    <th class="px-4 py-3 font-medium">Joined</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200 dark:divide-stone-800">
                @forelse ($users as $user)
                    @php($you = $user->is(auth()->user()))
                    <tr data-user="{{ $user->id }}"{{ $you ? ' data-you' : '' }}>
                        <td class="px-4 py-3">
                            <span class="font-medium">{{ $user->name }}</span>
                            @if ($user->is_admin)
                                <span data-badge="admin" class="ml-1 rounded bg-violet-100 px-2 py-0.5 text-xs text-violet-800 dark:bg-violet-950 dark:text-violet-200">Admin</span>
                            @endif
                            @if ($you)
                                <span data-badge="you" class="ml-1 rounded bg-stone-100 px-2 py-0.5 text-xs dark:bg-stone-800">You</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-stone-600 dark:text-stone-400">{{ $user->email }}</td>
                        <td class="px-4 py-3 text-stone-600 dark:text-stone-400">{{ $user->created_at?->toFormattedDateString() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-6 text-center text-stone-500">No users match this filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
</x-layout>
