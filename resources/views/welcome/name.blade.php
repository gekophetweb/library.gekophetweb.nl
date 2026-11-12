<x-auth-card title="Your name">
    <p class="mb-4 text-sm text-stone-600 dark:text-stone-400">Welcome! What should we call you?</p>

    <form method="POST" action="{{ route('welcome.name.store') }}" class="space-y-3">
        @csrf
        <label for="name" class="sr-only">Name</label>
        <input id="name" name="name" required autofocus maxlength="255" value="{{ old('name', $user->name) }}"
               class="block w-full rounded-md border border-stone-300 px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-950">
        @error('name')
            <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
        <button class="w-full rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 dark:bg-stone-100 dark:text-stone-900 dark:hover:bg-stone-300">Continue</button>
    </form>
</x-auth-card>
