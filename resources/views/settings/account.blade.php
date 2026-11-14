<x-settings-layout title="Delete account">
    <h1 class="mb-4 text-2xl font-semibold text-red-700 dark:text-red-400">Delete account</h1>
    <div class="rounded-lg border border-red-200 bg-white p-4 dark:border-red-900 dark:bg-stone-900">
        <p class="mb-2 text-sm">This permanently deletes your account and <strong>cannot be undone</strong>:</p>
        <ul class="mb-3 list-disc space-y-0.5 pl-5 text-sm text-stone-600 dark:text-stone-400">
            <li>your whole collection and wishlist, series and scan batches;</li>
            <li>your shared links, phone app tokens, API keys and linked sign-in methods.</li>
        </ul>
        <p class="mb-4 text-sm text-stone-600 dark:text-stone-400">Suggestions and comments you wrote stay for others to read, shown as <em>[deleted user]</em>. Your votes are removed.</p>

        <form method="POST" action="{{ route('settings.account.destroy') }}" class="space-y-3"
              onsubmit="return confirm('Delete your account and your whole collection? This cannot be undone.')">
            @csrf
            @method('DELETE')
            <div>
                <label for="confirm_email" class="mb-1 block text-sm font-medium">Type your e-mail address (<span class="font-mono">{{ $user->email }}</span>) to confirm</label>
                <input id="confirm_email" name="confirm_email" type="email" required autocomplete="off" class="block w-full rounded-md border border-stone-300 px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-950">
                @error('confirm_email', 'deleteAccount') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>
            <button class="rounded-md bg-red-700 px-4 py-2 text-sm font-medium text-white hover:bg-red-800 dark:bg-red-600 dark:hover:bg-red-700">Delete my account</button>
        </form>
    </div>
</x-settings-layout>
