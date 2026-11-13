<x-layout title="Suggest something">
    @php
        $primary = 'rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 dark:bg-stone-100 dark:text-stone-900 dark:hover:bg-stone-300';
    @endphp

    <x-suggestion-tabs current="mine" />

    <h1 class="mb-2 text-2xl font-semibold">Suggest something</h1>
    <p class="mb-6 text-sm text-stone-600 dark:text-stone-400">An idea, something that bugs you, a feature you miss: the admins read every message.</p>

    <form method="POST" action="{{ route('suggestions.store') }}" class="mb-10 max-w-2xl space-y-3">
        @csrf
        <label for="body" class="block text-sm font-medium">Your suggestion</label>
        <textarea id="body" name="body" rows="5" required minlength="10" maxlength="5000"
                  class="block w-full rounded-md border border-stone-300 px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-950">{{ old('body') }}</textarea>
        @error('body') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        <button class="{{ $primary }}">Send</button>
    </form>

    <section class="max-w-2xl">
        <h2 class="mb-3 text-lg font-semibold">Your suggestions</h2>
        <ul class="divide-y divide-stone-200 rounded-lg border border-stone-200 bg-white dark:divide-stone-800 dark:border-stone-800 dark:bg-stone-900">
            @forelse ($suggestions as $suggestion)
                <li class="space-y-2 px-4 py-3" data-suggestion="{{ $suggestion->id }}">
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="rounded px-2 py-0.5 {{ $suggestion->status->badgeClasses() }}">{{ $suggestion->status->label() }}</span>
                        <span class="text-stone-500">{{ $suggestion->created_at?->toFormattedDateString() }}</span>
                        @if ($suggestion->is_public)
                            <span class="text-stone-500" data-on-board>· On the board · {{ trans_choice(':count vote|:count votes', $suggestion->votes_count) }}</span>
                        @endif
                    </div>
                    <p class="whitespace-pre-line text-sm">{{ $suggestion->body }}</p>
                    @if ($suggestion->admin_reply)
                        <div class="rounded-md bg-stone-50 px-3 py-2 text-sm dark:bg-stone-950">
                            <p class="mb-1 text-xs font-medium text-stone-500">Reply from the admins</p>
                            <p class="whitespace-pre-line">{{ $suggestion->admin_reply }}</p>
                        </div>
                    @endif
                </li>
            @empty
                <li class="px-4 py-6 text-center text-sm text-stone-500">You have not sent any suggestions yet.</li>
            @endforelse
        </ul>
    </section>
</x-layout>
