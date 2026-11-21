<x-layout :title="$shelf->category->name" :share="$share ?? null">
    @php($chip = 'rounded-full border px-3 py-1 text-sm')
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            @if ($links->hasHome())
                <a href="{{ $links->home() }}" class="text-sm text-stone-500 underline">Library</a>
            @endif
            <h1 class="mb-4 mt-1 text-2xl font-semibold">{{ $shelf->category->name }}</h1>
        </div>
        @if ($links->canEdit())
            <div class="flex flex-wrap items-center gap-3">
                <x-share-button :scope="'category:'.$shelf->category->id" :label="'Share '.$shelf->category->name" />
                {{-- Preselects this category and the sub-category filter, if one is on. --}}
                <x-scanner-dialog :categories="$scanCategories" :languages="$languages"
                                  :category="$shelf->category" :sub-category="$shelf->subCategory" />
            </div>
        @endif
    </div>

    @php($on = 'border-stone-900 bg-stone-900 text-white dark:border-stone-100 dark:bg-stone-100 dark:text-stone-900')
    @php($off = 'border-stone-300 dark:border-stone-700')
    {{-- Each filter keeps the other one when it changes. --}}
    @php($withSub = array_filter(['sub' => $shelf->subCategory?->id]))
    @php($withLanguage = array_filter(['language' => $shelf->language?->id]))

    @if ($shelf->subCategories->isNotEmpty())
        <nav class="mb-3 flex flex-wrap gap-2" aria-label="Sub-categories">
            <a href="{{ $links->category($shelf->category, $withLanguage) }}" @class([$chip, $on => ! $shelf->subCategory, $off => $shelf->subCategory])>All</a>
            @foreach ($shelf->subCategories as $sub)
                <a href="{{ $links->category($shelf->category, ['sub' => $sub->id] + $withLanguage) }}" @class([$chip, $on => $shelf->subCategory?->is($sub), $off => ! $shelf->subCategory?->is($sub)])>{{ $sub->name }}</a>
            @endforeach
        </nav>
    @endif

    {{-- Only when the shelf holds more than one language: one chip per language on it. --}}
    @if ($shelf->languages->count() > 1)
        <nav class="mb-6 flex flex-wrap gap-2" aria-label="Languages" data-language-filter>
            <a href="{{ $links->category($shelf->category, $withSub) }}" @class([$chip, $on => ! $shelf->language, $off => $shelf->language])>All languages</a>
            @foreach ($shelf->languages as $language)
                <a href="{{ $links->category($shelf->category, $withSub + ['language' => $language->id]) }}" @class([$chip, $on => $shelf->language?->is($language), $off => ! $shelf->language?->is($language)])>{{ $language->name }}</a>
            @endforeach
        </nav>
    @elseif ($shelf->subCategories->isNotEmpty())
        <div class="mb-3"></div>
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
                        <a href="{{ $links->series($series) }}" class="flex flex-wrap items-baseline justify-between gap-2 px-4 py-3 hover:bg-stone-50 dark:hover:bg-stone-800">
                            <span><span class="font-medium">{{ $series->display_name }}</span>
                                @if ($series->original_name) <span class="text-stone-600 dark:text-stone-300" data-original-name>{{ $series->original_name }}</span> @endif
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
