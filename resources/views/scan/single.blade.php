@php
    $input = 'block w-full rounded-md border border-stone-300 px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-950';
    $primary = 'rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 dark:bg-stone-100 dark:text-stone-900 dark:hover:bg-stone-300';
@endphp
<x-layout title="Scan">
    <a href="{{ route('home') }}" class="text-sm text-stone-500 underline">Library</a>
    <h1 class="mb-1 mt-1 text-2xl font-semibold">Scan one item</h1>
    <p class="mb-6 text-sm text-stone-500">
        {{ collect([$setup->category->name, $setup->subCategory?->name, $setup->language->name])->filter()->join(' · ') }}
        · <span data-ownership="{{ $setup->ownership->value }}" class="font-medium">{{ $setup->ownership === App\Enums\OwnershipStatus::Owned ? 'Adding to your collection' : 'Adding to your wishlist' }}</span>
    </p>

    <form method="POST" action="{{ route('scan.single.lookup') }}" class="mb-8 flex max-w-md gap-2">
        @csrf
        <label for="barcode" class="sr-only">Barcode</label>
        <input id="barcode" name="barcode" required autofocus inputmode="numeric" autocomplete="off" placeholder="Scan or type a barcode / ISBN" class="{{ $input }}">
        <button class="{{ $primary }}">Look up</button>
    </form>
    @error('barcode') <p class="-mt-6 mb-6 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

    @if ($pending)
        <section class="max-w-xl rounded-lg border border-stone-200 bg-white p-4 dark:border-stone-800 dark:bg-stone-900">
            <p class="mb-3 font-mono text-sm text-stone-500">{{ $pending['barcode'] }}</p>

            @if ($existing && ($pending['can_promote'] ?? false))
                <p class="mb-3">This is on your wishlist: <a href="{{ route('objects.show', $existing) }}" class="font-medium underline">{{ $existing->name }}</a>.</p>
                <form method="POST" action="{{ route('scan.single.add') }}">
                    @csrf
                    <button class="{{ $primary }}">Mark as owned</button>
                </form>
            @elseif ($existing)
                <p>This is already {{ $existing->isOwned() ? 'in your collection' : 'on your wishlist' }}: <a href="{{ route('objects.show', $existing) }}" class="font-medium underline">{{ $existing->name }}</a>.</p>
            @elseif ($result)
                <div class="mb-4 flex gap-4">
                    @if ($result->coverUrl)
                        <img src="{{ $result->coverUrl }}" alt="" class="h-32 w-auto rounded border border-stone-200 dark:border-stone-800">
                    @endif
                    <dl class="grid grid-cols-[max-content_1fr] gap-x-4 gap-y-1 text-sm">
                        <dt class="text-stone-500">Title</dt><dd class="font-medium">{{ $result->name }}</dd>
                        @if ($result->creator) <dt class="text-stone-500">By</dt><dd>{{ $result->creator }}</dd> @endif
                        @if ($result->publisher) <dt class="text-stone-500">Publisher</dt><dd>{{ $result->publisher }}</dd> @endif
                        @if ($result->releaseDate) <dt class="text-stone-500">Released</dt><dd>{{ $result->releaseDate }}</dd> @endif
                    </dl>
                </div>
                <form method="POST" action="{{ route('scan.single.add') }}">
                    @csrf
                    <button class="{{ $primary }}">{{ $setup->ownership->value === 'owned' ? 'Add to collection' : 'Add to wishlist' }}</button>
                </form>
            @else
                <p class="mb-3 font-medium">
                    @if ($pending['invalid'] ?? false) Not a valid barcode for {{ $setup->category->name }}.
                    @elseif ($pending['no_provider'] ?? false) There is no lookup for {{ $setup->category->name }} yet. Add it by hand:
                    @else Not found. Add it by hand:
                    @endif
                </p>
                @if (! empty($pending['missing_keys']))
                    <p class="mb-3 text-sm text-amber-700 dark:text-amber-300">
                        Skipped {{ implode(', ', $pending['missing_keys']) }}: no key saved. <a href="{{ route('settings.edit') }}" class="underline">Add your key in settings</a> for better results.
                    </p>
                @endif
                @unless ($pending['invalid'] ?? false)
                    <form method="POST" action="{{ route('scan.single.add') }}" class="space-y-3">
                        @csrf
                        <div>
                            <label for="name" class="mb-1 block text-sm font-medium">Title (original)</label>
                            <input id="name" name="name" required maxlength="255" value="{{ old('name') }}" class="{{ $input }}">
                            @error('name') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="name_romanized" class="mb-1 block text-sm font-medium">Title (romanized)</label>
                            <input id="name_romanized" name="name_romanized" required maxlength="255" value="{{ old('name_romanized') }}" class="{{ $input }}">
                            @error('name_romanized') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <button class="{{ $primary }}">{{ $setup->ownership->value === 'owned' ? 'Add to collection' : 'Add to wishlist' }}</button>
                    </form>
                @endunless
            @endif
        </section>
    @endif
</x-layout>
