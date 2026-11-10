<x-layout title="Settings">
    @php
        $input = 'block w-full rounded-md border border-stone-300 px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-950';
        $primary = 'rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 dark:bg-stone-100 dark:text-stone-900 dark:hover:bg-stone-300';
        $secondary = 'rounded-md border border-stone-300 px-3 py-1.5 text-sm hover:bg-stone-100 dark:border-stone-700 dark:hover:bg-stone-800';
    @endphp

    <h1 class="mb-8 text-2xl font-semibold">Settings</h1>

    <section class="mb-10 max-w-md">
        <h2 class="mb-3 text-lg font-semibold">Profile</h2>
        <form method="POST" action="{{ route('settings.update') }}" class="space-y-3">
            @csrf
            @method('PUT')
            <label for="name" class="block text-sm font-medium">Name</label>
            <input id="name" name="name" required maxlength="255" value="{{ old('name', $user->name) }}" class="{{ $input }}">
            @error('name') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            <p class="text-sm text-stone-500">E-mail: {{ $user->email }}</p>
            <button class="{{ $primary }}">Save</button>
        </form>
    </section>

    <section class="mb-10 max-w-2xl">
        <h2 class="mb-1 text-lg font-semibold">API keys</h2>
        <p class="mb-4 text-sm text-stone-600 dark:text-stone-400">Lookups use your own keys. A saved key is never shown again; enter a new one to replace it.</p>
        @error('key') <p class="mb-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        <ul class="divide-y divide-stone-200 rounded-lg border border-stone-200 bg-white dark:divide-stone-800 dark:border-stone-800 dark:bg-stone-900">
            @foreach ($apiKeyProviders as $provider)
                @php($saved = $savedApiKeys->has($provider->value))
                <li class="space-y-2 px-4 py-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium">{{ $provider->label() }}</span>
                        @if ($saved)
                            <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">Key saved</span>
                        @endif
                    </div>
                    <p class="text-sm text-stone-500">{{ $provider->hint() }}</p>
                    <div class="flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('settings.api-keys.update', $provider->value) }}" class="flex flex-1 gap-2">
                            @csrf
                            @method('PUT')
                            <label for="key-{{ $provider->value }}" class="sr-only">{{ $provider->label() }} key</label>
                            <input id="key-{{ $provider->value }}" name="key" type="password" autocomplete="off" required maxlength="500"
                                   placeholder="{{ $saved ? 'New key' : 'Key' }}" class="{{ $input }} min-w-0 flex-1">
                            <button class="{{ $secondary }}">Save</button>
                        </form>
                        @if ($saved)
                            <form method="POST" action="{{ route('settings.api-keys.destroy', $provider->value) }}">
                                @csrf
                                @method('DELETE')
                                <button class="{{ $secondary }}">Remove</button>
                            </form>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="mb-10 max-w-2xl">
        <h2 class="mb-1 text-lg font-semibold">Phone app tokens</h2>
        <p class="mb-4 text-sm text-stone-600 dark:text-stone-400">The phone app signs in to the API with a token. Revoke one to sign that device out.</p>

        @if (session('token'))
            <div class="mb-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm dark:border-amber-800 dark:bg-amber-950">
                <p class="mb-1 font-medium">Your new token (shown once):</p>
                <code class="block break-all font-mono" id="new-token">{{ session('token') }}</code>
                <button type="button" class="mt-2 underline" onclick="navigator.clipboard.writeText(document.getElementById('new-token').textContent)">Copy</button>
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
    </section>

    <section class="max-w-2xl">
        <h2 class="mb-1 text-lg font-semibold">Linked sign-in methods</h2>
        <p class="mb-4 text-sm text-stone-600 dark:text-stone-400">You can also always sign in with a code sent to {{ $user->email }}, unless an admin switched e-mail codes off.</p>

        @error('sso') <p class="mb-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        @error('identity') <p class="mb-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

        <ul class="divide-y divide-stone-200 rounded-lg border border-stone-200 bg-white dark:divide-stone-800 dark:border-stone-800 dark:bg-stone-900">
            @forelse ($identities as $identity)
                <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <div>
                        <p class="font-medium">{{ $identity->provider->name }}</p>
                        <p class="text-sm text-stone-500">{{ $identity->email ?? 'No e-mail shared' }}</p>
                    </div>
                    <form method="POST" action="{{ route('settings.identities.destroy', $identity) }}">
                        @csrf
                        @method('DELETE')
                        <button class="{{ $secondary }}">Unlink</button>
                    </form>
                </li>
            @empty
                <li class="px-4 py-3 text-sm text-stone-500">No SSO provider linked yet.</li>
            @endforelse
        </ul>

        @if ($linkable->isNotEmpty())
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach ($linkable as $provider)
                    <a href="{{ route('sso.redirect', $provider) }}" class="{{ $secondary }}">Link {{ $provider->name }}</a>
                @endforeach
            </div>
        @endif
    </section>
</x-layout>
