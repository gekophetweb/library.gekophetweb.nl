<?php

namespace App\Http\Controllers;

use App\Models\Suggestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The public suggestion board: suggestions an admin put there, most votes first. */
class SuggestionVoteController extends Controller
{
    public function index(Request $request): View
    {
        return view('suggestions.board', [
            'suggestions' => Suggestion::query()->onBoard()->withVotesFor($request->user())
                ->orderByDesc('votes_count')->latest()->latest('id')
                ->paginate(25),
        ]);
    }

    public function store(Request $request, Suggestion $suggestion): RedirectResponse
    {
        abort_unless($suggestion->is_public, 404);
        abort_unless($suggestion->canBeVotedOnBy($request->user()), 403, 'You cannot vote on your own suggestion.');

        $suggestion->voters()->syncWithoutDetaching([$request->user()->id]);

        return back()->with('status', 'Vote added.');
    }

    public function destroy(Request $request, Suggestion $suggestion): RedirectResponse
    {
        abort_unless($suggestion->is_public, 404);

        $suggestion->voters()->detach($request->user()->id);

        return back()->with('status', 'Vote removed.');
    }
}
