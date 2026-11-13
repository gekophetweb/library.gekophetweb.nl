@props(['suggestion' => null])
@php
    $input = 'block w-full rounded-md border border-stone-300 px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-950';
@endphp
{{-- Series + volume, pre-filled from the series lookup and editable. Empty the series to add the book on its own. --}}
<fieldset class="mb-4 grid gap-3 sm:grid-cols-[1fr_8rem]">
    <legend class="mb-1 text-sm font-medium">
        Series
        @if ($suggestion)
            <span class="font-normal text-stone-500" data-series-source="{{ $suggestion['source'] }}">· from {{ \App\Models\Series::sourceLabel($suggestion['source']) }}</span>
        @endif
    </legend>
    <div>
        <label for="series_name" class="sr-only">Series name</label>
        <input id="series_name" name="series_name" maxlength="255" value="{{ old('series_name', $suggestion['reference']['name'] ?? '') }}" placeholder="Not part of a series" class="{{ $input }}">
        @error('series_name') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="series_volume" class="sr-only">Volume</label>
        <input id="series_volume" name="series_volume" maxlength="20" value="{{ old('series_volume', $suggestion['volume'] ?? '') }}" placeholder="Volume" class="{{ $input }}">
        @error('series_volume') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>
</fieldset>
