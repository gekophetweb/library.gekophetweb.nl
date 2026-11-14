<x-settings-layout title="Profile">
    @php
        $input = 'block w-full rounded-md border border-stone-300 px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-950';
        $primary = 'rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 dark:bg-stone-100 dark:text-stone-900 dark:hover:bg-stone-300';
        $secondary = 'rounded-md border border-stone-300 px-3 py-1.5 text-sm hover:bg-stone-100 dark:border-stone-700 dark:hover:bg-stone-800';
    @endphp

    <h1 class="mb-6 text-2xl font-semibold">Profile</h1>

    <section class="mb-10 max-w-md">
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

    <section id="notifications">
        <h2 class="mb-3 text-lg font-semibold">E-mail</h2>
        <form method="POST" action="{{ route('settings.notifications') }}" class="flex flex-wrap items-center gap-3">
            @csrf
            @method('PUT')
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="suggestion_emails" value="1" @checked($user->suggestion_emails)>
                E-mail me about suggestions: replies and comments on mine{{ $user->is_admin ? ', and every new suggestion' : '' }}
            </label>
            <button class="{{ $secondary }}">Save</button>
        </form>
    </section>
</x-settings-layout>
