@props(['title' => null])
{{-- The admin section: the normal page frame plus its own menu (a side column on wide
     screens, a scrollable row of tabs on phones). --}}
@php
    $newSuggestions = App\Models\Suggestion::query()->where('status', App\Enums\SuggestionStatus::New)->count();
    $items = [
        ['admin.home', 'admin.home', 'Overview', null],
        ['admin.users.index', 'admin.users.*', 'Users', null],
        ['admin.sso.index', 'admin.sso.*', 'Sign-in', null],
        ['admin.languages.index', 'admin.languages.*', 'Languages', null],
        ['admin.categories.index', 'admin.categories.*', 'Categories', null],
        ['admin.suggestions.index', 'admin.suggestions.*', 'Suggestions', $newSuggestions ?: null],
    ];
@endphp
<x-layout :title="$title ? $title.' · Admin' : 'Admin'" wide>
    <div class="grid gap-6 md:grid-cols-[12rem_minmax(0,1fr)] md:gap-10">
        <nav aria-label="Admin" class="-mx-4 overflow-x-auto px-4 md:mx-0 md:overflow-visible md:px-0">
            <p class="mb-2 hidden text-xs font-semibold uppercase tracking-wide text-stone-500 md:block">Admin</p>
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
                                <span class="rounded-full bg-sky-100 px-1.5 text-xs text-sky-800 dark:bg-sky-950 dark:text-sky-200">{{ $badge }}<span class="sr-only"> new</span></span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="min-w-0">
            {{ $slot }}
        </div>
    </div>
</x-layout>
