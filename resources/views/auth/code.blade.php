<x-auth-card title="Enter your code">
    <p class="mb-4 text-sm text-muted">
        If <strong class="text-ink">{{ $email }}</strong> can sign in, a 6-digit code is on its way. It works once and
        expires in {{ $minutes }} minutes.
    </p>

    <form method="POST" action="{{ route('login.code.verify') }}" class="space-y-2.5">
        @csrf
        <label for="code" class="block text-[13px] font-semibold">Login code</label>
        <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus maxlength="20"
               class="field text-center font-mono text-lg tracking-[0.4em]">
        @error('code')
            <p class="text-sm text-accent">{{ $message }}</p>
        @enderror
        <button class="btn btn-primary w-full">Log in</button>
    </form>

    <a href="{{ route('login') }}" class="mt-4 block text-center text-sm text-muted underline hover:text-ink">Use another address</a>
</x-auth-card>
