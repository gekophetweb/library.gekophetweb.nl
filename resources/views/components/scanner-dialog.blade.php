{{-- category / subCategory preselect the form, e.g. on a category page with its sub-category filter. --}}
@props(['categories', 'languages', 'category' => null, 'subCategory' => null])
@php($input = 'field text-sm')
<button type="button" onclick="document.getElementById('scanner-dialog').showModal()" class="btn btn-primary min-h-12 rounded-xl px-5 text-[15px]">
    <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 7V5a1 1 0 0 1 1-1h2M17 4h2a1 1 0 0 1 1 1v2M20 17v2a1 1 0 0 1-1 1h-2M7 20H5a1 1 0 0 1-1-1v-2M8 8v8M11 8v8M14 8v8M17 8v8"/></svg>
    Scan items
</button>

<dialog id="scanner-dialog" @if ($errors->hasAny(['category_id', 'sub_category_id', 'language_id', 'mode', 'ownership'])) open @endif
        class="m-auto w-full max-w-md rounded-[18px] border border-line bg-surface p-6 text-ink shadow-card backdrop:bg-stone-950/60">
    @php($missingKeys = auth()->user()->missingScanKeys())
    @if ($missingKeys !== [])
        <h2 class="mb-4 text-lg font-semibold">Add your API keys first</h2>
        <p class="mb-2 text-sm">Scanning looks items up with your own keys. You still need:</p>
        <ul class="mb-4 list-disc pl-5 text-sm" data-missing-keys>
            @foreach ($missingKeys as $provider)
                <li>{{ $provider->label() }}</li>
            @endforeach
        </ul>
        <p class="mb-4 text-sm text-stone-600 dark:text-stone-400">They are free; Settings explains step by step how to get each one.</p>
        <div class="flex justify-end gap-3">
            <button type="button" onclick="this.closest('dialog').close()" class="px-4 py-2 text-sm underline">Cancel</button>
            <a href="{{ route('settings.api-keys') }}" class="rounded-md bg-accent px-4 py-2 text-sm font-semibold text-accent-ink shadow-card hover:opacity-90">Go to API keys</a>
        </div>
    @else
    <h2 class="mb-4 text-lg font-semibold">What are you scanning?</h2>

    <form method="POST" action="{{ route('scan.start') }}" class="space-y-4">
        @csrf
        <div>
            <label for="scan-category" class="mb-1 block text-sm font-medium">Category</label>
            {{-- One list: main categories with their sub-categories underneath. --}}
            <select id="scan-category" name="category_id" required class="{{ $input }}">
                @foreach ($categories as $option)
                    <option value="{{ $option->id }}" @selected((int) old('category_id', $category?->id) === $option->id)>{{ $option->name }}</option>
                @endforeach
            </select>
            @error('category_id') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="scan-sub" class="mb-1 block text-sm font-medium">Sub-category</label>
            <select id="scan-sub" name="sub_category_id" class="{{ $input }}">
                <option value="">— (for a category without sub-categories)</option>
                @foreach ($categories as $option)
                    @if ($option->children->isNotEmpty())
                        <optgroup label="{{ $option->name }}">
                            @foreach ($option->children as $sub)
                                <option value="{{ $sub->id }}" @selected((int) old('sub_category_id', $subCategory?->id) === $sub->id)>{{ $sub->name }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                @endforeach
            </select>
            @error('sub_category_id') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="scan-language" class="mb-1 block text-sm font-medium">Language</label>
            <select id="scan-language" name="language_id" required class="{{ $input }}">
                @foreach ($languages as $language)
                    <option value="{{ $language->id }}" @selected((int) old('language_id') === $language->id)>{{ $language->name }}</option>
                @endforeach
            </select>
            @error('language_id') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        <fieldset>
            <legend class="mb-1 block text-sm font-medium">Mode</legend>
            <label class="mr-4 text-sm"><input type="radio" name="mode" value="single" @checked(old('mode', 'single') === 'single')> One item</label>
            <label class="text-sm"><input type="radio" name="mode" value="batch" @checked(old('mode') === 'batch')> Many items (batch)</label>
            @error('mode') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </fieldset>

        <fieldset>
            <legend class="mb-1 block text-sm font-medium">Add as</legend>
            <label class="mr-4 text-sm"><input type="radio" name="ownership" value="owned" @checked(old('ownership', 'owned') === 'owned')> I own these</label>
            <label class="text-sm"><input type="radio" name="ownership" value="wishlist" @checked(old('ownership') === 'wishlist')> Wishlist</label>
            @error('ownership') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </fieldset>

        <div class="flex justify-end gap-3">
            <button type="button" onclick="this.closest('dialog').close()" class="px-4 py-2 text-sm underline">Cancel</button>
            <button class="rounded-md bg-accent px-4 py-2 text-sm font-semibold text-accent-ink shadow-card hover:opacity-90">Start scanning</button>
        </div>
    </form>
    @endif
</dialog>
