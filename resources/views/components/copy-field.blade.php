@props(['value', 'label' => null])
{{-- A value to paste somewhere else (e.g. an SSO callback URL), with a copy button.
     The value is selectable as a fallback for browsers that refuse the clipboard. --}}
<div {{ $attributes->merge(['class' => 'space-y-1']) }}>
    @if ($label)
        <p class="text-sm text-stone-500">{{ $label }}</p>
    @endif
    <div class="flex items-stretch gap-2">
        <code class="min-w-0 flex-1 select-all break-all rounded-md border border-stone-200 bg-stone-50 px-2.5 py-1.5 font-mono text-xs text-stone-800 dark:border-stone-800 dark:bg-stone-950 dark:text-stone-200">{{ $value }}</code>
        <x-copy-button :value="$value" />
    </div>
</div>
