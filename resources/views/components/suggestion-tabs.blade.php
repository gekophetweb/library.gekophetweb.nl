@props(['current'])
<nav class="mb-4 flex gap-1 text-sm" aria-label="Suggestions">
    @foreach (['mine' => ['suggestions.index', 'Your suggestions'], 'board' => ['suggestions.board', 'Board']] as $key => [$route, $label])
        <a href="{{ route($route) }}"
           @class(['rounded px-3 py-1', 'bg-stone-900 text-white dark:bg-stone-100 dark:text-stone-900' => $current === $key, 'hover:bg-stone-100 dark:hover:bg-stone-800' => $current !== $key])
           @if ($current === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
