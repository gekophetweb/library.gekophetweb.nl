{{-- Save or replace a key for $provider; $saved says whether one is stored. Inherits $input/$secondary. --}}
<div class="flex flex-wrap gap-2">
    <form method="POST" action="{{ route('settings.api-keys.update', $provider->value) }}" class="flex min-w-0 flex-1 gap-2">
        @csrf
        @method('PUT')
        <label for="key-{{ $provider->value }}" class="sr-only">{{ $provider->label() }} key</label>
        <input id="key-{{ $provider->value }}" name="key" type="password" autocomplete="off" required maxlength="500"
               placeholder="{{ $saved ? 'New key' : 'Paste your key' }}" class="{{ $input }} min-w-0 flex-1">
        <button class="{{ $secondary }}">Save</button>
    </form>
    @if ($saved)
        <form method="POST" action="{{ route('settings.api-keys.destroy', $provider->value) }}">
            @csrf
            @method('DELETE')
            <button class="{{ $secondary }}">Remove</button>
        </form>
    @endif
</div>
