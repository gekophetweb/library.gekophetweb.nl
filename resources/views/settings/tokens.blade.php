<x-settings-layout title="Phone app">
    @php
        $input = 'block w-full rounded-md border border-stone-300 px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-950';
        $secondary = 'rounded-md border border-stone-300 px-3 py-1.5 text-sm hover:bg-stone-100 dark:border-stone-700 dark:hover:bg-stone-800';
    @endphp

    <h1 class="mb-1 text-2xl font-semibold">Phone app tokens</h1>
    <p class="mb-6 text-sm text-stone-600 dark:text-stone-400">The phone app signs in to the API with a token. Revoke one to sign that device out.</p>

    @if (session('token'))
        <div class="mb-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm dark:border-amber-800 dark:bg-amber-950">
            <p class="mb-1 font-medium">Your new token (shown once):</p>
            <code class="block break-all font-mono" id="new-token">{{ session('token') }}</code>
            <x-copy-button :value="session('token')" class="mt-2" />
        </div>
    @endif

    <form method="POST" action="{{ route('settings.tokens.store') }}" class="mb-4 flex gap-2">
        @csrf
        <label for="token-name" class="sr-only">Device name</label>
        <input id="token-name" name="name" required maxlength="100" placeholder="Device name, e.g. My phone" class="{{ $input }} min-w-0 flex-1">
        <button class="{{ $secondary }}">Create token</button>
    </form>
    @error('name') <p class="mb-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

    @if ($tokens->isNotEmpty())
        <ul class="divide-y divide-stone-200 rounded-lg border border-stone-200 bg-white dark:divide-stone-800 dark:border-stone-800 dark:bg-stone-900">
            @foreach ($tokens as $token)
                <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <div>
                        <p class="font-medium">{{ $token->name }}</p>
                        <p class="text-sm text-stone-500">Created {{ $token->created_at->toFormattedDateString() }} · {{ $token->last_used_at ? 'last used '.$token->last_used_at->diffForHumans() : 'never used' }}</p>
                    </div>
                    <form method="POST" action="{{ route('settings.tokens.destroy', $token->id) }}">
                        @csrf
                        @method('DELETE')
                        <button class="{{ $secondary }}">Revoke</button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif
</x-settings-layout>
