@props(['mode' => 'single', 'target'])
{{--
    Reads barcodes with the device camera (resources/js/camera-scanner.js).
    mode="single": the first code read is put in the input #$target and its form is submitted.
    mode="batch":  codes collect in the dialog; "Add to list" appends them to the textarea
                   #$target and submits its form (the paste-a-list endpoint).
--}}
<div data-camera-scanner data-mode="{{ $mode }}" data-target="{{ $target }}" {{ $attributes->merge(['class' => 'mb-4']) }}>
    <button type="button" data-camera-open
            class="inline-flex items-center gap-2 rounded-md border border-stone-300 bg-white px-3 py-2 text-sm font-medium hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-stone-500 dark:border-stone-700 dark:bg-stone-900 dark:hover:bg-stone-800">
        <svg aria-hidden="true" class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 7.5A1.5 1.5 0 0 1 4.5 6h2l1.2-1.8A1 1 0 0 1 8.5 3.8h3a1 1 0 0 1 .8.4L13.5 6h2A1.5 1.5 0 0 1 17 7.5v7A1.5 1.5 0 0 1 15.5 16h-11A1.5 1.5 0 0 1 3 14.5z"/>
            <circle cx="10" cy="10.5" r="3"/>
        </svg>
        Scan with camera
    </button>
    {{-- Filled in by JavaScript when the camera can't be used here (e.g. plain http). --}}
    <p data-camera-unavailable hidden class="mt-2 max-w-md text-sm text-amber-800 dark:text-amber-300"></p>

    <dialog data-camera-dialog aria-labelledby="camera-title-{{ $target }}"
            class="w-full max-w-md rounded-xl border border-stone-200 bg-white p-0 text-stone-900 backdrop:bg-stone-950/70 dark:border-stone-800 dark:bg-stone-900 dark:text-stone-100">
        <div class="flex items-center justify-between px-4 pt-4">
            <h2 id="camera-title-{{ $target }}" class="text-lg font-semibold">{{ $mode === 'batch' ? 'Scan items' : 'Scan a barcode' }}</h2>
            <button type="button" data-camera-close class="rounded px-2 py-1 text-sm underline">Close</button>
        </div>

        <div class="relative m-4 overflow-hidden rounded-lg bg-stone-950">
            <video data-camera-video playsinline muted class="aspect-[4/3] w-full object-cover"></video>
            {{-- Aiming guide: barcodes read best held flat in the middle. --}}
            <div aria-hidden="true" class="pointer-events-none absolute inset-x-[12%] top-1/2 h-1/3 -translate-y-1/2 rounded-md border-2 border-white/80 shadow-[0_0_0_9999px_rgb(0_0_0/0.25)]"></div>
        </div>

        <p data-camera-status aria-live="polite" class="px-4 text-sm text-stone-600 dark:text-stone-400">Starting the camera…</p>

        @if ($mode === 'batch')
            <div class="px-4 pt-3">
                <p class="text-sm font-medium"><span data-camera-count>0</span> scanned</p>
                <ul data-camera-found class="mt-1 max-h-32 space-y-0.5 overflow-y-auto font-mono text-sm"></ul>
            </div>
            <div class="flex justify-end gap-3 p-4">
                <button type="button" data-camera-close class="px-4 py-2 text-sm underline">Cancel</button>
                <button type="button" data-camera-submit disabled
                        class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 disabled:opacity-40 dark:bg-stone-100 dark:text-stone-900 dark:hover:bg-stone-300">Add to list</button>
            </div>
        @else
            <div class="p-4"></div>
        @endif
    </dialog>
</div>
