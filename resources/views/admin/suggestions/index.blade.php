<x-layout title="Suggestions">
    @php
        $input = 'block w-full rounded-md border border-stone-300 px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-950';
        $secondary = 'rounded-md border border-stone-300 px-3 py-1.5 text-sm hover:bg-stone-100 dark:border-stone-700 dark:hover:bg-stone-800';
        $filters = ['' => 'All'] + collect($statuses)->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    @endphp

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold">Suggestions</h1>
        <nav class="flex flex-wrap gap-1 rounded-md border border-stone-200 bg-white p-1 text-sm dark:border-stone-800 dark:bg-stone-900" aria-label="Filter suggestions">
            @foreach ($filters as $value => $label)
                @php($active = ($status?->value ?? '') === $value)
                <a href="{{ route('admin.suggestions.index', $value === '' ? [] : ['status' => $value]) }}"
                   @class(['rounded px-3 py-1', 'bg-stone-900 text-white dark:bg-stone-100 dark:text-stone-900' => $active, 'hover:bg-stone-100 dark:hover:bg-stone-800' => ! $active])
                   @if ($active) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
    </div>

    @if ($errors->any())
        <p class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">{{ $errors->first() }}</p>
    @endif

    <ul class="divide-y divide-stone-200 rounded-lg border border-stone-200 bg-white dark:divide-stone-800 dark:border-stone-800 dark:bg-stone-900">
        @forelse ($suggestions as $suggestion)
            <li class="space-y-3 px-4 py-4" data-suggestion="{{ $suggestion->id }}">
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="rounded px-2 py-0.5 {{ $suggestion->status->badgeClasses() }}">{{ $suggestion->status->label() }}</span>
                    <span class="font-medium text-stone-900 dark:text-stone-100">{{ $suggestion->user->name }}</span>
                    <span class="text-stone-500">{{ $suggestion->user->email }}</span>
                    <span class="text-stone-500">· {{ $suggestion->created_at?->toFormattedDateString() }}</span>
                </div>
                <p class="whitespace-pre-line text-sm">{{ $suggestion->body }}</p>
                <form method="POST" action="{{ route('admin.suggestions.update', $suggestion) }}" class="grid gap-2 sm:grid-cols-[10rem_1fr_auto] sm:items-start">
                    @csrf
                    @method('PATCH')
                    <label class="sr-only" for="status-{{ $suggestion->id }}">Status</label>
                    <select id="status-{{ $suggestion->id }}" name="status" class="{{ $input }}">
                        @foreach ($statuses as $case)
                            <option value="{{ $case->value }}" @selected($suggestion->status === $case)>{{ $case->label() }}</option>
                        @endforeach
                    </select>
                    <label class="sr-only" for="reply-{{ $suggestion->id }}">Reply to the sender</label>
                    <textarea id="reply-{{ $suggestion->id }}" name="admin_reply" rows="2" maxlength="5000" placeholder="Reply (optional, the sender sees it)" class="{{ $input }}">{{ $suggestion->admin_reply }}</textarea>
                    <button class="{{ $secondary }}">Save</button>
                </form>
            </li>
        @empty
            <li class="px-4 py-6 text-center text-sm text-stone-500">No suggestions match this filter.</li>
        @endforelse
    </ul>

    <div class="mt-4">{{ $suggestions->links() }}</div>
</x-layout>
