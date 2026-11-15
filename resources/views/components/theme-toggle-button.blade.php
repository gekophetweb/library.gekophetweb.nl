{{-- The sun/moon button: shows the sun in dark (switch to light) and the moon in light. --}}
<button {{ $attributes->merge(['class' => 'flex size-[42px] cursor-pointer items-center justify-center rounded-full border border-line-strong bg-surface-2 text-ink hover:bg-surface']) }}
        aria-label="Switch between light and dark theme" title="Switch between light and dark theme">
    <svg aria-hidden="true" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" class="hidden dark:block"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
    <svg aria-hidden="true" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="block dark:hidden"><path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/></svg>
</button>
