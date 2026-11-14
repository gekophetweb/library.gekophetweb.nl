@php
    $secondary = 'rounded-md border border-stone-300 px-3 py-1 text-xs hover:bg-stone-100 dark:border-stone-700 dark:hover:bg-stone-800';
@endphp
<x-layout title="Your scan batches">
    <a href="{{ route('home') }}" class="text-sm text-stone-500 underline">Library</a>
    <h1 class="mb-1 mt-1 text-2xl font-semibold">Your scan batches</h1>
    <p class="mb-6 max-w-2xl text-sm text-stone-600 dark:text-stone-400">Every batch scan you started. Open one to continue scanning, or to see what happened to each code.</p>

    <div class="overflow-x-auto rounded-lg border border-stone-200 bg-white dark:border-stone-800 dark:bg-stone-900">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-stone-200 text-xs uppercase tracking-wide text-stone-500 dark:border-stone-800">
                <tr>
                    <th class="px-4 py-3 font-medium">Started</th>
                    <th class="px-4 py-3 font-medium">Scanning</th>
                    <th class="px-4 py-3 text-right font-medium">Added / codes</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium"><span class="sr-only">Open</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200 dark:divide-stone-800">
                @forelse ($batches as $batch)
                    <tr data-batch="{{ $batch->id }}">
                        <td class="whitespace-nowrap px-4 py-3 text-stone-600 dark:text-stone-400" title="{{ $batch->created_at }}">{{ $batch->created_at?->diffForHumans() }}</td>
                        <td class="px-4 py-3">
                            {{ collect([$batch->category->name, $batch->subCategory?->name, $batch->language->name])->filter()->join(' · ') }}
                            <span class="block text-xs text-stone-500">{{ $batch->ownership?->value === 'wishlist' ? 'To wishlist' : 'To collection' }}</span>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            @if ($batch->isDraft())
                                {{ number_format($batch->items_count) }}
                            @else
                                {{ number_format($batch->added_count) }} / {{ number_format($batch->items_count) }}
                            @endif
                            @if ($batch->failed_count)
                                <span class="block text-xs text-red-700 dark:text-red-400" data-failed="{{ $batch->failed_count }}">{{ $batch->failed_count }} failed</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3" data-status="{{ $batch->status }}">
                            <span @class([
                                'rounded px-2 py-0.5 text-xs',
                                'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200' => $batch->isDraft(),
                                'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200' => $batch->isRunning(),
                                'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200' => $batch->status === App\Models\ScanBatch::DONE,
                            ])>{{ $statuses[$batch->status] ?? $batch->status }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('scan.batches.show', $batch) }}" class="{{ $secondary }}">{{ $batch->isDraft() ? 'Continue' : 'Open' }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-stone-500">No batch scans yet. Choose <strong>Scan items</strong> → <em>Many items (batch)</em> on your library page to start one.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $batches->links() }}</div>
</x-layout>
