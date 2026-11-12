<x-layout :title="$shelf->category->name">
    @php($chip = 'rounded-full border px-3 py-1 text-sm')
    <a href="{{ route('home') }}" class="text-sm text-stone-500 underline">Library</a>
    <h1 class="mb-4 mt-1 text-2xl font-semibold">{{ $shelf->category->name }}</h1>

    @if ($shelf->subCategories->isNotEmpty())
        <nav class="mb-6 flex flex-wrap gap-2" aria-label="Sub-categories">
            <a href="{{ route('library.category', $shelf->category) }}" @class([$chip, 'border-stone-900 bg-stone-900 text-white dark:border-stone-100 dark:bg-stone-100 dark:text-stone-900' => ! $shelf->subCategory, 'border-stone-300 dark:border-stone-700' => $shelf->subCategory])>All</a>
            @foreach ($shelf->subCategories as $sub)
                <a href="{{ route('library.category', [$shelf->category, 'sub' => $sub->id]) }}" @class([$chip, 'border-stone-900 bg-stone-900 text-white dark:border-stone-100 dark:bg-stone-100 dark:text-stone-900' => $shelf->subCategory?->is($sub), 'border-stone-300 dark:border-stone-700' => ! $shelf->subCategory?->is($sub)])>{{ $sub->name }}</a>
            @endforeach
        </nav>
    @endif

    @if ($shelf->series->isEmpty() && $shelf->standalone->isEmpty() && $shelf->wishlist->isEmpty())
        <p class="text-stone-500">Nothing here yet.</p>
    @endif

    @if ($shelf->series->isNotEmpty())
        <section class="mb-8">
            <h2 class="mb-3 text-lg font-semibold">Series</h2>
            <ul class="divide-y divide-stone-200 rounded-lg border border-stone-200 bg-white dark:divide-stone-800 dark:border-stone-800 dark:bg-stone-900">
                @foreach ($shelf->series as $series)
                    <li>
                        <a href="{{ route('library.series', $series) }}" class="flex flex-wrap items-baseline justify-between gap-2 px-4 py-3 hover:bg-stone-50 dark:hover:bg-stone-800">
                            <span><span class="font-medium">{{ $series->name }}</span>
                                @if ($series->artist) <span class="text-sm text-stone-500">· {{ $series->artist->name }}</span> @endif</span>
                            <span class="text-sm text-stone-500">
                                {{ $series->items_count }} owned
                                @if ($series->wanted_count)
                                    · {{ $series->wanted_count }} wanted
                                @endif
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($shelf->standalone->isNotEmpty())
        <section>
            <h2 class="mb-3 text-lg font-semibold">{{ $shelf->series->isEmpty() ? 'Items' : 'Other items' }}</h2>
            <x-object-list :objects="$shelf->standalone" />
        </section>
    @endif

    @if ($shelf->wishlist->isNotEmpty())
        <section class="mt-8" id="wishlist">
            <h2 class="mb-3 text-lg font-semibold">Wishlist <span class="text-sm font-normal text-stone-500">· {{ $shelf->wishlist->count() }} wanted</span></h2>
            <x-object-list :objects="$shelf->wishlist" />
        </section>
    @endif
</x-layout>
