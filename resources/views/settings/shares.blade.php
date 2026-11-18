<x-settings-layout title="Shared links">
    @php
        $input = 'block w-full rounded-md border border-stone-300 px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-950';
        $secondary = 'rounded-md border border-stone-300 px-3 py-1.5 text-sm hover:bg-stone-100 dark:border-stone-700 dark:hover:bg-stone-800';
    @endphp

    <h1 class="mb-1 text-2xl font-semibold">Shared links</h1>
    <p class="mb-6 text-sm text-stone-600 dark:text-stone-400">Anyone with a link can browse what it covers, read only, without an account — unless you share it with one e-mail address: then only the account with that address can open it. Revoke a share to stop it working.</p>

    @if (session('share_url'))
        <div class="mb-4 rounded-md border border-emerald-300 bg-emerald-50 p-3 text-sm dark:border-emerald-800 dark:bg-emerald-950">
            <p class="mb-1 font-medium">Your new link:</p>
            <code class="block break-all font-mono" id="new-share">{{ session('share_url') }}</code>
            <x-copy-button :value="session('share_url')" class="mt-2" />
        </div>
    @endif

    <form method="POST" action="{{ route('settings.shares.store') }}" class="mb-4 grid gap-3 sm:grid-cols-2 sm:items-end">
        @csrf
        <div>
            <label for="share-scope" class="mb-1 block text-sm font-medium">What to share</label>
            <select id="share-scope" name="scope" class="{{ $input }}">
                <option value="library">Whole library</option>
                <optgroup label="Category">
                    @foreach ($shareCategories as $category)
                        <option value="category:{{ $category->id }}" @selected(old('scope') === 'category:'.$category->id)>{{ $category->name }}</option>
                    @endforeach
                </optgroup>
                @if ($shareSeries->isNotEmpty())
                    <optgroup label="Series">
                        @foreach ($shareSeries as $series)
                            <option value="series:{{ $series->id }}" @selected(old('scope') === 'series:'.$series->id)>{{ $series->display_name }}</option>
                        @endforeach
                    </optgroup>
                @endif
            </select>
        </div>
        <div>
            <label for="share-label" class="mb-1 block text-sm font-medium">Label <span class="font-normal text-stone-500">(optional)</span></label>
            <input id="share-label" name="label" maxlength="100" value="{{ old('label') }}" placeholder="e.g. For my brother" class="{{ $input }}">
        </div>
        <div>
            <label for="share-expires" class="mb-1 block text-sm font-medium">Expires <span class="font-normal text-stone-500">(optional)</span></label>
            <input id="share-expires" name="expires_at" type="date" value="{{ old('expires_at') }}" class="{{ $input }}">
        </div>
        <div>
            <label for="share-recipient" class="mb-1 block text-sm font-medium">Only for <span class="font-normal text-stone-500">(optional e-mail)</span></label>
            <input id="share-recipient" name="recipient_email" type="email" maxlength="255" value="{{ old('recipient_email') }}" placeholder="Leave empty for anyone with the link" class="{{ $input }}">
        </div>
        <button class="{{ $secondary }} py-2 sm:col-span-2 sm:justify-self-start">Create share</button>
    </form>
    @error('recipient_email') <p class="mb-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    @error('scope_id') <p class="mb-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    @error('scope_type') <p class="mb-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    @error('expires_at') <p class="mb-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

    @if ($shares->isNotEmpty())
        <ul class="divide-y divide-stone-200 rounded-lg border border-stone-200 bg-white dark:divide-stone-800 dark:border-stone-800 dark:bg-stone-900">
            @foreach ($shares as $share)
                <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3" data-share="{{ $share->id }}">
                    <div class="min-w-0">
                        <p class="font-medium">{{ $share->label ?? $share->scopeLabel() }}</p>
                        <p class="text-sm text-stone-500">
                            @if ($share->label) {{ $share->scopeLabel() }} · @endif
                            @if ($share->isPersonal()) <span data-recipient>Only for {{ $share->recipient_email }}</span> · @endif
                            @if ($share->expires_at === null) Never expires
                            @elseif ($share->isUsable()) Expires {{ $share->expires_at->toFormattedDateString() }}
                            @else <span class="text-red-600 dark:text-red-400">Expired {{ $share->expires_at->toFormattedDateString() }}</span>
                            @endif
                        </p>
                        <code class="block break-all font-mono text-xs text-stone-500" id="share-{{ $share->id }}">{{ $share->url() }}</code>
                    </div>
                    <div class="flex gap-2">
                        <x-copy-button :value="$share->url()" />
                        <form method="POST" action="{{ route('settings.shares.destroy', $share) }}">
                            @csrf
                            @method('DELETE')
                            <button class="{{ $secondary }}">Revoke</button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-settings-layout>
