<x-settings-layout title="API keys">
    @php
        $input = 'block w-full rounded-md border border-stone-300 px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-950';
        $secondary = 'rounded-md border border-stone-300 px-3 py-1.5 text-sm hover:bg-stone-100 dark:border-stone-700 dark:hover:bg-stone-800';
    @endphp

    <h1 class="mb-1 text-2xl font-semibold">API keys</h1>
    <p class="mb-6 text-sm text-stone-600 dark:text-stone-400">Lookups use your own keys. You need Google Books and Hardcover before you can scan; Rakuten is optional, and without it Japanese lookups skip Rakuten. Each is free and takes a few minutes to get: every provider has a step-by-step guide. A saved key is never shown again; enter a new one to replace it.</p>

    @error('api_keys') <p class="mb-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm dark:border-amber-800 dark:bg-amber-950" data-api-keys-missing>{{ $message }}</p> @enderror
    @error('key') <p class="mb-4 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

    <ul class="divide-y divide-stone-200 rounded-lg border border-stone-200 bg-white dark:divide-stone-800 dark:border-stone-800 dark:bg-stone-900">
        @foreach ($apiKeyProviders as $provider)
            @php($saved = ! in_array($provider, $missingApiKeys, true))
            @php($stored = in_array($provider->value, $storedApiKeys, true))
            <li class="space-y-2 px-4 py-3" data-provider="{{ $provider->value }}">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-medium">{{ $provider->label() }}</span>
                    @include('settings.api-keys._status')
                </div>
                <p class="text-sm text-stone-500">{{ $provider->hint() }}</p>
                <p class="text-sm">
                    <a href="{{ route('settings.api-keys.show', $provider->value) }}" class="font-medium underline underline-offset-2">How to get a {{ $provider->label() }} key</a>
                    <span class="text-stone-500">· step by step</span>
                </p>
                @include('settings.api-keys._form')
            </li>
        @endforeach
    </ul>
</x-settings-layout>
