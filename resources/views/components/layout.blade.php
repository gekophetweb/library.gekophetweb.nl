@props(['title' => null, 'share' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if ($share)
        <meta name="robots" content="noindex, nofollow">
        <meta name="referrer" content="no-referrer">
    @endif
    <title>{{ $title ? $title.' · ' : '' }}{{ $share ? $share->user->name.'’s collection' : config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-stone-900 antialiased dark:bg-stone-950 dark:text-stone-100">
    @if ($share)
        {{-- A share link: no account nav, nothing to act on, even for a signed-in visitor. --}}
        <header class="border-b border-stone-200 bg-white dark:border-stone-800 dark:bg-stone-900">
            <p class="mx-auto max-w-5xl px-4 py-3 text-sm">
                <span class="font-semibold">{{ $share->user->name }}’s collection</span>
                <span class="text-stone-500">· read only</span>
            </p>
        </header>
    @else
    @auth
        <header class="border-b border-stone-200 bg-white dark:border-stone-800 dark:bg-stone-900">
            <nav class="mx-auto flex max-w-5xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3 text-sm">
                <a href="{{ route('home') }}" class="font-semibold">{{ config('app.name') }}</a>
                @can('admin')
                    <a href="{{ route('admin.users.index') }}" class="text-stone-600 hover:text-stone-900 dark:text-stone-400 dark:hover:text-stone-100">Users</a>
                    <a href="{{ route('admin.sso.index') }}" class="text-stone-600 hover:text-stone-900 dark:text-stone-400 dark:hover:text-stone-100">Sign-in</a>
                @endcan
                <form method="GET" action="{{ route('search.index') }}" role="search" class="flex min-w-0 items-center gap-1">
                    <label for="nav-search" class="sr-only">Search your library</label>
                    <input id="nav-search" type="search" name="q" value="{{ request()->routeIs('search.index') ? request('q') : '' }}" placeholder="Search" maxlength="255"
                           class="w-28 min-w-0 rounded-md border border-stone-300 px-2 py-1 text-sm sm:w-48 dark:border-stone-700 dark:bg-stone-950">
                    <label for="nav-search-scope" class="sr-only">Search in</label>
                    <select id="nav-search-scope" name="scope" class="rounded-md border border-stone-300 px-1 py-1 text-sm dark:border-stone-700 dark:bg-stone-950">
                        @foreach (App\Enums\SearchScope::cases() as $searchScope)
                            <option value="{{ $searchScope->value }}" @selected(request('scope', 'all') === $searchScope->value)>{{ $searchScope->label() }}</option>
                        @endforeach
                    </select>
                </form>
                <a href="{{ route('settings.edit') }}" class="ml-auto text-stone-600 hover:text-stone-900 dark:text-stone-400 dark:hover:text-stone-100">{{ auth()->user()->name }}</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="text-stone-600 hover:text-stone-900 dark:text-stone-400 dark:hover:text-stone-100">Log out</button>
                </form>
            </nav>
        </header>
    @endauth
    @endif

    <main class="mx-auto max-w-5xl px-4 py-8">
        @if (! $share && session('status'))
            <p role="status" class="mb-6 rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</p>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
