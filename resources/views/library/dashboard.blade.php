<x-layout title="Library">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold">Your library</h1>
        <x-scanner-dialog :categories="$scanCategories" :languages="$languages" />
    </div>

    <ul class="grid gap-4 sm:grid-cols-3">
        @foreach ($categories as $category)
            <li>
                <a href="{{ route('library.category', $category) }}"
                   class="block rounded-xl border border-stone-200 bg-white p-5 hover:border-stone-400 dark:border-stone-800 dark:bg-stone-900 dark:hover:border-stone-600">
                    <span class="block text-lg font-semibold">{{ $category->name }}</span>
                    {{-- Owned is the collection size; wanted items are a secondary figure. --}}
                    <span class="text-sm text-stone-600 dark:text-stone-400" data-owned="{{ $category->items_count }}">{{ $category->items_count }} owned</span>
                    @if ($category->wanted_count)
                        <span class="text-sm text-stone-500" data-wanted="{{ $category->wanted_count }}">· {{ $category->wanted_count }} wanted</span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>

    @if ($sharedWithMe->isNotEmpty())
        <section class="mt-10" data-shared-with-me>
            <h2 class="mb-3 text-lg font-semibold">Shared with you</h2>
            <ul class="divide-y divide-stone-200 rounded-lg border border-stone-200 bg-white dark:divide-stone-800 dark:border-stone-800 dark:bg-stone-900">
                @foreach ($sharedWithMe as $share)
                    <li class="px-4 py-3">
                        <a href="{{ $share->url() }}" class="font-medium underline-offset-2 hover:underline">{{ $share->user->name }}’s {{ $share->isWholeLibrary() ? 'collection' : $share->scopeLabel() }}</a>
                        @if ($share->label) <span class="text-sm text-stone-500">· {{ $share->label }}</span> @endif
                        @if ($share->expires_at) <span class="text-sm text-stone-500">· until {{ $share->expires_at->toFormattedDateString() }}</span> @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layout>
