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
                    <span class="text-sm text-stone-500">{{ trans_choice(':count item|:count items', $category->items_count) }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</x-layout>
