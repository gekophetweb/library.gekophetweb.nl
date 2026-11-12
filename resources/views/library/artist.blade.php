<x-layout :title="$artist->name">
    <a href="{{ route('home') }}" class="text-sm text-stone-500 underline">Library</a>
    <h1 class="mt-1 text-2xl font-semibold">{{ $artist->name }}</h1>
    @if ($artist->name_romanized && $artist->name_romanized !== $artist->name)
        <p class="text-stone-500">{{ $artist->name_romanized }}</p>
    @endif
    @if ($artist->name_english)
        <p class="text-stone-500">{{ $artist->name_english }}</p>
    @endif

    @if ($series->isNotEmpty())
        <section class="mb-8 mt-6">
            <h2 class="mb-3 text-lg font-semibold">Series</h2>
            <x-series-list :series="$series" />
        </section>
    @endif

    <section class="mt-6">
        <h2 class="mb-3 text-lg font-semibold">Items <span class="text-sm font-normal text-stone-500">· {{ $objects->count() }}</span></h2>
        @if ($objects->isEmpty())
            <p class="text-stone-500">Nothing by {{ $artist->name }} in your library.</p>
        @else
            <x-object-list :objects="$objects" />
        @endif
    </section>
</x-layout>
