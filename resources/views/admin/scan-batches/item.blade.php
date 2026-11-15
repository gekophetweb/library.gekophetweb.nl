@php
    $tone = fn (string $outcome) => match ($outcome) {
        'success' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
        'not_found' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
        default => 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200',
    };
@endphp
<x-admin-layout title="Scanned code {{ $item->barcode }}">
    <a href="{{ route('admin.scan-batches.show', $batch) }}" class="text-sm text-stone-500 underline">Scan batch #{{ $batch->id }}</a>
    <h1 class="mb-1 mt-1 text-2xl font-semibold">Code <span class="font-mono">{{ $item->barcode }}</span></h1>
    <p class="mb-6 text-sm text-stone-600 dark:text-stone-400">
        {{ $batch->user->name }} · outcome <span data-item-status="{{ $item->status }}">{{ $item->status }}</span>@if ($item->object?->name ?? $item->message) · {{ $item->object?->name ?? $item->message }}@endif
    </p>

    @forelse ($calls as $call)
        <section class="mb-4 rounded-lg border border-stone-200 bg-white p-4 dark:border-stone-800 dark:bg-stone-900" data-api-call="{{ $call->id }}">
            <div class="mb-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                <span class="font-semibold">{{ $call->provider }}</span>
                <span class="text-stone-500">{{ $call->operation }}</span>
                <span class="rounded px-2 py-0.5 text-xs {{ $tone($call->outcome) }}">{{ $call->outcome }}{{ $call->status_code ? ' · '.$call->status_code : '' }}</span>
                <span class="text-stone-500">{{ $call->duration_ms }} ms</span>
                <span class="text-stone-500">{{ $call->created_at }}</span>
            </div>
            <p class="mb-2 break-all font-mono text-xs text-stone-600 dark:text-stone-400">{{ $call->query }}</p>
            @if ($call->error)
                <p class="mb-2 text-sm text-red-700 dark:text-red-400">{{ Str::limit($call->error, 300) }}</p>
            @endif
            @if (filled($call->response_body))
                <details>
                    <summary class="cursor-pointer text-sm underline">Raw response ({{ number_format(strlen($call->response_body)) }} bytes)</summary>
                    <pre class="mt-2 max-h-[32rem] overflow-auto rounded bg-stone-100 p-3 text-xs dark:bg-stone-950">{{ $call->prettyBody() }}</pre>
                </details>
            @else
                <p class="text-sm text-stone-500">No response body{{ $call->outcome === 'error' ? ' (the request did not get an answer)' : '' }}.</p>
            @endif
        </section>
    @empty
        <p class="text-stone-500">No API calls are linked to this code. Codes handled before raw API data was kept have none; redo the code to record it.</p>
    @endforelse
</x-admin-layout>
