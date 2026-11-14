{{-- Save or replace the credentials for $provider ($saved / $stored as in _status). Inherits $input/$secondary.
     A provider with a second credential (Rakuten's access key) always takes both together. --}}
<div class="flex flex-wrap items-end gap-2">
    <form method="POST" action="{{ route('settings.api-keys.update', $provider->value) }}" class="flex min-w-0 flex-1 flex-wrap items-end gap-2">
        @csrf
        @method('PUT')
        <div class="min-w-48 flex-1">
            <label for="key-{{ $provider->value }}" @class(['mb-1 block text-xs font-medium text-stone-600 dark:text-stone-400', 'sr-only' => ! $provider->needsSecret()])>{{ $provider->keyLabel() }}</label>
            <input id="key-{{ $provider->value }}" name="key" type="password" autocomplete="off" required maxlength="500"
                   placeholder="{{ $saved ? 'New '.strtolower($provider->keyLabel()) : 'Paste your '.strtolower($provider->keyLabel()) }}" class="{{ $input }}">
        </div>
        @if ($provider->needsSecret())
            <div class="min-w-48 flex-1">
                <label for="secret-{{ $provider->value }}" class="mb-1 block text-xs font-medium text-stone-600 dark:text-stone-400">{{ $provider->secretLabel() }}</label>
                <input id="secret-{{ $provider->value }}" name="secret" type="password" autocomplete="off" required maxlength="500"
                       placeholder="{{ $saved ? 'New '.strtolower($provider->secretLabel()) : 'Paste your '.strtolower($provider->secretLabel()) }}" class="{{ $input }}">
            </div>
        @endif
        <button class="{{ $secondary }}">Save</button>
    </form>
    @if ($stored)
        <form method="POST" action="{{ route('settings.api-keys.destroy', $provider->value) }}">
            @csrf
            @method('DELETE')
            <button class="{{ $secondary }}">Remove</button>
        </form>
    @endif
</div>
@error('secret') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
