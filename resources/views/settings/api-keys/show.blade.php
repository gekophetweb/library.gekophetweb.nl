<x-settings-layout :title="$provider->label().' key'">
    @php
        $input = 'block w-full rounded-md border border-stone-300 px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-950';
        $secondary = 'rounded-md border border-stone-300 px-3 py-1.5 text-sm hover:bg-stone-100 dark:border-stone-700 dark:hover:bg-stone-800';
        $box = 'rounded-lg border border-stone-200 bg-white p-4 dark:border-stone-800 dark:bg-stone-900';
    @endphp

    <p class="mb-2 text-sm"><a href="{{ route('settings.api-keys') }}" class="underline">← All API keys</a></p>
    <div class="mb-1 flex flex-wrap items-center gap-2">
        <h1 class="text-2xl font-semibold">How to get a {{ $provider->label() }} key</h1>
        @include('settings.api-keys._status')
    </div>
    <p class="mb-6 text-sm text-stone-600 dark:text-stone-400">{{ $provider->hint() }} Free; you need a {{ $provider->accountNeeded() }}.</p>

    <section class="mb-6 {{ $box }}">
        @include('settings.api-keys.guides.'.$provider->value)
    </section>

    <section class="{{ $box }}">
        <h2 class="mb-2 font-semibold">Paste your key</h2>
        @error('key') <p class="mb-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        @include('settings.api-keys._form')
    </section>
</x-settings-layout>
