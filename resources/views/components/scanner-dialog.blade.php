@props(['categories', 'languages'])
@php($input = 'block w-full rounded-md border border-stone-300 px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-950')
<button type="button" onclick="document.getElementById('scanner-dialog').showModal()"
        class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 dark:bg-stone-100 dark:text-stone-900 dark:hover:bg-stone-300">
    Scan items
</button>

<dialog id="scanner-dialog" @if ($errors->hasAny(['category_id', 'sub_category_id', 'language_id', 'mode'])) open @endif
        class="w-full max-w-md rounded-xl border border-stone-200 bg-white p-6 text-stone-900 backdrop:bg-stone-950/50 dark:border-stone-800 dark:bg-stone-900 dark:text-stone-100">
    <h2 class="mb-4 text-lg font-semibold">What are you scanning?</h2>

    <form method="POST" action="{{ route('scan.start') }}" class="space-y-4">
        @csrf
        <div>
            <label for="scan-category" class="mb-1 block text-sm font-medium">Category</label>
            {{-- One list: main categories with their sub-categories underneath. --}}
            <select id="scan-category" name="category_id" required class="{{ $input }}">
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((int) old('category_id') === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            @error('category_id') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="scan-sub" class="mb-1 block text-sm font-medium">Sub-category</label>
            <select id="scan-sub" name="sub_category_id" class="{{ $input }}">
                <option value="">— (for a category without sub-categories)</option>
                @foreach ($categories as $category)
                    @if ($category->children->isNotEmpty())
                        <optgroup label="{{ $category->name }}">
                            @foreach ($category->children as $sub)
                                <option value="{{ $sub->id }}" @selected((int) old('sub_category_id') === $sub->id)>{{ $sub->name }}</option>
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

        <div class="flex justify-end gap-3">
            <button type="button" onclick="this.closest('dialog').close()" class="px-4 py-2 text-sm underline">Cancel</button>
            <button class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 dark:bg-stone-100 dark:text-stone-900 dark:hover:bg-stone-300">Start scanning</button>
        </div>
    </form>
</dialog>
