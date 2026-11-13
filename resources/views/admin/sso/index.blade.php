<x-layout title="Sign-in methods">
    @php
        $secondary = 'rounded-md border border-stone-300 px-3 py-1.5 text-sm hover:bg-stone-100 dark:border-stone-700 dark:hover:bg-stone-800';
        $primary = 'rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 dark:bg-stone-100 dark:text-stone-900 dark:hover:bg-stone-300';
    @endphp

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold">Sign-in methods</h1>
        <a href="{{ route('admin.sso.create') }}" class="{{ $primary }}">Add SSO provider</a>
    </div>

    @if ($errors->any())
        <p class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">{{ $errors->first() }}</p>
    @endif

    <section class="mb-10">
        <h2 class="mb-3 text-lg font-semibold">SSO providers</h2>
        <ul class="divide-y divide-stone-200 rounded-lg border border-stone-200 bg-white dark:divide-stone-800 dark:border-stone-800 dark:bg-stone-900">
            @forelse ($providers as $provider)
                <li class="space-y-2 px-4 py-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium">{{ $provider->name }}</span>
                        <span class="rounded bg-stone-100 px-2 py-0.5 text-xs dark:bg-stone-800">{{ $provider->driver->label() }}</span>
                        @unless ($provider->enabled)
                            <span class="rounded bg-amber-100 px-2 py-0.5 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-200">Disabled</span>
                        @endunless
                        @if ($provider->allow_registration)
                            <span class="rounded bg-sky-100 px-2 py-0.5 text-xs text-sky-800 dark:bg-sky-950 dark:text-sky-200">Allows sign-up</span>
                        @endif
                        <span class="text-xs text-stone-500">{{ $provider->identities_count }} linked</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <span class="text-stone-500">Callback URL:</span>
                        <code class="break-all rounded bg-stone-100 px-1.5 py-0.5 text-xs dark:bg-stone-800" id="callback-{{ $provider->id }}">{{ route('sso.callback', $provider) }}</code>
                        <button type="button" class="text-xs underline" onclick="navigator.clipboard.writeText(document.getElementById('callback-{{ $provider->id }}').textContent)">Copy</button>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('admin.sso.edit', $provider) }}" class="{{ $secondary }}">Edit</a>
                        <a href="{{ route('admin.sso.test', $provider) }}" class="{{ $secondary }}">Test login</a>
                        <form method="POST" action="{{ route('admin.sso.destroy', $provider) }}" onsubmit="return confirm('Remove {{ e($provider->name) }}? Users lose this sign-in method.')">
                            @csrf
                            @method('DELETE')
                            <button class="{{ $secondary }} text-red-700 dark:text-red-400">Remove</button>
                        </form>
                    </div>
                </li>
            @empty
                <li class="px-4 py-4 text-sm text-stone-500">No SSO providers yet. Users sign in with e-mail codes.</li>
            @endforelse
        </ul>
    </section>

    <section class="max-w-xl">
        <h2 class="mb-3 text-lg font-semibold">E-mail login codes</h2>
        <form method="POST" action="{{ route('admin.sso.settings') }}" class="space-y-3 rounded-lg border border-stone-200 bg-white p-4 dark:border-stone-800 dark:bg-stone-900">
            @csrf
            @method('PUT')
            <input type="hidden" name="email_codes_enabled" value="0">
            <label class="flex items-start gap-3 text-sm">
                <input type="checkbox" name="email_codes_enabled" value="1" @checked($emailCodes) class="mt-1">
                <span><strong>Allow login with an e-mail code</strong><br><span class="text-stone-500">Lets people sign in without SSO. Cannot be switched off while no SSO provider is enabled.</span></span>
            </label>
            <input type="hidden" name="email_registration_enabled" value="0">
            <label class="flex items-start gap-3 text-sm">
                <input type="checkbox" name="email_registration_enabled" value="1" @checked($emailRegistration) class="mt-1">
                <span><strong>Allow sign-up with an e-mail code</strong><br><span class="text-stone-500">Any address can create an account. When off, only existing users get codes.</span></span>
            </label>
            <button class="{{ $primary }}">Save</button>
        </form>
    </section>
</x-layout>
