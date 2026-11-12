<x-layout :title="$shelf->series->name">
    <a href="{{ route('home') }}" class="text-sm text-stone-500 underline">Library</a>
    <h1 class="mt-1 text-2xl font-semibold">{{ $shelf->series->name }}</h1>
    @if ($shelf->series->name_romanized && $shelf->series->name_romanized !== $shelf->series->name)
        <p class="text-stone-500">{{ $shelf->series->name_romanized }}</p>
    @endif

    @if ($shelf->volumes->isNotEmpty())
        <p class="mb-4 mt-2 text-sm text-stone-600 dark:text-stone-400">You own {{ $shelf->ownedCount }} of {{ $shelf->volumes->count() }} volumes</p>

        <ol class="mb-8 grid grid-cols-[repeat(auto-fill,minmax(9rem,1fr))] gap-2">
            @foreach ($shelf->volumes as $entry)
                @php($volume = $entry['volume'])
                <li data-volume="{{ $volume->volume_number }}" data-state="{{ $entry['state'] }}"
                    @class(['rounded-lg border px-3 py-2 text-sm',
                        'border-emerald-300 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950' => $entry['state'] === 'owned',
                        'border-dashed border-stone-300 text-stone-500 dark:border-stone-700' => $entry['state'] === 'missing'])>
                    @if ($entry['object'])
                        <a href="{{ route('objects.show', $entry['object']) }}" class="font-medium underline-offset-2 hover:underline">Vol. {{ $volume->volume_number }}</a>
                    @else
                        <span class="font-medium">Vol. {{ $volume->volume_number }}</span>
                    @endif
                    @if ($volume->isSpecialEdition())
                        <span class="block text-xs">{{ $volume->edition }}</span>
                    @endif
                    <span class="block text-xs">{{ $entry['state'] === 'owned' ? 'Owned' : 'Missing' }}</span>
                </li>
            @endforeach
        </ol>
    @else
        <p class="mb-4 mt-2 text-sm text-stone-600 dark:text-stone-400">{{ trans_choice(':count item|:count items', $shelf->itemCount) }}</p>
    @endif

    @if ($shelf->unmatched->isNotEmpty())
        <h2 class="mb-3 text-lg font-semibold">{{ $shelf->volumes->isEmpty() ? 'Items' : 'Not matched to a known volume' }}</h2>
        <x-object-list :objects="$shelf->unmatched" />
    @endif
</x-layout>
