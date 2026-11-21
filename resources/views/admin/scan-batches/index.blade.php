@php
    $secondary = 'rounded-md border border-stone-300 px-3 py-1 text-xs hover:bg-stone-100 disabled:cursor-not-allowed disabled:opacity-40 dark:border-stone-700 dark:hover:bg-stone-800';
    $active = fn (?string $value) => ($filters['status'] ?? null) === $value;
    $keep = array_filter(['language' => $filters['language'] ?? null, 'category' => $filters['category'] ?? null, 'mode' => $filters['mode'] ?? null]);
    $kinds = ['' => 'Batches and single scans', 'batch' => 'Batches', 'single' => 'Single scans'];
@endphp
<x-admin-layout title="Scan batches">
    <div class="mb-2 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold">Scan batches</h1>
        <nav class="flex flex-wrap gap-1 rounded-md border border-stone-200 bg-white p-1 text-sm dark:border-stone-800 dark:bg-stone-900" aria-label="Filter scan batches">
            @foreach ([null => 'All'] + $statuses as $value => $label)
                <a href="{{ route('admin.scan-batches.index', $keep + ($value ? ['status' => $value] : [])) }}"
                   @class(['rounded px-3 py-1', 'bg-stone-900 text-white dark:bg-stone-100 dark:text-stone-900' => $active($value ?: null), 'hover:bg-stone-100 dark:hover:bg-stone-800' => ! $active($value ?: null)])
                   @if ($active($value ?: null)) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
    </div>
    <p class="mb-6 max-w-2xl text-sm text-stone-600 dark:text-stone-400">
        Every user's batch scans. Open one to see what happened to each code. Remove abandoned ones:
        a batch keeps its language and sub-category in use. Removing a batch never removes items it already added.
    </p>

    {{-- Single scans are recorded per user and scan setup, as a "single" batch. --}}
    <nav class="mb-4 flex flex-wrap gap-3 text-sm" aria-label="Batches or single scans" data-mode-filter>
        @foreach ($kinds as $value => $label)
            @php($current = ($filters['mode'] ?? '') === $value)
            <a href="{{ route('admin.scan-batches.index', array_filter(['status' => $filters['status'] ?? null, 'mode' => $value ?: null] + $keep)) }}"
               @class(['underline-offset-2', 'font-semibold' => $current, 'underline' => ! $current])
               @if ($current) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    @if ($language || $category)
        <p class="mb-4 flex flex-wrap items-center gap-2 text-sm" data-filter>
            Showing batches for
            <span class="rounded bg-stone-100 px-2 py-0.5 font-medium dark:bg-stone-800">{{ $language?->name ?? $category?->name }}</span>
            <a href="{{ route('admin.scan-batches.index', array_filter(['status' => $filters['status'] ?? null, 'mode' => $filters['mode'] ?? null])) }}" class="underline">Show all</a>
        </p>
    @endif

    @error('batch') <p class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">{{ $message }}</p> @enderror

    <div class="overflow-x-auto rounded-lg border border-stone-200 bg-white dark:border-stone-800 dark:bg-stone-900">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-stone-200 text-xs uppercase tracking-wide text-stone-500 dark:border-stone-800">
                <tr>
                    <th class="px-4 py-3 font-medium">Started</th>
                    <th class="px-4 py-3 font-medium">User</th>
                    <th class="px-4 py-3 font-medium">Scanning</th>
                    <th class="px-4 py-3 text-right font-medium">Added / codes</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200 dark:divide-stone-800">
                @forelse ($batches as $batch)
                    <tr data-batch="{{ $batch->id }}">
                        <td class="whitespace-nowrap px-4 py-3 text-stone-600 dark:text-stone-400" title="{{ $batch->created_at }}">{{ $batch->created_at?->diffForHumans() }}</td>
                        <td class="px-4 py-3">
                            <span class="font-medium">{{ $batch->user->name }}</span>
                            <span class="block text-xs text-stone-500">{{ $batch->user->email }}</span>
                        </td>
                        <td class="px-4 py-3">
                            {{ collect([$batch->category->name, $batch->subCategory?->name, $batch->language->name])->filter()->join(' · ') }}
                            <span class="block text-xs text-stone-500">{{ $batch->ownership?->value === 'wishlist' ? 'To wishlist' : 'To collection' }}</span>
                            @if ($batch->isSingle())
                                <span class="mt-1 inline-block rounded bg-sky-100 px-2 py-0.5 text-xs text-sky-800 dark:bg-sky-950 dark:text-sky-200" data-single>Single scans</span>
                            @endif
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
                        <td class="flex justify-end gap-2 px-4 py-3">
                            <a href="{{ route('admin.scan-batches.show', $batch) }}" class="{{ $secondary }}">Details</a>
                            <form method="POST" action="{{ route('admin.scan-batches.destroy', $batch) }}"
                                  onsubmit="return confirm('Remove this scan batch of {{ e($batch->user->name) }}?')">
                                @csrf
                                @method('DELETE')
                                <button class="{{ $secondary }} text-red-700 dark:text-red-400" @disabled($batch->isRunning())
                                        @if ($batch->isRunning()) title="Still adding items; remove it once it is done" @endif>Remove</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-stone-500">No scan batches match this filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $batches->links() }}</div>
</x-admin-layout>
