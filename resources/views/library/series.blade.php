<x-layout :title="$shelf->series->display_name" :share="$share ?? null">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            @if ($links->hasHome())
                <a href="{{ $links->home() }}" class="text-sm text-stone-500 underline">Library</a>
            @endif
            <h1 class="mt-1 text-2xl font-semibold">{{ $shelf->series->display_name }}</h1>
            @if (filled($shelf->series->name_english))
                <p class="text-stone-500" data-original-name>{{ $shelf->series->name }}</p>
            @endif
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
    @if ($links->canEdit())
        <details class="mt-2 max-w-md" @if ($errors->has('name_english')) open @endif>
            <summary class="cursor-pointer text-sm text-stone-500 underline">English name</summary>
            <form method="POST" action="{{ route('library.series.update', $shelf->series) }}" class="mt-2 flex flex-wrap items-start gap-2">
                @csrf
                @method('PATCH')
                <div class="min-w-0 flex-1">
                    <label for="name_english" class="sr-only">English name</label>
                    <input id="name_english" name="name_english" maxlength="255" value="{{ old('name_english', $shelf->series->name_english) }}" placeholder="Shown instead of {{ $shelf->series->name }}" class="block w-full rounded-md border border-stone-300 px-3 py-1.5 text-sm dark:border-stone-700 dark:bg-stone-950">
                    @error('name_english') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <button class="rounded-md border border-stone-300 px-3 py-1.5 text-sm hover:bg-stone-100 dark:border-stone-700 dark:hover:bg-stone-800">Save</button>
            </form>
        </details>
    @endif
    @if ($links->canEdit() && $shelf->series->isSyncable())
        <p class="mt-1 text-xs text-stone-500" data-synced>
            Volume list from {{ \App\Models\Series::sourceLabel($shelf->series->external_source) }}
            · {{ $shelf->series->synced_at ? 'updated '.$shelf->series->synced_at->diffForHumans() : 'not fetched yet' }}
        </p>
    @endif

    @if ($shelf->volumes->isNotEmpty())
        @php($wanted = $shelf->volumes->where('state', 'wanted')->count())
        <p class="mb-4 mt-2 text-sm text-stone-600 dark:text-stone-400">
            {{-- The owner's wording stays as it was; viewers get a neutral variant. --}}
            @if ($links->isShared())
                {{ $shelf->ownedCount }} of {{ $shelf->volumeCount }} volumes owned
                @if ($wanted)
                    · {{ $wanted }} on the wishlist
                @endif
            @else
                You own {{ $shelf->ownedCount }} of {{ $shelf->volumeCount }} volumes
                @if ($wanted)
                    · {{ $wanted }} on your wishlist
                @endif
            @endif
        </p>
        @php($missing = $shelf->volumes->where('state', 'missing')->count())
        @if ($missing > 1 && $links->canEdit() && ($canWish ?? false))
            <form method="POST" action="{{ route('library.series.wish-missing', $shelf->series) }}" class="-mt-2 mb-4" data-wish-missing>
                @csrf
                <button class="rounded-md border border-stone-300 px-3 py-1.5 text-sm hover:bg-stone-100 dark:border-stone-700 dark:hover:bg-stone-800">Add all {{ $missing }} missing volumes to wishlist</button>
            </form>
        @endif

        <ol class="mb-8 grid grid-cols-[repeat(auto-fill,minmax(9rem,1fr))] gap-2">
            @foreach ($shelf->volumes as $entry)
                @php($volume = $entry['volume'])
                <li data-volume="{{ $volume->volume_number }}" data-state="{{ $entry['state'] }}"
                    @class(['rounded-lg border px-3 py-2 text-sm',
                        'border-emerald-300 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950' => $entry['state'] === 'owned',
                        'border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950' => $entry['state'] === 'wanted',
                        'border-dashed border-stone-300 text-stone-500 dark:border-stone-700' => $entry['state'] === 'missing',
                        'border-stone-200 text-stone-400 dark:border-stone-800 dark:text-stone-500' => $entry['state'] === 'other_edition'])>
                    @if ($entry['object'])
                        <a href="{{ $links->object($entry['object']) }}" class="font-medium underline-offset-2 hover:underline">Vol. {{ $volume->volume_number }}</a>
                    @else
                        <span class="font-medium">Vol. {{ $volume->volume_number }}</span>
                    @endif
                    @if ($volume->isSpecialEdition())
                        <span class="block text-xs">{{ $volume->edition }}</span>
                    @endif
                    <span class="block text-xs">{{ ['owned' => 'Owned', 'wanted' => 'Wanted', 'missing' => 'Missing', 'other_edition' => 'Other edition'][$entry['state']] }}</span>
                    @if (filled($volume->isbns))
                        {{-- The ISBN of the item you have first, so it is easy to compare with the book. --}}
                        <ul class="mt-1 space-y-0.5 font-mono text-[0.7rem] leading-tight text-stone-500 dark:text-stone-400" data-volume-isbns>
                            @foreach (collect($volume->isbns)->sortBy(fn ($isbn) => $isbn !== $entry['object']?->barcode) as $isbn)
                                <li @class(['font-semibold text-stone-700 dark:text-stone-200' => $isbn === $entry['object']?->barcode])>{{ $isbn }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($entry['state'] === 'missing' && $links->canEdit() && ($canWish ?? false))
                        <form method="POST" action="{{ route('library.series.volumes.wish', [$shelf->series, $volume]) }}" class="mt-1" data-wish-volume>
                            @csrf
                            <button class="text-xs text-stone-700 underline dark:text-stone-300">Add to wishlist</button>
                        </form>
                    @endif
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
