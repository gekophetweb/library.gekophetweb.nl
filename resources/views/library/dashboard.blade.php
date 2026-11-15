<x-layout title="Library">
    @php
        $icons = [
            'books' => 'M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2V5zM4 19a2 2 0 0 1 2-2h13M9 7h6',
            'music' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 14a2 2 0 1 0 0-4 2 2 0 0 0 0 4z',
            'games' => 'M6 9h4M8 7v4M15 9h.01M18 11h.01M7 5h10a5 5 0 0 1 5 5v1a5 5 0 0 1-9 3h-2a5 5 0 0 1-9-3v-1a5 5 0 0 1 5-5z',
        ];
        $owned = $categories->sum('items_count');
        $wanted = $categories->sum('wanted_count');
    @endphp

    <div class="mb-10 flex flex-wrap items-end justify-between gap-5">
        <div class="space-y-1.5">
            <h1 class="font-display text-4xl leading-none font-extrabold sm:text-[52px]">Your library</h1>
            <p class="text-muted">
                {{ number_format($owned) }} {{ Str::plural('thing', $owned) }} on your shelves{{ $wanted ? ', '.number_format($wanted).' on your wishlist' : '' }}.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-4">
            @if ($hasScanBatches)
                <a href="{{ route('scan.batches.index') }}" class="text-sm text-accent underline-offset-2 hover:underline">Your scan batches</a>
            @endif
            <x-scanner-dialog :categories="$scanCategories" :languages="$languages" />
        </div>
    </div>

    <ul class="grid gap-5 sm:grid-cols-[repeat(auto-fit,minmax(240px,1fr))]">
        @foreach ($categories as $category)
            <li>
                <a href="{{ route('library.category', $category) }}"
                   class="card group flex h-full flex-col gap-7 p-6 text-ink hover:border-line-strong">
                    <span class="flex items-center justify-between">
                        <span class="flex size-[52px] items-center justify-center rounded-[14px] bg-accent-soft text-accent">
                            <svg aria-hidden="true" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icons[$category->kind?->value] ?? $icons['books'] }}"/></svg>
                        </span>
                        <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="text-muted transition group-hover:text-accent"><path d="M7 17 17 7M9 7h8v8"/></svg>
                    </span>
                    <span class="flex flex-col gap-1.5">
                        <span class="font-display text-[26px] font-bold">{{ $category->name }}</span>
                        <span class="flex flex-wrap items-baseline gap-2.5">
                            {{-- Owned is the collection size; wanted items are a secondary figure. --}}
                            <span class="font-display text-[40px] leading-none font-extrabold" data-owned="{{ $category->items_count }}">{{ $category->items_count }}</span>
                            <span class="text-sm text-muted">owned</span>
                            @if ($category->wanted_count)
                                <span class="pill pill-wanted ml-auto" data-wanted="{{ $category->wanted_count }}">{{ $category->wanted_count }} wanted</span>
                            @endif
                        </span>
                    </span>
                </a>
            </li>
        @endforeach
    </ul>

    @if ($sharedWithMe->isNotEmpty())
        <section class="mt-12" data-shared-with-me>
            <h2 class="mb-3 font-display text-[22px] font-bold">Shared with you</h2>
            <ul class="card divide-y divide-line overflow-hidden">
                @foreach ($sharedWithMe as $share)
                    <li class="flex flex-wrap items-baseline gap-2 px-5 py-3.5">
                        <a href="{{ $share->url() }}" class="font-semibold text-ink underline-offset-2 hover:underline">{{ $share->user->name }}’s {{ $share->isWholeLibrary() ? 'collection' : $share->scopeLabel() }}</a>
                        @if ($share->label) <span class="text-sm text-muted">· {{ $share->label }}</span> @endif
                        @if ($share->expires_at) <span class="text-sm text-muted">· until {{ $share->expires_at->toFormattedDateString() }}</span> @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layout>
