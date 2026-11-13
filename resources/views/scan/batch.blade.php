@php
    use App\Models\ScanBatch;
    $input = 'block w-full rounded-md border border-stone-300 px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-950';
    $primary = 'rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 dark:bg-stone-100 dark:text-stone-900 dark:hover:bg-stone-300';
    $running = in_array($batch->status, [ScanBatch::QUEUED, ScanBatch::PROCESSING], true);
    $labels = ['pending' => 'Waiting', 'added' => 'Added', 'promoted' => 'Moved from wishlist', 'already_owned' => 'Already in collection', 'already_wanted' => 'Already on wishlist', 'not_found' => 'Not found', 'invalid' => 'Invalid barcode', 'failed' => 'Failed'];
    $toWishlist = $batch->ownership === App\Enums\OwnershipStatus::Wishlist;
@endphp
<x-layout title="Batch scan">
    @if ($running)
        {{-- No JavaScript needed: refresh while the queued job works through the list. --}}
        <meta http-equiv="refresh" content="3">
    @endif

    <a href="{{ route('home') }}" class="text-sm text-stone-500 underline">Library</a>
    <h1 class="mb-1 mt-1 text-2xl font-semibold">Batch scan</h1>
    <p class="mb-6 text-sm text-stone-500">
        {{ collect([$batch->category->name, $batch->subCategory?->name, $batch->language->name])->filter()->join(' · ') }}
        · <span data-ownership="{{ $batch->ownership->value }}" class="font-medium">{{ $toWishlist ? 'Adding to your wishlist' : 'Adding to your collection' }}</span>
    </p>

    @if ($batch->isDraft())
        <form method="POST" action="{{ route('scan.batches.items.store', $batch) }}" class="mb-2 flex max-w-md gap-2">
            @csrf
            <label for="barcode" class="sr-only">Barcode</label>
            <input id="barcode" name="barcode" required autofocus inputmode="numeric" autocomplete="off" placeholder="Scan or type a barcode / ISBN" class="{{ $input }}">
            <button class="rounded-md border border-stone-300 px-4 py-2 text-sm dark:border-stone-700">Add</button>
        </form>
        @error('barcode') <p class="mb-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        <p class="mb-6 text-sm text-stone-500">Items on this list are not in your {{ $toWishlist ? 'wishlist' : 'collection' }} yet. Commit the list when you are done.</p>
    @elseif ($running)
        <p class="mb-6 rounded-md bg-sky-50 px-4 py-3 text-sm text-sky-800 dark:bg-sky-950 dark:text-sky-200">Adding {{ $items->count() }} items in the background…</p>
    @else
        <p class="mb-6 rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
            Done: {{ $items->whereIn('status', ['added', 'promoted'])->count() }} of {{ $items->count() }} added.
        </p>
    @endif

    <ul class="mb-6 max-w-2xl divide-y divide-stone-200 rounded-lg border border-stone-200 bg-white dark:divide-stone-800 dark:border-stone-800 dark:bg-stone-900">
        @forelse ($items as $item)
            <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                <div>
                    <p class="font-mono text-sm">{{ $item->barcode }}</p>
                    @if ($item->object)
                        <a href="{{ route('objects.show', $item->object) }}" class="font-medium underline">{{ $item->object->name }}</a>
                    @endif
                    @if ($item->message) <p class="text-sm text-stone-500">{{ $item->message }}</p> @endif
                </div>
                @if ($batch->isDraft())
                    <form method="POST" action="{{ route('scan.batches.items.destroy', [$batch, $item]) }}">
                        @csrf
                        @method('DELETE')
                        <button class="text-sm underline">Remove</button>
                    </form>
                @else
                    <span class="text-sm text-stone-500" data-status="{{ $item->status }}">{{ $labels[$item->status] ?? $item->status }}</span>
                @endif
            </li>
        @empty
            <li class="px-4 py-3 text-sm text-stone-500">Nothing scanned yet.</li>
        @endforelse
    </ul>

    @if ($batch->isDraft() && $items->isNotEmpty())
        <form method="POST" action="{{ route('scan.batches.commit', $batch) }}">
            @csrf
            <button class="{{ $primary }}">Add {{ $items->count() }} {{ Str::plural('item', $items->count()) }} to my {{ $toWishlist ? 'wishlist' : 'collection' }}</button>
        </form>
    @endif
</x-layout>
