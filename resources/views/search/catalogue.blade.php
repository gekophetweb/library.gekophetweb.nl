@php
    $input = 'block w-full rounded-md border border-stone-300 px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-950';
    $primary = 'rounded-md bg-stone-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-stone-700 dark:bg-stone-100 dark:text-stone-900 dark:hover:bg-stone-300';
@endphp
<x-layout title="Find books">
    <h1 class="mb-1 text-2xl font-semibold">Find books you don’t own yet</h1>
    <p class="mb-6 text-sm text-stone-500">Searches the online catalogues by title or author. Put a book on your wishlist without having it in hand.</p>

    <form method="GET" action="{{ route('search.index') }}" class="mb-8 grid max-w-3xl gap-3 sm:grid-cols-[1fr_auto_auto_auto_auto] sm:items-end">
        <input type="hidden" name="scope" value="catalogue">
        <div>
            <label for="catalogue-q" class="mb-1 block text-sm font-medium">Title or author</label>
            <input id="catalogue-q" name="q" value="{{ $term }}" maxlength="255" required class="{{ $input }}">
        </div>
        <div>
            <label for="catalogue-category" class="mb-1 block text-sm font-medium">Category</label>
            <select id="catalogue-category" name="category_id" class="{{ $input }}">
                @foreach ($categories as $option)
                    <option value="{{ $option->id }}" @selected($option->is($category))>{{ $option->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="catalogue-sub" class="mb-1 block text-sm font-medium">Add as</label>
            <select id="catalogue-sub" name="sub_category_id" class="{{ $input }}">
                <option value="">—</option>
                @foreach ($categories as $option)
                    @if ($option->children->isNotEmpty())
                        <optgroup label="{{ $option->name }}">
                            @foreach ($option->children as $child)
                                <option value="{{ $child->id }}" @selected($subCategory?->is($child))>{{ $child->name }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                @endforeach
            </select>
        </div>
        <div>
            <label for="catalogue-language" class="mb-1 block text-sm font-medium">Language</label>
            <select id="catalogue-language" name="language_id" class="{{ $input }}">
                @foreach ($languages as $option)
                    <option value="{{ $option->id }}" @selected($option->is($language))>{{ $option->name }}</option>
                @endforeach
            </select>
        </div>
        <button class="{{ $primary }} py-2">Search</button>
    </form>

    @if ($outcome)
        @if (! empty($outcome->missingKeys))
            <p class="mb-4 text-sm text-amber-700 dark:text-amber-300">
                Skipped {{ collect($outcome->missingKeys)->map->label()->join(', ') }}: no key saved. <a href="{{ route('settings.edit') }}" class="underline">Add your key in settings</a> for more results.
            </p>
        @endif

        @if ($outcome->noProvider)
            <p class="text-stone-500" data-empty>There is no online catalogue for {{ $category->name }} yet.</p>
        @elseif (empty($outcome->results))
            <p class="text-stone-500" data-empty>No books found for “{{ $term }}”.</p>
        @else
            <ul class="max-w-3xl divide-y divide-stone-200 rounded-lg border border-stone-200 bg-white dark:divide-stone-800 dark:border-stone-800 dark:bg-stone-900">
                @foreach ($outcome->results as $result)
                    @php($have = $existing->get($result->barcode))
                    <li class="flex gap-4 px-4 py-3" data-isbn="{{ $result->barcode }}" data-state="{{ $have ? $have->ownership->value : 'none' }}">
                        @if ($result->coverUrl)
                            <img src="{{ $result->coverUrl }}" alt="" loading="lazy" class="h-20 w-14 flex-none rounded border border-stone-200 object-cover dark:border-stone-800">
                        @else
                            <div class="h-20 w-14 flex-none rounded border border-dashed border-stone-300 dark:border-stone-700"></div>
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="font-medium">{{ $result->name }}</p>
                            <p class="text-sm text-stone-500">
                                {{ collect([$result->creator, $result->publisher, $result->releaseDate ? substr($result->releaseDate, 0, 4) : null])->filter()->join(' · ') }}
                            </p>
                            <p class="font-mono text-xs text-stone-400">{{ $result->barcode }}</p>
                        </div>
                        <div class="flex-none self-center text-right">
                            @if ($have)
                                <a href="{{ route('objects.show', $have) }}" @class(['rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200' => $have->isOwned(),
                                    'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200' => ! $have->isOwned()])>{{ $have->isOwned() ? 'Owned' : 'On wishlist' }}</a>
                            @else
                                <form method="POST" action="{{ route('wishlist.store') }}">
                                    @csrf
                                    <input type="hidden" name="category_id" value="{{ $category->id }}">
                                    @if ($subCategory) <input type="hidden" name="sub_category_id" value="{{ $subCategory->id }}"> @endif
                                    <input type="hidden" name="language_id" value="{{ $language->id }}">
                                    @foreach (['source' => $result->source, 'barcode' => $result->barcode, 'name' => $result->name, 'name_romanized' => $result->nameRomanized, 'name_english' => $result->nameEnglish, 'creator' => $result->creator, 'publisher' => $result->publisher, 'release_date' => $result->releaseDate, 'cover_url' => $result->coverUrl] as $field => $value)
                                        @if ($value !== null) <input type="hidden" name="{{ $field }}" value="{{ $value }}"> @endif
                                    @endforeach
                                    <button class="{{ $primary }}">Add to wishlist</button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    @endif
</x-layout>
