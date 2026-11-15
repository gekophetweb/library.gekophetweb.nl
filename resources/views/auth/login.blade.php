<x-auth-card title="Log in">
    @if ($errors->any())
        <p class="mb-4 rounded-lg bg-accent-soft px-3 py-2 text-sm text-accent">{{ $errors->first() }}</p>
    @endif

    @if ($providers->isNotEmpty())
        <div class="space-y-3">
            @foreach ($providers as $provider)
                <a href="{{ route('sso.redirect', $provider) }}" class="btn btn-secondary w-full">Continue with {{ $provider->name }}</a>
            @endforeach
        </div>
    @endif

    @if ($emailCodes)
        @if ($providers->isNotEmpty())
            <p class="my-5 flex items-center gap-3 text-xs tracking-[0.14em] text-muted uppercase">
                <span class="h-px flex-1 bg-line"></span>or<span class="h-px flex-1 bg-line"></span>
            </p>
        @endif

        <form method="POST" action="{{ route('login.code.request') }}" class="space-y-2.5">
            @csrf
            <label for="email" class="block text-[13px] font-semibold">E-mail address</label>
            <input id="email" name="email" type="email" required autocomplete="email" value="{{ old('email') }}" placeholder="you@example.com" class="field">
            <button class="btn btn-primary w-full">E-mail me a login code</button>
            <p class="text-xs text-muted">No account yet? Entering your address creates one.</p>
        </form>
    @endif

    @if ($devLogin)
        <form method="POST" action="{{ route('login.dev') }}" class="mt-6 border-t border-dashed border-line-strong pt-5">
            @csrf
            <button class="btn w-full border border-line-strong font-medium text-muted hover:text-ink">Sign in as dev user (local only)</button>
        </form>
    @endif
</x-auth-card>
