@php
    $viewer = auth()->user();
    $primary = 'rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 dark:bg-stone-100 dark:text-stone-900 dark:hover:bg-stone-300';
@endphp
<x-layout title="Suggestion">
    <x-suggestion-tabs :current="$suggestion->user_id === $viewer->id ? 'mine' : 'board'" />

    <article class="mb-8 max-w-2xl space-y-3 rounded-lg border border-stone-200 bg-white p-4 dark:border-stone-800 dark:bg-stone-900" data-suggestion="{{ $suggestion->id }}">
        <div class="flex flex-wrap items-center gap-2 text-xs">
            <span class="rounded px-2 py-0.5 {{ $suggestion->status->badgeClasses() }}">{{ $suggestion->status->label() }}</span>
            @if ($viewer->is_admin)
                <span class="font-medium">{{ $suggestion->user->name }}</span>
                <span class="text-stone-500">{{ $suggestion->user->email }}</span>
            @elseif ($suggestion->user_id === $viewer->id)
                <span class="font-medium">Your suggestion</span>
            @endif
            <span class="text-stone-500">· {{ $suggestion->created_at?->toFormattedDateString() }}</span>
            @if ($suggestion->is_public)
                <span class="text-stone-500">· On the board · {{ trans_choice(':count vote|:count votes', $suggestion->votes_count) }}</span>
            @endif
        </div>
        <p class="whitespace-pre-line">{{ $suggestion->body }}</p>
        @if ($suggestion->admin_reply)
            <div class="rounded-md bg-stone-50 px-3 py-2 text-sm dark:bg-stone-950">
                <p class="mb-1 text-xs font-medium text-stone-500">Reply from the admins</p>
                <p class="whitespace-pre-line">{{ $suggestion->admin_reply }}</p>
            </div>
        @endif
        @if ($suggestion->canBeVotedOnBy($viewer))
            <form method="POST" action="{{ route('suggestions.vote', $suggestion) }}">
                @csrf
                @if ($voted)
                    @method('DELETE')
                    <button class="text-sm underline">Take back your vote</button>
                @else
                    <button class="text-sm font-medium underline">Vote for this</button>
                @endif
            </form>
        @endif
    </article>

    <section class="max-w-2xl">
        <h2 class="mb-3 text-lg font-semibold">Comments</h2>
        <ul class="mb-6 space-y-3">
            @forelse ($suggestion->comments as $comment)
                <li id="comment-{{ $comment->id }}" class="rounded-lg border border-stone-200 bg-white px-4 py-3 dark:border-stone-800 dark:bg-stone-900" data-comment="{{ $comment->id }}">
                    <div class="mb-1 flex flex-wrap items-center justify-between gap-2 text-xs text-stone-500">
                        <span><span class="font-medium text-stone-700 dark:text-stone-300" data-author>{{ $comment->authorLabelFor($viewer) }}</span> · {{ $comment->created_at?->diffForHumans() }}</span>
                        @if ($comment->canBeDeletedBy($viewer))
                            <form method="POST" action="{{ route('suggestions.comments.destroy', [$suggestion, $comment]) }}">
                                @csrf
                                @method('DELETE')
                                <button class="underline">Delete</button>
                            </form>
                        @endif
                    </div>
                    <p class="whitespace-pre-line text-sm">{{ $comment->body }}</p>
                </li>
            @empty
                <li class="text-sm text-stone-500">No comments yet.</li>
            @endforelse
        </ul>

        <form method="POST" action="{{ route('suggestions.comments.store', $suggestion) }}" class="space-y-3">
            @csrf
            <label for="comment-body" class="block text-sm font-medium">Add a comment</label>
            <textarea id="comment-body" name="body" rows="3" required minlength="2" maxlength="5000"
                      class="block w-full rounded-md border border-stone-300 px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-950">{{ old('body') }}</textarea>
            @error('body') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            <p class="text-xs text-stone-500">
                @if ($suggestion->is_public) Everyone who can see the board can read this thread. Others see you as a role, not by name.
                @else Only the sender and the admins can read this thread.
                @endif
            </p>
            <button class="{{ $primary }}">Comment</button>
        </form>
    </section>
</x-layout>
