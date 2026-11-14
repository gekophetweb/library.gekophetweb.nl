@props(['title' => null])
{{-- The settings section: one short page per topic, with its own menu (a side column on
     wide screens, a scrollable row of tabs on phones), like the admin section. --}}
@php
    $missingKeys = count(auth()->user()->missingApiKeys());
    $items = [
        ['settings.edit', 'settings.edit', 'Profile', null],
        ['settings.api-keys', 'settings.api-keys*', 'API keys', $missingKeys ?: null],
        ['settings.tokens', 'settings.tokens', 'Phone app', null],
        ['settings.shares', 'settings.shares', 'Shared links', null],
        ['settings.sign-in', 'settings.sign-in', 'Sign-in methods', null],
        ['settings.account', 'settings.account', 'Delete account', null],
    ];
@endphp
<x-layout :title="$title ? $title.' · Settings' : 'Settings'">
    <div class="grid gap-6 md:grid-cols-[12rem_minmax(0,1fr)] md:gap-10">
        <nav aria-label="Settings" class="-mx-4 overflow-x-auto px-4 md:mx-0 md:overflow-visible md:px-0">
            <p class="mb-2 hidden text-xs font-semibold uppercase tracking-wide text-stone-500 md:block">Settings</p>
            <ul class="flex gap-1 md:flex-col">
                @foreach ($items as [$route, $pattern, $label, $badge])
                    @php($active = request()->routeIs($pattern))
                    <li>
                        <a href="{{ route($route) }}" @if ($active) aria-current="page" @endif
                           @class([
                               'flex items-center justify-between gap-2 whitespace-nowrap rounded-md px-3 py-1.5 text-sm',
                               'bg-stone-900 font-medium text-white dark:bg-stone-100 dark:text-stone-900' => $active,
                               'text-stone-700 hover:bg-stone-100 dark:text-stone-300 dark:hover:bg-stone-800' => ! $active,
                           ])>
                            {{ $label }}
                            @if ($badge)
                                <span class="rounded-full bg-amber-100 px-1.5 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-200" data-missing-key-count>{{ $badge }}<span class="sr-only"> missing</span></span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="min-w-0 max-w-2xl">
            {{ $slot }}
        </div>
    </div>
</x-layout>
