@php
    $labels = [
        'pending' => 'Waiting', 'added' => 'Added', 'promoted' => 'Moved from wishlist',
        'already_owned' => 'Already in collection', 'already_wanted' => 'Already on wishlist',
        'not_found' => 'Not found', 'invalid' => 'Invalid barcode', 'failed' => 'Lookup failed',
    ];
    $tone = fn (string $status) => match ($status) {
        'added', 'promoted' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
        'failed' => 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200',
        'not_found', 'invalid' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
        default => 'bg-stone-100 text-stone-700 dark:bg-stone-800 dark:text-stone-300',
    };
    $secondary = 'rounded-md border border-stone-300 px-3 py-1.5 text-sm hover:bg-stone-100 dark:border-stone-700 dark:hover:bg-stone-800';
@endphp
<x-admin-layout title="Scan batch">
    @if ($batch->isRunning())
        {{-- Follow a retry or a running batch without JavaScript. --}}
        <meta http-equiv="refresh" content="5">
    @endif

    <a href="{{ route('admin.scan-batches.index') }}" class="text-sm text-stone-500 underline">Scan batches</a>
    <h1 class="mb-1 mt-1 text-2xl font-semibold">Scan batch #{{ $batch->id }}</h1>

    <dl class="mb-6 grid max-w-2xl grid-cols-[max-content_1fr] gap-x-6 gap-y-1 text-sm">
        <dt class="text-stone-500">User</dt><dd>{{ $batch->user->name }} <span class="text-stone-500">· {{ $batch->user->email }}</span></dd>
        <dt class="text-stone-500">Scanning</dt><dd>{{ collect([$batch->category->name, $batch->subCategory?->name, $batch->language->name])->filter()->join(' · ') }} · {{ $batch->ownership?->value === 'wishlist' ? 'to wishlist' : 'to collection' }}</dd>
        <dt class="text-stone-500">Status</dt><dd data-status="{{ $batch->status }}">{{ $statuses[$batch->status] ?? $batch->status }}</dd>
        <dt class="text-stone-500">Started</dt><dd>{{ $batch->created_at }}</dd>
        @if ($batch->committed_at) <dt class="text-stone-500">Committed</dt><dd>{{ $batch->committed_at }}</dd> @endif
        @if ($batch->finished_at) <dt class="text-stone-500">Finished</dt><dd>{{ $batch->finished_at }}</dd> @endif
    </dl>

    @if ($items->isNotEmpty())
        <ul class="mb-4 flex flex-wrap gap-2 text-sm" aria-label="Outcome per code" data-counts>
            @foreach ($counts as $status => $count)
                <li class="rounded px-2 py-0.5 {{ $tone($status) }}">{{ $labels[$status] ?? $status }}: {{ $count }}</li>
            @endforeach
        </ul>
    @endif

    <div class="mb-6 flex flex-wrap gap-2">
        @if ($batch->status === App\Models\ScanBatch::DONE && ($counts['failed'] ?? 0) > 0)
            <form method="POST" action="{{ route('admin.scan-batches.retry', $batch) }}">
                @csrf
                <button class="{{ $secondary }}">Look up the {{ $counts['failed'] }} failed {{ Str::plural('code', $counts['failed']) }} again</button>
            </form>
        @endif
        <form method="POST" action="{{ route('admin.scan-batches.destroy', $batch) }}" onsubmit="return confirm('Remove this scan batch?')">
            @csrf
            @method('DELETE')
            <button class="{{ $secondary }} text-red-700 disabled:opacity-40 dark:text-red-400" @disabled($batch->isRunning())>Remove batch</button>
        </form>
    </div>

    <div class="overflow-x-auto rounded-lg border border-stone-200 bg-white dark:border-stone-800 dark:bg-stone-900">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-stone-200 text-xs uppercase tracking-wide text-stone-500 dark:border-stone-800">
                <tr>
                    <th class="px-4 py-3 font-medium">Code</th>
                    <th class="px-4 py-3 font-medium">Outcome</th>
                    <th class="px-4 py-3 font-medium">Details</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200 dark:divide-stone-800">
                @forelse ($items as $item)
                    <tr data-item-status="{{ $item->status }}">
                        <td class="whitespace-nowrap px-4 py-2 font-mono">{{ $item->barcode }}</td>
                        <td class="whitespace-nowrap px-4 py-2"><span class="rounded px-2 py-0.5 text-xs {{ $tone($item->status) }}">{{ $labels[$item->status] ?? $item->status }}</span></td>
                        <td class="px-4 py-2 text-stone-600 dark:text-stone-400">{{ $item->object?->name ?? $item->message }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-6 text-center text-stone-500">Nothing was scanned in this batch.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
