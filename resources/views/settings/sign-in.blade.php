<x-settings-layout title="Sign-in methods">
    @php($secondary = 'rounded-md border border-stone-300 px-3 py-1.5 text-sm hover:bg-stone-100 dark:border-stone-700 dark:hover:bg-stone-800')

    <h1 class="mb-1 text-2xl font-semibold">Linked sign-in methods</h1>
    <p class="mb-6 text-sm text-stone-600 dark:text-stone-400">You can also always sign in with a code sent to {{ $user->email }}, unless an admin switched e-mail codes off.</p>

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
</x-settings-layout>
