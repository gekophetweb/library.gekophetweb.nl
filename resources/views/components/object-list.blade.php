@props(['objects'])
<ul class="divide-y divide-stone-200 rounded-lg border border-stone-200 bg-white dark:divide-stone-800 dark:border-stone-800 dark:bg-stone-900">
    @foreach ($objects as $object)
        <li>
            <a href="{{ route('objects.show', $object) }}" class="flex flex-wrap items-baseline justify-between gap-2 px-4 py-3 hover:bg-stone-50 dark:hover:bg-stone-800">
                <span>
                    <span class="font-medium">{{ $object->name }}</span>
                    @if ($object->name_romanized !== $object->name)
                        <span class="text-sm text-stone-500">· {{ $object->name_romanized }}</span>
                    @endif
                    @unless ($object->isOwned())
                        <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-950 dark:text-amber-200">Wanted</span>
                    @endunless
                </span>
                <span class="text-sm text-stone-500">
                    {{ collect([
                        $object->subCategory?->name,
                        $object->bookDetail?->artist?->name ?? $object->musicDetail?->artist?->name,
                        $object->gameDetail?->platform?->name,
                    ])->filter()->join(' · ') }}
                </span>
            </a>
        </li>
    @endforeach
</ul>
