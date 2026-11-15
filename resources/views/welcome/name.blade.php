<x-auth-card title="Your name">
    <p class="mb-4 text-sm text-muted">Welcome! What should we call you?</p>

    <form method="POST" action="{{ route('welcome.name.store') }}" class="space-y-2.5">
        @csrf
        <label for="name" class="sr-only">Name</label>
        <input id="name" name="name" required autofocus maxlength="255" value="{{ old('name', $user->name) }}" class="field">
        @error('name')
            <p class="text-sm text-accent">{{ $message }}</p>
        @enderror
        <button class="btn btn-primary w-full">Continue</button>
    </form>
</x-auth-card>
