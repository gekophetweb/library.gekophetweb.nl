<x-layout :title="$shelf->series->name" :share="$share ?? null">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            @if ($links->hasHome())
                <a href="{{ $links->home() }}" class="text-sm text-stone-500 underline">Library</a>
            @endif
            <h1 class="mt-1 text-2xl font-semibold">{{ $shelf->series->name }}</h1>
            @if ($shelf->series->name_romanized && $shelf->series->name_romanized !== $shelf->series->name)
                <p class="text-stone-500">{{ $shelf->series->name_romanized }}</p>
            @endif
        </div>
        @if ($links->canEdit())
            <div class="flex flex-wrap items-center gap-2">
                @if ($shelf->series->isSyncable())
                    <form method="POST" action="{{ route('library.series.sync', $shelf->series) }}">
                        @csrf
                        <button class="rounded-md border border-stone-300 px-3 py-1.5 text-sm hover:bg-stone-100 dark:border-stone-700 dark:hover:bg-stone-800">Fetch volumes</button>
                    </form>
                @endif
                <x-share-button :scope="'series:'.$shelf->series->id" label="Share this series" />
            </div>
        @endif
    </div>
    @if ($links->canEdit() && $shelf->series->isSyncable())
        <p class="mt-1 text-xs text-stone-500" data-synced>
            Volume list from {{ ['ndl' => 'NDL', 'wikidata' => 'Wikidata'][$shelf->series->external_source] }}
            · {{ $shelf->series->synced_at ? 'updated '.$shelf->series->synced_at->diffForHumans() : 'not fetched yet' }}
        </p>
    @endif

    @if ($shelf->volumes->isNotEmpty())
        @php($wanted = $shelf->volumes->where('state', 'wanted')->count())
        <p class="mb-4 mt-2 text-sm text-stone-600 dark:text-stone-400">
            {{-- The owner's wording stays as it was; viewers get a neutral variant. --}}
            @if ($links->isShared())
                {{ $shelf->ownedCount }} of {{ $shelf->volumes->count() }} volumes owned
                @if ($wanted)
                    · {{ $wanted }} on the wishlist
                @endif
            @else
                You own {{ $shelf->ownedCount }} of {{ $shelf->volumes->count() }} volumes
                @if ($wanted)
                    · {{ $wanted }} on your wishlist
                @endif
            @endif
        </p>

        <ol class="mb-8 grid grid-cols-[repeat(auto-fill,minmax(9rem,1fr))] gap-2">
            @foreach ($shelf->volumes as $entry)
                @php($volume = $entry['volume'])
                <li data-volume="{{ $volume->volume_number }}" data-state="{{ $entry['state'] }}"
                    @class(['rounded-lg border px-3 py-2 text-sm',
                        'border-emerald-300 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950' => $entry['state'] === 'owned',
                        'border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950' => $entry['state'] === 'wanted',
                        'border-dashed border-stone-300 text-stone-500 dark:border-stone-700' => $entry['state'] === 'missing'])>
                    @if ($entry['object'])
                        <a href="{{ $links->object($entry['object']) }}" class="font-medium underline-offset-2 hover:underline">Vol. {{ $volume->volume_number }}</a>
                    @else
                        <span class="font-medium">Vol. {{ $volume->volume_number }}</span>
                    @endif
                    @if ($volume->isSpecialEdition())
                        <span class="block text-xs">{{ $volume->edition }}</span>
                    @endif
                    <span class="block text-xs">{{ ['owned' => 'Owned', 'wanted' => 'Wanted', 'missing' => 'Missing'][$entry['state']] }}</span>
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
