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

    {{-- Redo the automatic romanization of every Japanese / Korean item that still has a
         placeholder title, publisher or author. Hand-typed names stay. --}}
    <section class="mt-8 rounded-lg border border-stone-200 bg-white p-4 dark:border-stone-800 dark:bg-stone-900">
        <h2 class="font-semibold">Romanization</h2>
        <p class="mt-1 text-sm">{{ $unromanized }} Japanese or Korean {{ Str::plural('item', $unromanized) }} with a missing or automatic romanized title, publisher or author.</p>
        <p class="mt-1 text-sm text-stone-500">Looks each barcode up again with its owner’s keys. Names typed by hand are kept.</p>
        <form method="POST" action="{{ route('admin.romanize') }}" class="mt-3">
            @csrf
            <button class="btn btn-secondary" data-romanize-all @disabled($unromanized === 0)
                    onclick="return confirm(@js('Redo the romanization of '.$unromanized.' '.Str::plural('item', $unromanized).'?'))">Redo romanization</button>
        </form>
    </section>
</x-admin-layout>
