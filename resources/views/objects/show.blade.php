@php
    use App\Enums\CategoryKind;
    $input = 'block w-full rounded-md border border-stone-300 px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-950';
    $label = 'mb-1 block text-sm font-medium';
    $artist = $object->bookDetail?->artist ?? $object->musicDetail?->artist;
    $selectedLanguages = collect(old('languages', $object->languages->pluck('id')->all()))->map(fn ($id) => (int) $id);
    $selectedGenres = collect(old('genres', $object->genres->pluck('id')->all()))->map(fn ($id) => (int) $id);
@endphp
<x-layout :title="$object->name" :share="$share ?? null">
    @php
        $back = $object->serie ? $links->series($object->serie) : $links->category($object->category);
    @endphp
    @if ($back)
        <a href="{{ $back }}" class="text-sm text-stone-500 underline">
            {{ $object->serie?->name ?? $object->category->name }}
        </a>
    @endif
    <h1 class="mt-1 text-2xl font-semibold">{{ $object->name }}</h1>
    @if ($object->name_romanized !== $object->name) <p class="text-stone-500">{{ $object->name_romanized }}</p> @endif
    @if ($object->name_english) <p class="text-stone-500">{{ $object->name_english }}</p> @endif

    <dl class="my-6 grid max-w-2xl grid-cols-[max-content_1fr] gap-x-6 gap-y-2 text-sm">
        <dt class="text-stone-500">Status</dt>
        <dd>
            <span data-ownership="{{ $object->ownership->value }}" @class(['rounded-full px-2 py-0.5 text-xs font-medium',
                'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200' => $object->isOwned(),
                'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200' => ! $object->isOwned()])>{{ $object->ownership->label() }}</span>
        </dd>
        @foreach (array_filter([
            'Category' => collect([$object->category->name, $object->subCategory?->name])->filter()->join(' › '),
            'Series' => $object->serie ? $object->serie->name.($object->bookDetail?->volume ? ' · vol. '.$object->bookDetail->volume : '') : null,
            $kind === CategoryKind::Music ? 'Artist' : 'Author' => $artist?->name,
            'Platform' => $object->gameDetail?->platform?->name,
            'Languages' => $object->languages->pluck('name')->join(', '),
            'Genres' => $object->genres->pluck('name')->join(', '),
            'Publisher' => $object->publisher?->name,
            'Released' => $object->release_date?->toFormattedDateString(),
            'Barcode' => $object->barcode,
        ]) as $term => $value)
            <dt class="text-stone-500">{{ $term }}</dt>
            <dd>{{ $value }}</dd>
        @endforeach
    </dl>

    {{-- Viewers of a share link get the details only: nothing below this line writes. --}}
    @if ($links->canEdit())
    @if ($artist)
        <p class="-mt-3 mb-6 text-sm"><a href="{{ $links->artist($artist) }}" class="underline">More by {{ $artist->name }}</a></p>
    @endif

    @if ($object->series_suggestion && ! $object->serie)
        @php
            $suggested = $object->series_suggestion;
        @endphp
        <div class="mb-6 max-w-2xl rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm dark:border-sky-900 dark:bg-sky-950" data-series-suggestion>
            <p class="mb-3">
                This looks like
                @if ($suggested['volume']) volume {{ $suggested['volume'] }} of @endif
                <span class="font-medium">{{ $suggested['reference']['name'] }}</span>
                <span class="text-stone-500">(from {{ ['ndl' => 'NDL', 'wikidata' => 'Wikidata', 'openlibrary' => 'Open Library', 'title' => 'the title'][$suggested['source']] ?? $suggested['source'] }}, not certain)</span>.
            </p>
            <div class="flex gap-2">
                <form method="POST" action="{{ route('objects.series-suggestion.accept', $object) }}">
                    @csrf
                    <button class="rounded-md bg-stone-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-stone-700 dark:bg-stone-100 dark:text-stone-900 dark:hover:bg-stone-300">Add to this series</button>
                </form>
                <form method="POST" action="{{ route('objects.series-suggestion.dismiss', $object) }}">
                    @csrf
                    @method('DELETE')
                    <button class="rounded-md border border-stone-300 px-3 py-1.5 text-sm dark:border-stone-700">Not this series</button>
                </form>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('objects.ownership', $object) }}" class="mb-4">
        @csrf
        @method('PATCH')
        <input type="hidden" name="ownership" value="{{ $object->isOwned() ? 'wishlist' : 'owned' }}">
        <button class="rounded-md border border-stone-300 px-4 py-2 text-sm hover:bg-stone-100 dark:border-stone-700 dark:hover:bg-stone-800">
            {{ $object->isOwned() ? 'Move to wishlist' : 'Mark as owned' }}
        </button>
    </form>

    <details class="max-w-2xl rounded-lg border border-stone-200 bg-white p-4 dark:border-stone-800 dark:bg-stone-900" @if ($errors->any()) open @endif>
        <summary class="cursor-pointer font-medium">Edit</summary>

        <form method="POST" action="{{ route('objects.update', $object) }}" class="mt-4 space-y-4">
            @csrf
            @method('PUT')

            @foreach (['name' => 'Title (original)', 'name_romanized' => 'Title (romanized)', 'name_english' => 'Title (English, optional)'] as $field => $text)
                <div>
                    <label for="{{ $field }}" class="{{ $label }}">{{ $text }}</label>
                    <input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $object->$field) }}" @required($field !== 'name_english') maxlength="255" class="{{ $input }}">
                    @error($field) <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
            @endforeach

            <div>
                <label for="ownership" class="{{ $label }}">Status</label>
                <select id="ownership" name="ownership" class="{{ $input }}">
                    @foreach (App\Enums\OwnershipStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(old('ownership', $object->ownership->value) === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
                @error('ownership') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            @if ($options['subCategories']->isNotEmpty())
                <div>
                    <label for="sub_category_id" class="{{ $label }}">Sub-category</label>
                    <select id="sub_category_id" name="sub_category_id" class="{{ $input }}">
                        <option value="">—</option>
                        @foreach ($options['subCategories'] as $sub)
                            <option value="{{ $sub->id }}" @selected((int) old('sub_category_id', $object->sub_category_id) === $sub->id)>{{ $sub->name }}</option>
                        @endforeach
                    </select>
                    @error('sub_category_id') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
            @endif

            <fieldset>
                <legend class="{{ $label }}">Languages</legend>
                <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                    @foreach ($options['languages'] as $language)
                        <label class="flex items-center gap-1"><input type="checkbox" name="languages[]" value="{{ $language->id }}" @checked($selectedLanguages->contains($language->id))> {{ $language->name }}</label>
                    @endforeach
                </div>
                @error('languages') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </fieldset>

            @if ($options['genres']->isNotEmpty())
                <fieldset>
                    <legend class="{{ $label }}">Genres</legend>
                    <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                        @foreach ($options['genres'] as $genre)
                            <label class="flex items-center gap-1"><input type="checkbox" name="genres[]" value="{{ $genre->id }}" @checked($selectedGenres->contains($genre->id))> {{ $genre->name }}</label>
                        @endforeach
                    </div>
                    @error('genres.*') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </fieldset>
            @endif

            @php
                $selects = [
                    'publisher_id' => ['Publisher', $options['publishers'], $object->publisher_id],
                    'serie_id' => ['Series', $options['series'], $object->serie_id],
                ];
                if ($kind !== CategoryKind::Games) {
                    $selects['artist_id'] = [$kind === CategoryKind::Music ? 'Artist' : 'Author', $options['artists'], $artist?->id];
                } else {
                    $selects['platform_id'] = ['Platform', $options['platforms'], $object->gameDetail?->platform_id];
                }
            @endphp
            @foreach ($selects as $field => [$text, $choices, $current])
                <div>
                    <label for="{{ $field }}" class="{{ $label }}">{{ $text }}</label>
                    <select id="{{ $field }}" name="{{ $field }}" class="{{ $input }}">
                        <option value="">—</option>
                        @foreach ($choices as $choice)
                            <option value="{{ $choice->id }}" @selected((int) old($field, $current) === $choice->id)>{{ $choice->name }}</option>
                        @endforeach
                    </select>
                    @error($field) <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
            @endforeach

            @if ($kind === CategoryKind::Books)
                <div>
                    <label for="volume" class="{{ $label }}">Volume</label>
                    <input id="volume" name="volume" value="{{ old('volume', $object->bookDetail?->volume) }}" maxlength="20" placeholder="e.g. 3 or 3.5" class="{{ $input }} w-32">
                    @error('volume') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
            @endif

            <div>
                <label for="release_date" class="{{ $label }}">Release date</label>
                <input id="release_date" name="release_date" type="date" value="{{ old('release_date', $object->release_date?->toDateString()) }}" class="{{ $input }} w-48">
                @error('release_date') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            <button class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 dark:bg-stone-100 dark:text-stone-900 dark:hover:bg-stone-300">Save</button>
        </form>
    </details>
    @endif
</x-layout>
