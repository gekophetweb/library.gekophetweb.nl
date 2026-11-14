@php
    use App\Http\Controllers\Admin\CategoryController;
    $input = 'block w-full rounded-md border border-stone-300 px-3 py-1.5 text-sm dark:border-stone-700 dark:bg-stone-950';
    $secondary = 'rounded-md border border-stone-300 px-3 py-1.5 text-sm hover:bg-stone-100 disabled:cursor-not-allowed disabled:opacity-40 dark:border-stone-700 dark:hover:bg-stone-800';
@endphp
<x-admin-layout title="Categories">
    <h1 class="mb-1 text-2xl font-semibold">Categories</h1>
    <p class="mb-6 max-w-2xl text-sm text-stone-600 dark:text-stone-400">
        Books, Music and Games are fixed: each has its own kind of details. Under them you can add, rename,
        reorder and remove sub-categories. When a main category has sub-categories, scanning asks for one.
    </p>

    @error('category') <p class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">{{ $message }}</p> @enderror

    <div class="max-w-3xl space-y-8">
        @foreach ($categories as $parent)
            <section data-category="{{ $parent->slug }}">
                <h2 class="mb-2 text-lg font-semibold">{{ $parent->name }}</h2>
                <ul class="divide-y divide-stone-200 rounded-lg border border-stone-200 bg-white dark:divide-stone-800 dark:border-stone-800 dark:bg-stone-900">
                    @forelse ($parent->children as $child)
                        @php($uses = CategoryController::uses($child))
                        @php($bag = $errors->getBag('category-'.$child->id))
                        <li class="flex flex-wrap items-center gap-3 px-4 py-3" data-sub-category="{{ $child->slug }}">
                            <form method="POST" action="{{ route('admin.categories.update', $child) }}" class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                                @csrf
                                @method('PATCH')
                                <label for="category-name-{{ $child->id }}" class="sr-only">Name</label>
                                <input id="category-name-{{ $child->id }}" name="name" required maxlength="100"
                                       value="{{ $bag->any() ? old('name') : $child->name }}" class="{{ $input }} max-w-56">
                                <label for="category-sort-{{ $child->id }}" class="text-sm text-stone-500">Order</label>
                                <input id="category-sort-{{ $child->id }}" name="sort" type="number" min="0" max="1000" required
                                       value="{{ $bag->any() ? old('sort') : $child->sort }}" class="{{ $input }} w-20">
                                <button class="{{ $secondary }}">Save</button>
                            </form>
                            <span class="text-sm text-stone-500">{{ $uses ? 'Used by '.$uses : 'Not used' }}</span>
                            <form method="POST" action="{{ route('admin.categories.destroy', $child) }}"
                                  onsubmit="return confirm('Remove {{ e($child->name) }} from {{ e($parent->name) }}?')">
                                @csrf
                                @method('DELETE')
                                <button class="{{ $secondary }} text-red-700 dark:text-red-400" @disabled($uses)
                                        @if ($uses) title="In use, so it can't be removed" @endif>Remove</button>
                            </form>
                            @if ($bag->any()) <p class="basis-full text-sm text-red-600 dark:text-red-400">{{ $bag->first() }}</p> @endif
                        </li>
                    @empty
                        <li class="px-4 py-3 text-sm text-stone-500">No sub-categories: items go straight under {{ $parent->name }}.</li>
                    @endforelse

                    <li class="bg-stone-50 px-4 py-3 dark:bg-stone-950/40">
                        @php($createBag = $errors->getBag('create-'.$parent->id))
                        <form method="POST" action="{{ route('admin.categories.store', $parent) }}" class="flex flex-wrap items-center gap-2">
                            @csrf
                            <label for="new-sub-{{ $parent->id }}" class="sr-only">New sub-category of {{ $parent->name }}</label>
                            <input id="new-sub-{{ $parent->id }}" name="name" required maxlength="100" placeholder="New sub-category of {{ $parent->name }}"
                                   value="{{ $createBag->any() ? old('name') : '' }}" class="{{ $input }} max-w-64">
                            <button class="{{ $secondary }}">Add</button>
                            @if ($createBag->any()) <p class="basis-full text-sm text-red-600 dark:text-red-400">{{ $createBag->first() }}</p> @endif
                        </form>
                    </li>
                </ul>
            </section>
        @endforeach
    </div>
</x-admin-layout>
