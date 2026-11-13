<x-layout title="Library" :share="$share">
    <h1 class="mb-6 text-2xl font-semibold">{{ $share->label ?? $share->user->name.'’s library' }}</h1>

    <ul class="grid gap-4 sm:grid-cols-3">
        @foreach ($categories as $category)
            <li>
                <a href="{{ $links->category($category) }}"
                   class="block rounded-xl border border-stone-200 bg-white p-5 hover:border-stone-400 dark:border-stone-800 dark:bg-stone-900 dark:hover:border-stone-600">
                    <span class="block text-lg font-semibold">{{ $category->name }}</span>
                    <span class="text-sm text-stone-600 dark:text-stone-400" data-owned="{{ $category->items_count }}">{{ $category->items_count }} owned</span>
                    @if ($category->wanted_count)
                        <span class="text-sm text-stone-500" data-wanted="{{ $category->wanted_count }}">· {{ $category->wanted_count }} wanted</span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
</x-layout>
