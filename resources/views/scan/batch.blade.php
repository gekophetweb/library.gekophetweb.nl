@php
    use App\Models\ScanBatch;
    $input = 'block w-full rounded-md border border-stone-300 px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-950';
    $primary = 'rounded-md bg-accent px-4 py-2 text-sm font-semibold text-accent-ink shadow-card hover:opacity-90';
    $running = in_array($batch->status, [ScanBatch::QUEUED, ScanBatch::PROCESSING], true);
    $labels = ['pending' => 'Waiting', 'added' => 'Added', 'promoted' => 'Moved from wishlist', 'already_owned' => 'Already in collection', 'already_wanted' => 'Already on wishlist', 'not_found' => 'Not found', 'invalid' => 'Invalid barcode', 'failed' => 'Failed'];
    $toWishlist = $batch->ownership === App\Enums\OwnershipStatus::Wishlist;
@endphp
<x-layout title="Batch scan">
    @if ($running)
        {{-- No JavaScript needed: refresh while the queued job works through the list. --}}
        <meta http-equiv="refresh" content="3">
    @endif

    <a href="{{ route('scan.batches.index') }}" class="text-sm text-stone-500 underline">Your scan batches</a>
    <h1 class="mb-1 mt-1 text-2xl font-semibold">Batch scan</h1>
    <p class="mb-6 text-sm text-stone-500">
        {{ collect([$batch->category->name, $batch->subCategory?->name, $batch->language->name])->filter()->join(' · ') }}
        · <span data-ownership="{{ $batch->ownership->value }}" class="font-medium">{{ $toWishlist ? 'Adding to your wishlist' : 'Adding to your collection' }}</span>
    </p>

    @if ($batch->isDraft())
        <x-camera-scanner mode="batch" target="barcodes" />

        <form method="POST" action="{{ route('scan.batches.items.store', $batch) }}" class="mb-2 flex max-w-md gap-2">
            @csrf
            <label for="barcode" class="sr-only">Barcode</label>
            <input id="barcode" name="barcode" required autofocus inputmode="numeric" autocomplete="off" placeholder="Scan or type a barcode / ISBN" class="{{ $input }}">
            <button class="shrink-0 whitespace-nowrap rounded-md border border-stone-300 px-4 py-2 text-sm dark:border-stone-700">Add</button>
        </form>
        @error('barcode') <p class="mb-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

        {{-- Bulk entry: a list typed or pasted from elsewhere, one ISBN / barcode per line. --}}
        <details class="mb-4 max-w-md" @if (old('barcodes') !== null || $errors->has('barcodes')) open @endif>
            <summary class="cursor-pointer text-sm font-medium underline underline-offset-2">Paste a list instead</summary>
            <form method="POST" action="{{ route('scan.batches.items.bulk', $batch) }}" class="mt-3 space-y-2">
                @csrf
                <label for="barcodes" class="block text-sm text-stone-600 dark:text-stone-400">One ISBN or barcode per line. Empty lines are ignored.</label>
                <textarea id="barcodes" name="barcodes" rows="8" required autocomplete="off" spellcheck="false"
                          placeholder="9780140449136&#10;978-0-14-044913-6&#10;4988601234567"
                          class="{{ $input }} font-mono">{{ old('barcodes') }}</textarea>
                @error('barcodes') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                @if ($skipped = session('bulk_skipped'))
                    <div class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-200" data-bulk-skipped>
                        <p class="font-medium">{{ count($skipped) }} {{ Str::plural('line', count($skipped)) }} not added (still in the box above):</p>
                        <ul class="mt-1 list-disc pl-5">
                            @foreach ($skipped as $line)
                                <li><span class="font-mono">{{ $line['line'] }}</span> {{ $line['reason'] }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <button class="rounded-md border border-stone-300 px-4 py-2 text-sm dark:border-stone-700">Add all to the list</button>
            </form>
        </details>

        <p class="mb-6 text-sm text-stone-500">Items on this list are not in your {{ $toWishlist ? 'wishlist' : 'collection' }} yet. Commit the list when you are done.</p>
    @elseif ($running)
        <p class="mb-6 rounded-md bg-sky-50 px-4 py-3 text-sm text-sky-800 dark:bg-sky-950 dark:text-sky-200">Adding {{ $items->count() }} items in the background…</p>
    @else
        @php
            $added = $items->whereIn('status', ['added', 'promoted'])->count();
            $failed = $items->where('status', 'failed')->count();
            $notFound = $items->where('status', 'not_found')->count();
        @endphp
        <div @class([
            'mb-6 max-w-2xl rounded-md px-4 py-3 text-sm',
            'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200' => $failed === 0,
            'bg-amber-50 text-amber-900 dark:bg-amber-950 dark:text-amber-200' => $failed > 0,
        ]) data-batch-summary>
            <p class="font-medium">Done: {{ $added }} of {{ $items->count() }} added.</p>
            @if ($notFound || $failed)
                <p class="mt-1">
                    @if ($notFound) <span class="font-medium text-red-600 dark:text-red-400" data-not-found>{{ $notFound }} not found</span> in the catalogues (add those by hand below).@endif
                    @if ($failed) {{ $failed }} could not be looked up because no catalogue answered.@endif
                </p>
            @endif
            @if ($failed)
                <form method="POST" action="{{ route('scan.batches.retry', $batch) }}" class="mt-2">
                    @csrf
                    <button class="rounded-md border border-amber-400 bg-white px-3 py-1.5 text-sm font-medium hover:bg-amber-100 dark:border-amber-700 dark:bg-stone-900 dark:hover:bg-stone-800">Try the {{ $failed }} failed {{ Str::plural('item', $failed) }} again</button>
                </form>
            @endif
        </div>
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
                    @if ($batch->status === ScanBatch::DONE && $item->status === 'not_found')
                        {{-- Only a code no catalogue knew can be added by hand. --}}
                        @php($bag = $errors->getBag("item{$item->id}"))
                        <details class="mt-2" data-add-by-hand="{{ $item->id }}" @if ($bag->any()) open @endif>
                            <summary class="cursor-pointer text-sm font-medium underline underline-offset-2">Add by hand</summary>
                            <form method="POST" action="{{ route('scan.batches.items.add', [$batch, $item]) }}" class="mt-2 max-w-md space-y-2">
                                @csrf
                                <div>
                                    <label for="name-{{ $item->id }}" class="mb-1 block text-sm font-medium">Title (original)</label>
                                    <input id="name-{{ $item->id }}" name="name" required maxlength="255" value="{{ $bag->any() ? old('name') : '' }}" class="{{ $input }}">
                                    @if ($bag->has('name')) <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $bag->first('name') }}</p> @endif
                                </div>
                                <div>
                                    <label for="name_romanized-{{ $item->id }}" class="mb-1 block text-sm font-medium">Title (romanized)</label>
                                    <input id="name_romanized-{{ $item->id }}" name="name_romanized" required maxlength="255" value="{{ $bag->any() ? old('name_romanized') : '' }}" class="{{ $input }}">
                                    @if ($bag->has('name_romanized')) <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $bag->first('name_romanized') }}</p> @endif
                                </div>
                                <button class="{{ $primary }}">{{ $toWishlist ? 'Add to wishlist' : 'Add to collection' }}</button>
                            </form>
                        </details>
                    @endif
                </div>
                @if ($batch->isDraft())
                    <form method="POST" action="{{ route('scan.batches.items.destroy', [$batch, $item]) }}">
                        @csrf
                        @method('DELETE')
                        <button class="text-sm underline">Remove</button>
                    </form>
                @else
                    @if ($batch->status === ScanBatch::DONE && $item->isRedoable())
                        {{-- Not in the collection yet, or a book without a series: look it up again. --}}
                        <form method="POST" action="{{ route('scan.batches.items.redo', [$batch, $item]) }}" class="ml-auto">
                            @csrf
                            <button class="btn btn-secondary" data-redo-code="{{ $item->id }}"
                                    title="{{ $item->object_id ? 'Look for its series again' : 'Ask the catalogues again' }}">{{ $item->object_id ? 'Find series again' : 'Look up again' }}</button>
                        </form>
                    @endif
                    <span @class([
                        'text-sm',
                        'font-medium text-red-600 dark:text-red-400' => $item->status === 'not_found',
                        'text-stone-500' => $item->status !== 'not_found',
                    ]) data-status="{{ $item->status }}">{{ $labels[$item->status] ?? $item->status }}</span>
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
