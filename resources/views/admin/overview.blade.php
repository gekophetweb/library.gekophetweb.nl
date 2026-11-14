@php
    $cards = [
        [route('admin.users.index'), 'Users', $users.' '.Str::plural('account', $users), $admins.' '.Str::plural('admin', $admins)],
        [route('admin.sso.index'), 'Sign-in', $providers.' SSO '.Str::plural('provider', $providers).' enabled', 'E-mail login codes '.($emailCodes ? 'on' : 'off')],
        [route('admin.languages.index'), 'Languages', $languages.' '.Str::plural('language', $languages), 'Offered when scanning and editing items'],
        [route('admin.categories.index'), 'Categories', $topCategories.' main · '.$subCategories.' sub-categories', 'Sub-categories can be added and renamed'],
        [route('admin.scan-batches.index'), 'Scan batches', $batches.' '.Str::plural('batch', $batches), $draftBatches.' not committed'],
        [route('admin.suggestions.index'), 'Suggestions', $newSuggestions.' new', 'Ideas and bug reports from users'],
    ];
@endphp
<x-admin-layout title="Overview">
    <h1 class="mb-6 text-2xl font-semibold">Admin</h1>

    <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($cards as [$href, $label, $main, $detail])
            <li>
                <a href="{{ $href }}" class="block h-full rounded-lg border border-stone-200 bg-white p-4 hover:border-stone-400 dark:border-stone-800 dark:bg-stone-900 dark:hover:border-stone-600">
                    <h2 class="font-semibold">{{ $label }}</h2>
                    <p class="mt-1 text-sm">{{ $main }}</p>
                    <p class="mt-1 text-sm text-stone-500">{{ $detail }}</p>
                </a>
            </li>
        @endforeach
    </ul>
</x-admin-layout>
