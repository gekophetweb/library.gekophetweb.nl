@props(['label', 'items', 'badge' => 'info'])
{{-- Side menu of the settings and admin sections: a column on wide screens, a scrollable
     row of tabs on phones. $items: [route, active pattern, label, badge count or null]. --}}
<nav aria-label="{{ $label }}" class="-mr-4 -ml-8 overflow-x-auto pr-4 pl-8 md:mx-0 md:overflow-visible md:px-0">
    <p class="label-mono mb-2 ml-3 hidden md:block">{{ $label }}</p>
    <ul class="flex gap-1 md:flex-col">
        @foreach ($items as [$route, $pattern, $text, $count])
            @php($active = request()->routeIs($pattern))
            <li>
                <a href="{{ route($route) }}" @if ($active) aria-current="page" @endif
                   @class([
                       'flex min-h-[42px] items-center justify-between gap-2 rounded-[10px] px-3 text-sm whitespace-nowrap',
                       'bg-ink font-semibold text-paper' => $active,
                       'text-ink hover:bg-surface-2' => ! $active,
                   ])>
                    {{ $text }}
                    @if ($count)
                        <span class="pill pill-{{ $badge }}"{!! $badge === 'wanted' ? ' data-missing-key-count' : '' !!}>{{ $count }}<span class="sr-only"> {{ $badge === 'wanted' ? 'missing' : 'new' }}</span></span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
</nav>
