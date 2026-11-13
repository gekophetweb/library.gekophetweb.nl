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

    <section class="mb-10 max-w-2xl" id="shares">
        <h2 class="mb-1 text-lg font-semibold">Shared links</h2>
        <p class="mb-4 text-sm text-stone-600 dark:text-stone-400">Anyone with a link can browse what it covers, read only, without an account. Revoke a link to stop it working.</p>

        @if (session('share_url'))
            <div class="mb-4 rounded-md border border-emerald-300 bg-emerald-50 p-3 text-sm dark:border-emerald-800 dark:bg-emerald-950">
                <p class="mb-1 font-medium">Your new link:</p>
                <code class="block break-all font-mono" id="new-share">{{ session('share_url') }}</code>
                <button type="button" class="mt-2 underline" onclick="navigator.clipboard.writeText(document.getElementById('new-share').textContent)">Copy</button>
            </div>
        @endif

        <form method="POST" action="{{ route('settings.shares.store') }}" class="mb-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto_auto] sm:items-end">
            @csrf
            <div>
                <label for="share-scope" class="mb-1 block text-sm font-medium">What to share</label>
                <select id="share-scope" name="scope" class="{{ $input }}">
                    <option value="library">Whole library</option>
                    <optgroup label="Category">
                        @foreach ($shareCategories as $category)
                            <option value="category:{{ $category->id }}" @selected(old('scope') === 'category:'.$category->id)>{{ $category->name }}</option>
                        @endforeach
                    </optgroup>
                    @if ($shareSeries->isNotEmpty())
                        <optgroup label="Series">
                            @foreach ($shareSeries as $series)
                                <option value="series:{{ $series->id }}" @selected(old('scope') === 'series:'.$series->id)>{{ $series->name }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                </select>
            </div>
            <div>
                <label for="share-label" class="mb-1 block text-sm font-medium">Label <span class="font-normal text-stone-500">(optional)</span></label>
                <input id="share-label" name="label" maxlength="100" value="{{ old('label') }}" placeholder="e.g. For my brother" class="{{ $input }}">
            </div>
            <div>
                <label for="share-expires" class="mb-1 block text-sm font-medium">Expires <span class="font-normal text-stone-500">(optional)</span></label>
                <input id="share-expires" name="expires_at" type="date" value="{{ old('expires_at') }}" class="{{ $input }}">
            </div>
            <button class="{{ $secondary }} py-2">Create link</button>
        </form>
        @error('scope_id') <p class="mb-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        @error('scope_type') <p class="mb-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        @error('expires_at') <p class="mb-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

        @if ($shares->isNotEmpty())
            <ul class="divide-y divide-stone-200 rounded-lg border border-stone-200 bg-white dark:divide-stone-800 dark:border-stone-800 dark:bg-stone-900">
                @foreach ($shares as $share)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3" data-share="{{ $share->id }}">
                        <div class="min-w-0">
                            <p class="font-medium">{{ $share->label ?? $share->scopeLabel() }}</p>
                            <p class="text-sm text-stone-500">
                                @if ($share->label) {{ $share->scopeLabel() }} · @endif
                                @if ($share->expires_at === null) Never expires
                                @elseif ($share->isUsable()) Expires {{ $share->expires_at->toFormattedDateString() }}
                                @else <span class="text-red-600 dark:text-red-400">Expired {{ $share->expires_at->toFormattedDateString() }}</span>
                                @endif
                            </p>
                            <code class="block break-all font-mono text-xs text-stone-500" id="share-{{ $share->id }}">{{ $share->url() }}</code>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" class="{{ $secondary }}" onclick="navigator.clipboard.writeText(document.getElementById('share-{{ $share->id }}').textContent)">Copy</button>
                            <form method="POST" action="{{ route('settings.shares.destroy', $share) }}">
                                @csrf
                                @method('DELETE')
                                <button class="{{ $secondary }}">Revoke</button>
                            </form>
                        </div>
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
