@php
    use App\Http\Controllers\Admin\LanguageController;
    $input = 'block w-full rounded-md border border-stone-300 px-3 py-1.5 text-sm dark:border-stone-700 dark:bg-stone-950';
    $secondary = 'rounded-md border border-stone-300 px-3 py-1.5 text-sm hover:bg-stone-100 disabled:cursor-not-allowed disabled:opacity-40 dark:border-stone-700 dark:hover:bg-stone-800';
    $primary = 'rounded-md bg-stone-900 px-4 py-1.5 text-sm font-medium text-white hover:bg-stone-700 dark:bg-stone-100 dark:text-stone-900 dark:hover:bg-stone-300';
@endphp
<x-admin-layout title="Languages">
    <h1 class="mb-1 text-2xl font-semibold">Languages</h1>
    <p class="mb-6 max-w-2xl text-sm text-stone-600 dark:text-stone-400">
        Offered when scanning and editing items. The language of a scan decides which catalogues are asked
        (Japanese → Rakuten and NDL, others → Google Books and Open Library), so the code can't be changed once added.
    </p>

    @error('language') <p class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">{{ $message }}</p> @enderror

    <ul class="mb-10 max-w-3xl divide-y divide-stone-200 rounded-lg border border-stone-200 bg-white dark:divide-stone-800 dark:border-stone-800 dark:bg-stone-900">
        @forelse ($languages as $language)
            @php($uses = LanguageController::uses($language))
            @php($bag = $errors->getBag('language-'.$language->id))
            <li class="flex flex-wrap items-center gap-3 px-4 py-3" data-language="{{ $language->code }}">
                <code class="w-16 shrink-0 font-mono text-sm text-stone-500">{{ $language->code }}</code>
                <form method="POST" action="{{ route('admin.languages.update', $language) }}" class="flex min-w-0 flex-1 items-center gap-2">
                    @csrf
                    @method('PATCH')
                    <label for="language-name-{{ $language->id }}" class="sr-only">Name for {{ $language->code }}</label>
                    <input id="language-name-{{ $language->id }}" name="name" required maxlength="100"
                           value="{{ $bag->any() ? old('name') : $language->name }}" class="{{ $input }} max-w-56">
                    <button class="{{ $secondary }}">Save</button>
                </form>
                <span class="text-sm text-stone-500">{{ $uses ? 'Used by '.$uses : 'Not used' }}</span>
                <form method="POST" action="{{ route('admin.languages.destroy', $language) }}"
                      onsubmit="return confirm('Remove {{ e($language->name) }}?')">
                    @csrf
                    @method('DELETE')
                    <button class="{{ $secondary }} text-red-700 dark:text-red-400" @disabled($uses)
                            @if ($uses) title="In use, so it can't be removed" @endif>Remove</button>
                </form>
                @if ($bag->any()) <p class="basis-full text-sm text-red-600 dark:text-red-400">{{ $bag->first() }}</p> @endif
            </li>
        @empty
            <li class="px-4 py-3 text-sm text-stone-500">No languages yet. Add one below.</li>
        @endforelse
    </ul>

    <section class="max-w-3xl">
        <h2 class="mb-3 text-lg font-semibold">Add a language</h2>
        <form method="POST" action="{{ route('admin.languages.store') }}" class="flex flex-wrap items-end gap-3 rounded-lg border border-stone-200 bg-white p-4 dark:border-stone-800 dark:bg-stone-900">
            @csrf
            <div class="w-28">
                <label for="new-code" class="mb-1 block text-sm font-medium">Code</label>
                <input id="new-code" name="code" required maxlength="12" placeholder="e.g. fr" value="{{ old('code') }}" class="{{ $input }} font-mono" autocomplete="off" spellcheck="false">
            </div>
            <div class="min-w-48 flex-1">
                <label for="new-name" class="mb-1 block text-sm font-medium">Name</label>
                <input id="new-name" name="name" required maxlength="100" placeholder="e.g. French" value="{{ old('name') }}" class="{{ $input }}">
            </div>
            <button class="{{ $primary }}">Add</button>
            @if ($errors->create->any())
                <ul class="basis-full text-sm text-red-600 dark:text-red-400">
                    @foreach ($errors->create->all() as $message) <li>{{ $message }}</li> @endforeach
                </ul>
            @endif
        </form>
        <p class="mt-2 text-sm text-stone-500">Use the two-letter ISO 639-1 code (<code>ja</code>, <code>en</code>, <code>ko</code>…), so catalogue lookups recognise it.</p>
    </section>
</x-admin-layout>
