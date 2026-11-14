@props(['value', 'label' => 'Copy'])
{{-- Copies $value to the clipboard. The label switches to "Copied" for a moment
     (announced to screen readers); behaviour lives in resources/js/app.js. --}}
<button type="button" data-copy="{{ $value }}" data-state="idle"
    {{ $attributes->merge(['class' => 'group inline-flex shrink-0 items-center gap-1.5 rounded-md border border-stone-300 bg-white px-2.5 py-1 text-xs font-medium text-stone-700 transition-colors hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-stone-500 data-[state=copied]:border-emerald-500 data-[state=copied]:bg-emerald-50 data-[state=copied]:text-emerald-800 data-[state=failed]:border-amber-500 data-[state=failed]:bg-amber-50 data-[state=failed]:text-amber-900 dark:border-stone-700 dark:bg-stone-900 dark:text-stone-200 dark:hover:bg-stone-800 dark:data-[state=copied]:border-emerald-600 dark:data-[state=copied]:bg-emerald-950 dark:data-[state=copied]:text-emerald-200 dark:data-[state=failed]:border-amber-600 dark:data-[state=failed]:bg-amber-950 dark:data-[state=failed]:text-amber-200']) }}>
    {{-- Clipboard icon, swapped for a check mark once copied. --}}
    <svg aria-hidden="true" class="size-3.5 group-data-[state=copied]:hidden" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <rect x="6.5" y="6.5" width="10" height="11" rx="1.5"/>
        <path d="M13.5 6.5V4a1.5 1.5 0 0 0-1.5-1.5H5A1.5 1.5 0 0 0 3.5 4v9A1.5 1.5 0 0 0 5 14.5h1.5"/>
    </svg>
    <svg aria-hidden="true" class="hidden size-3.5 group-data-[state=copied]:block" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="m4.5 10.5 3.5 3.5 7.5-8"/>
    </svg>
    <span data-copy-label data-idle-text="{{ $label }}" aria-live="polite">{{ $label }}</span>
</button>
