{{-- $saved: complete key; $stored: a row exists, possibly without the second credential. --}}
@if ($saved)
    <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">Key saved</span>
@elseif ($stored && $provider->needsSecret())
    <span class="rounded bg-amber-100 px-2 py-0.5 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-200" data-secret-missing>{{ $provider->secretLabel() }} missing</span>
@else
    <span class="rounded bg-amber-100 px-2 py-0.5 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-200">Needed to scan</span>
@endif
