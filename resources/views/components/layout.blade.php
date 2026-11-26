@props(['title' => null, 'share' => null, 'wide' => false, 'backdrop' => null])
@php
    // Share links always get the standard look: never the visitor's settings or photo,
    // and not the owner's either (plan redesign-themes §3).
    $viewer = $share ? null : auth()->user();
    $appearance = $viewer?->appearance ?? App\Support\Appearance\Appearance::defaults();
    $guestTheme = ! $share && ! $viewer;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @foreach ($appearance->htmlAttributes($viewer) as $attribute => $value) {{ $attribute }}="{{ $value }}" @endforeach @if ($guestTheme) data-guest @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if ($share)
        <meta name="robots" content="noindex, nofollow">
        <meta name="referrer" content="no-referrer">
    @endif
    <title>{{ $title ? $title.' · ' : '' }}{{ $share ? $share->user->name.'’s collection' : config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    {{-- Resolve "same as device" (and a guest's choice) before the first paint: no flash. --}}
    <script>
        (function () {
            var d = document.documentElement, m = d.dataset.themeMode;
            if (d.hasAttribute('data-guest')) { try { m = localStorage.getItem('library-theme') || m; } catch (e) {} }
            if (m !== 'light' && m !== 'dark') { m = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; }
            d.dataset.theme = m;
        })();
    </script>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body @class(['paper min-h-screen antialiased', 'has-backdrop' => $backdrop]) @if ($backdrop) style="--backdrop: url('{{ $backdrop }}')" @endif>
    @php
        $navLink = 'rounded-md py-2 text-muted hover:text-ink';
    @endphp

    @if ($share)
        {{-- A share link: no account nav, nothing to act on, even for a signed-in visitor. --}}
        <header class="border-b border-line bg-surface">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center gap-x-4 gap-y-1 py-1.5 pr-4 pl-8 sm:pl-16">
                <img src="{{ asset('images/gekophetweb-logo.png') }}" alt="Gek op het Web" width="88" height="60" class="logo h-auto w-[72px] sm:w-[88px]">
                <p class="text-sm">
                    <span class="font-display text-base font-bold">{{ $share->user->name }}’s collection</span>
                    <span class="text-muted">· read only</span>
                </p>
            </div>
        </header>
    @else
    @auth
        <header class="border-b border-line bg-surface">
            <nav class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-5 gap-y-2 py-1.5 pr-4 pl-8 text-sm sm:pl-16">
                <a href="{{ route('home') }}" aria-label="{{ config('app.name') }} home" class="flex items-center gap-2.5 text-ink">
                    <img src="{{ asset('images/gekophetweb-logo.png') }}" alt="Gek op het Web" width="88" height="60" class="logo h-auto w-[72px] sm:w-[88px]">
                    <span class="label-mono text-ink">Library</span>
                </a>

                    <form method="GET" action="{{ route('search.index') }}" role="search"
                          class="order-last flex min-h-[42px] w-full min-w-0 items-center gap-2 rounded-full border border-line-strong bg-surface-2 py-1 pr-1.5 pl-3.5 sm:order-none sm:w-auto sm:flex-[1_1_16rem] sm:max-w-md">
                        <svg aria-hidden="true" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" class="shrink-0 text-muted"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                        <label for="nav-search" class="sr-only">Search your library</label>
                        <input id="nav-search" type="search" name="q" value="{{ request()->routeIs('search.index') ? request('q') : '' }}" placeholder="Search" maxlength="255"
                               class="min-w-0 flex-1 border-0 bg-transparent text-sm text-ink placeholder:text-muted focus:outline-none">
                        <label for="nav-search-scope" class="sr-only">Search in</label>
                        <select id="nav-search-scope" name="scope" class="min-h-8 rounded-full border-0 bg-surface px-2.5 text-[13px] font-medium text-ink">
                            @foreach (App\Enums\SearchScope::cases() as $searchScope)
                                <option value="{{ $searchScope->value }}" @selected(request('scope', 'all') === $searchScope->value)>{{ $searchScope->label() }}</option>
                            @endforeach
                        </select>
                    </form>

                <div class="ml-auto flex flex-wrap items-center gap-x-5 gap-y-1">
                        @can('admin')
                            {{-- All management lives in the admin section; one entry point here. --}}
                            @php($newSuggestions = App\Models\Suggestion::query()->where('status', App\Enums\SuggestionStatus::New)->count())
                            <a href="{{ route('admin.home') }}" data-admin-link
                               @class([$navLink, 'font-semibold text-ink' => request()->routeIs('admin.*')])>Admin
                                @if ($newSuggestions)
                                    <span data-new-suggestions="{{ $newSuggestions }}" title="{{ $newSuggestions }} new {{ Str::plural('suggestion', $newSuggestions) }}" class="pill pill-info ml-0.5">{{ $newSuggestions }}<span class="sr-only"> new {{ Str::plural('suggestion', $newSuggestions) }}</span></span>
                                @endif
                            </a>
                        @endcan
                        <a href="{{ route('suggestions.index') }}" @class([$navLink, 'font-semibold text-ink' => request()->routeIs('suggestions.*')])>Suggest something</a>
                        <a href="{{ route('settings.edit') }}" @class([$navLink, 'font-semibold text-ink' => request()->routeIs('settings.*')])>{{ auth()->user()->name }}</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="{{ $navLink }} cursor-pointer">Log out</button>
                        </form>
                        <form method="POST" action="{{ route('settings.appearance.theme') }}" data-theme-toggle>
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="mode" value="{{ $appearance->mode === App\Support\Appearance\ThemeMode::Dark ? 'light' : 'dark' }}">
                            <x-theme-toggle-button />
                        </form>
                </div>
            </nav>
        </header>
    @endauth
    @endif

    <main @class(['mx-auto py-8 pr-4 pl-8 sm:py-10 sm:pr-6 sm:pl-16', 'max-w-7xl' => $wide, 'max-w-5xl' => ! $wide])>
        @if (! $share && session('status'))
            <p role="status" class="mb-6 rounded-xl bg-owned-soft px-4 py-3 text-sm font-medium text-owned">{{ session('status') }}</p>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
