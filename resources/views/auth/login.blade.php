<x-auth-card title="Log in">
    @php($button = 'block w-full rounded-md border border-stone-300 px-4 py-2 text-center text-sm font-medium hover:bg-stone-100 dark:border-stone-700 dark:hover:bg-stone-800')

    @if ($errors->any())
        <p class="mb-4 text-sm text-red-600 dark:text-red-400">{{ $errors->first() }}</p>
    @endif

    @if ($providers->isNotEmpty())
        <div class="space-y-2">
            @foreach ($providers as $provider)
                <a href="{{ route('sso.redirect', $provider) }}" class="{{ $button }}">Continue with {{ $provider->name }}</a>
            @endforeach
        </div>
    @endif

    @if ($emailCodes)
        @if ($providers->isNotEmpty())
            <p class="my-6 text-center text-xs uppercase tracking-wide text-stone-500">or</p>
        @endif

        <form method="POST" action="{{ route('login.code.request') }}" class="space-y-3">
            @csrf
            <label for="email" class="block text-sm font-medium">E-mail address</label>
            <input id="email" name="email" type="email" required autocomplete="email" value="{{ old('email') }}"
                   class="block w-full rounded-md border border-stone-300 px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-950">
            <button class="w-full rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 dark:bg-stone-100 dark:text-stone-900 dark:hover:bg-stone-300">E-mail me a login code</button>
            <p class="text-xs text-stone-500">No account yet? Entering your address creates one.</p>
        </form>
    @endif
</x-auth-card>
