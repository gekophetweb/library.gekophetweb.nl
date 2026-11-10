@php
    $editing = $provider->exists;
    $input = 'block w-full rounded-md border border-stone-300 px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-950';
    $driver = old('driver', $provider->driver?->value ?? 'google');
@endphp
<x-layout :title="$editing ? 'Edit '.$provider->name : 'Add SSO provider'">
    <h1 class="mb-6 text-2xl font-semibold">{{ $editing ? 'Edit '.$provider->name : 'Add SSO provider' }}</h1>

    <form method="POST" action="{{ $editing ? route('admin.sso.update', $provider) : route('admin.sso.store') }}" class="max-w-xl space-y-4">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div>
            <label for="driver" class="mb-1 block text-sm font-medium">Type</label>
            <select id="driver" name="driver" class="{{ $input }}">
                @foreach ($drivers as $option)
                    <option value="{{ $option->value }}" @selected($driver === $option->value)>{{ $option->label() }}</option>
                @endforeach
            </select>
            @error('driver') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        @foreach ([
            ['name', 'Button label', 'e.g. "Work account"', $provider->name],
            ['client_id', 'Client ID', null, $provider->client_id],
            ['tenant', 'Tenant (Microsoft only)', 'Directory ID or domain, or "common" / "organizations"', $provider->setting('tenant')],
            ['issuer', 'Issuer URL (OpenID Connect only)', 'https://auth.example.com/application/o/library/', $provider->setting('issuer')],
            ['scopes', 'Scopes (optional, OpenID Connect / Microsoft)', 'openid email profile', $provider->setting('scopes')],
        ] as [$field, $label, $placeholder, $value])
            <div>
                <label for="{{ $field }}" class="mb-1 block text-sm font-medium">{{ $label }}</label>
                <input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $value) }}" placeholder="{{ $placeholder }}" class="{{ $input }}">
                @error($field) <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>
        @endforeach

        <div>
            <label for="client_secret" class="mb-1 block text-sm font-medium">Client secret</label>
            {{-- Write-only: the stored secret is never sent back to the browser. --}}
            <input id="client_secret" name="client_secret" type="password" autocomplete="new-password" class="{{ $input }}"
                   placeholder="{{ $editing ? 'Leave empty to keep the current secret' : '' }}">
            @error('client_secret') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="sort" class="mb-1 block text-sm font-medium">Button order</label>
            <input id="sort" name="sort" type="number" min="0" max="1000" value="{{ old('sort', $provider->sort ?? 0) }}" class="{{ $input }} w-28">
        </div>

        <input type="hidden" name="enabled" value="0">
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $provider->enabled))> Enabled (shows a button on the login page)
        </label>
        @error('enabled') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

        <input type="hidden" name="allow_registration" value="0">
        <label class="flex items-start gap-2 text-sm">
            <input type="checkbox" name="allow_registration" value="1" class="mt-1" @checked(old('allow_registration', $provider->allow_registration))>
            <span>Allow sign-up: a verified e-mail without an account creates one</span>
        </label>

        @if ($editing)
            <p class="text-sm text-stone-500">Callback URL to register at the identity provider: <code class="break-all">{{ route('sso.callback', $provider) }}</code></p>
        @else
            <p class="text-sm text-stone-500">After saving, the callback URL to register at the identity provider is shown on the overview.</p>
        @endif

        <div class="flex gap-3">
            <button class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 dark:bg-stone-100 dark:text-stone-900 dark:hover:bg-stone-300">Save</button>
            <a href="{{ route('admin.sso.index') }}" class="px-4 py-2 text-sm underline">Cancel</a>
        </div>
    </form>
</x-layout>
