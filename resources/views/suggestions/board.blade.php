<x-layout title="Suggestion board">
    <x-suggestion-tabs current="board" />

    <h1 class="mb-2 text-2xl font-semibold">Suggestion board</h1>
    <p class="mb-6 text-sm text-stone-600 dark:text-stone-400">Suggestions the admins put up for everyone. Vote for the ones you want most; who sent them stays private.</p>

    <ul class="max-w-2xl divide-y divide-stone-200 rounded-lg border border-stone-200 bg-white dark:divide-stone-800 dark:border-stone-800 dark:bg-stone-900">
        @forelse ($suggestions as $suggestion)
            <li class="flex gap-4 px-4 py-3" data-suggestion="{{ $suggestion->id }}">
                <div class="w-16 shrink-0 text-center">
                    <span class="block text-lg font-semibold tabular-nums" data-votes="{{ $suggestion->votes_count }}">{{ $suggestion->votes_count }}</span>
                    <span class="block text-xs text-stone-500">{{ $suggestion->votes_count === 1 ? 'vote' : 'votes' }}</span>
                    @if ($suggestion->canBeVotedOnBy(auth()->user()))
                        <form method="POST" action="{{ route('suggestions.vote', $suggestion) }}" class="mt-1">
                            @csrf
                            @if ($suggestion->voted)
                                @method('DELETE')
                                <button class="text-xs underline" data-unvote>Unvote</button>
                            @else
                                <button class="text-xs font-medium underline" data-vote>Vote</button>
                            @endif
                        </form>
                    @else
                        <span class="mt-1 block text-xs text-stone-500">Yours</span>
                    @endif
                </div>
                <div class="min-w-0 space-y-2">
                    <span class="rounded px-2 py-0.5 text-xs {{ $suggestion->status->badgeClasses() }}">{{ $suggestion->status->label() }}</span>
                    <p class="whitespace-pre-line text-sm">{{ $suggestion->body }}</p>
                    <a href="{{ route('suggestions.show', $suggestion) }}" class="inline-block text-xs text-stone-600 underline dark:text-stone-400" data-thread-link>{{ trans_choice('{0} Comment|{1} 1 comment|[2,*] :count comments', $suggestion->comments_count) }}</a>
                    @if ($suggestion->admin_reply)
                        <div class="rounded-md bg-stone-50 px-3 py-2 text-sm dark:bg-stone-950">
                            <p class="mb-1 text-xs font-medium text-stone-500">Reply from the admins</p>
                            <p class="whitespace-pre-line">{{ $suggestion->admin_reply }}</p>
                        </div>
                    @endif
                </div>
            </li>
        @empty
            <li class="px-4 py-6 text-center text-sm text-stone-500">Nothing on the board yet.</li>
        @endforelse
    </ul>

    <div class="mt-4 max-w-2xl">{{ $suggestions->links() }}</div>
</x-layout>
