<x-settings-layout title="Profile">
    <h1 class="mb-6 font-display text-4xl leading-none font-extrabold sm:text-[44px]">Profile</h1>

    <section class="card mb-6 p-6">
        <form method="POST" action="{{ route('settings.update') }}" class="max-w-md space-y-2.5">
            @csrf
            @method('PUT')
            <label for="name" class="block text-[13px] font-semibold">Name</label>
            <input id="name" name="name" required maxlength="255" value="{{ old('name', $user->name) }}" class="field">
            @error('name') <p class="text-sm text-accent">{{ $message }}</p> @enderror
            <p class="text-sm text-muted">E-mail: {{ $user->email }}</p>
            <button class="btn btn-primary">Save</button>
        </form>
    </section>

    <section class="card mb-6 p-6">
        <h2 class="mb-1 font-display text-[22px] font-bold">Appearance</h2>
        <p class="mb-4 text-sm text-muted">Theme, accent colour, background (your own photo too), heading font and density. The sun and moon button in the menu bar switches light and dark.</p>
        <a href="{{ route('settings.appearance') }}" class="btn btn-secondary">Set up your own style</a>
    </section>

    <section id="notifications" class="card p-6">
        <h2 class="mb-3 font-display text-[22px] font-bold">E-mail</h2>
        <form method="POST" action="{{ route('settings.notifications') }}" class="flex flex-wrap items-center gap-3">
            @csrf
            @method('PUT')
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="suggestion_emails" value="1" @checked($user->suggestion_emails) class="size-4 accent-[var(--accent)]">
                E-mail me about suggestions: replies and comments on mine{{ $user->is_admin ? ', and every new suggestion' : '' }}
            </label>
            <button class="btn btn-secondary">Save</button>
        </form>
    </section>
</x-settings-layout>
