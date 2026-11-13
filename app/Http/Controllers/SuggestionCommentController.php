<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSuggestionCommentRequest;
use App\Models\Suggestion;
use App\Models\SuggestionComment;
use App\Support\Suggestions\SuggestionMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A suggestion's own page with its thread. Anyone who may see the suggestion may read and
 * add to the thread; a suggestion they may not see is a 404, never a 403.
 */
class SuggestionCommentController extends Controller
{
    public function show(Request $request, Suggestion $suggestion): View
    {
        $user = $request->user();
        abort_unless($suggestion->isVisibleTo($user), 404);

        $suggestion->load(['user', 'comments.user', 'comments.suggestion'])->loadCount('voters as votes_count');

        return view('suggestions.show', [
            'suggestion' => $suggestion,
            'voted' => $suggestion->voters()->whereKey($user->id)->exists(),
        ]);
    }

    public function store(StoreSuggestionCommentRequest $request, Suggestion $suggestion, SuggestionMailer $mailer): RedirectResponse
    {
        abort_unless($suggestion->isVisibleTo($request->user()), 404);

        $comment = new SuggestionComment($request->validated());
        $comment->user()->associate($request->user());
        $suggestion->comments()->save($comment);
        $mailer->commented($comment);

        return redirect()->to(route('suggestions.show', $suggestion).'#comment-'.$comment->id)->with('status', 'Comment added.');
    }

    public function destroy(Request $request, Suggestion $suggestion, SuggestionComment $comment): RedirectResponse
    {
        abort_unless($suggestion->isVisibleTo($request->user()), 404);
        abort_unless($comment->canBeDeletedBy($request->user()), 403);

        $comment->delete();

        return redirect()->route('suggestions.show', $suggestion)->with('status', 'Comment deleted.');
    }
}
