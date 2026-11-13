<x-layout title="Search">
    <h1 class="mb-1 text-2xl font-semibold">Search</h1>

    @if ($results === null)
        <p class="text-stone-500">Type a title, an author or an artist in the search box.</p>
    @else
        <p class="mb-6 text-sm text-stone-500">
            “{{ $term }}” in {{ strtolower($scope->label()) }}
        </p>

        @if ($results->objects->total() === 0 && $results->series->isEmpty() && $results->artists->isEmpty())
            <p class="text-stone-500" data-empty>Nothing in your library matches “{{ $term }}” ({{ strtolower($scope->label()) }}).</p>
        @endif

        @if ($results->objects->total() > 0)
            <section class="mb-8" id="items">
                <h2 class="mb-3 text-lg font-semibold">Items <span class="text-sm font-normal text-stone-500">· {{ $results->objects->total() }}</span></h2>
                <ul class="divide-y divide-stone-200 rounded-lg border border-stone-200 bg-white dark:divide-stone-800 dark:border-stone-800 dark:bg-stone-900">
                    @foreach ($results->objects as $object)
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
                                        $object->serie ? $object->serie->name.($object->bookDetail?->volume ? ' · vol. '.$object->bookDetail->volume : '') : null,
                                        $object->bookDetail?->artist?->name ?? $object->musicDetail?->artist?->name,
                                        $object->gameDetail?->platform?->name,
                                        $object->category->name,
                                    ])->filter()->join(' · ') }}
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-3">{{ $results->objects->links() }}</div>
            </section>
        @endif

        @if ($results->series->isNotEmpty())
            <section class="mb-8" id="series">
                <h2 class="mb-3 text-lg font-semibold">Series <span class="text-sm font-normal text-stone-500">· {{ $results->series->count() }}</span></h2>
                <x-series-list :series="$results->series" />
            </section>
        @endif

        @if ($results->artists->isNotEmpty())
            <section id="creators">
                <h2 class="mb-3 text-lg font-semibold">Authors &amp; artists <span class="text-sm font-normal text-stone-500">· {{ $results->artists->count() }}</span></h2>
                <ul class="divide-y divide-stone-200 rounded-lg border border-stone-200 bg-white dark:divide-stone-800 dark:border-stone-800 dark:bg-stone-900">
                    @foreach ($results->artists as $artist)
                        <li>
                            <a href="{{ route('library.artist', $artist) }}" class="block px-4 py-3 hover:bg-stone-50 dark:hover:bg-stone-800">
                                <span class="font-medium">{{ $artist->name }}</span>
                                @if ($artist->name_romanized && $artist->name_romanized !== $artist->name)
                                    <span class="text-sm text-stone-500">· {{ $artist->name_romanized }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endif
</x-layout>
