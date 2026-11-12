@props(['series'])
<ul class="divide-y divide-stone-200 rounded-lg border border-stone-200 bg-white dark:divide-stone-800 dark:border-stone-800 dark:bg-stone-900">
    @foreach ($series as $entry)
        <li>
            <a href="{{ $links->series($entry) }}" class="flex flex-wrap items-baseline justify-between gap-2 px-4 py-3 hover:bg-stone-50 dark:hover:bg-stone-800">
                <span>
                    <span class="font-medium">{{ $entry->name }}</span>
                    @if ($entry->artist) <span class="text-sm text-stone-500">· {{ $entry->artist->name }}</span> @endif
                </span>
                <span class="text-sm text-stone-500">
                    {{ $entry->objects_count ?? $entry->items_count ?? 0 }} owned
                </span>
            </a>
        </li>
    @endforeach
</ul>
