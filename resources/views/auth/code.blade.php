<x-auth-card title="Enter your code">
    <p class="mb-4 text-sm text-stone-600 dark:text-stone-400">
        If <strong>{{ $email }}</strong> can sign in, a 6-digit code is on its way. It works once and
        expires in {{ $minutes }} minutes.
    </p>

    <form method="POST" action="{{ route('login.code.verify') }}" class="space-y-3">
        @csrf
        <label for="code" class="block text-sm font-medium">Login code</label>
        <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus maxlength="20"
               class="block w-full rounded-md border border-stone-300 px-3 py-2 text-center font-mono text-lg tracking-[0.4em] dark:border-stone-700 dark:bg-stone-950">
        @error('code')
            <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
        <button class="w-full rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 dark:bg-stone-100 dark:text-stone-900 dark:hover:bg-stone-300">Log in</button>
    </form>

    <a href="{{ route('login') }}" class="mt-4 block text-center text-sm text-stone-600 underline dark:text-stone-400">Use another address</a>
</x-auth-card>
