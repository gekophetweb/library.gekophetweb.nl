@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-stone-900 antialiased dark:bg-stone-950 dark:text-stone-100">
    @auth
        <header class="border-b border-stone-200 bg-white dark:border-stone-800 dark:bg-stone-900">
            <nav class="mx-auto flex max-w-5xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3 text-sm">
                <a href="{{ route('home') }}" class="font-semibold">{{ config('app.name') }}</a>
                @can('admin')
                    <a href="{{ route('admin.sso.index') }}" class="text-stone-600 hover:text-stone-900 dark:text-stone-400 dark:hover:text-stone-100">Sign-in</a>
                @endcan
                <a href="{{ route('settings.edit') }}" class="ml-auto text-stone-600 hover:text-stone-900 dark:text-stone-400 dark:hover:text-stone-100">{{ auth()->user()->name }}</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="text-stone-600 hover:text-stone-900 dark:text-stone-400 dark:hover:text-stone-100">Log out</button>
                </form>
            </nav>
        </header>
    @endauth

    <main class="mx-auto max-w-5xl px-4 py-8">
        @if (session('status'))
            <p role="status" class="mb-6 rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</p>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
