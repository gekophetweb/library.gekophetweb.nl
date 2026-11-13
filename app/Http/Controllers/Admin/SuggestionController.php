<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SuggestionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSuggestionRequest;
use App\Models\Suggestion;
use App\Support\Suggestions\SuggestionMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Every user's suggestions, newest first, for admins to triage. */
class SuggestionController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(SuggestionStatus::class)],
            'sort' => ['nullable', 'in:newest,votes'],
        ]);
        $status = $request->enum('status', SuggestionStatus::class);
        $sort = $request->input('sort', 'newest');

        $suggestions = Suggestion::query()
            ->with('user')
            ->withCount(['voters as votes_count', 'comments'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($sort === 'votes', fn ($query) => $query->orderByDesc('votes_count'))
            ->latest()
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.suggestions.index', [
            'suggestions' => $suggestions,
            'status' => $status,
            'sort' => $sort,
            'statuses' => SuggestionStatus::cases(),
        ]);
    }

    public function update(UpdateSuggestionRequest $request, Suggestion $suggestion, SuggestionMailer $mailer): RedirectResponse
    {
        $suggestion->status = $request->enum('status', SuggestionStatus::class);
        $suggestion->admin_reply = $request->validated('admin_reply') ?: null;
        // An unticked checkbox sends nothing: absent means off the board.
        $suggestion->is_public = $request->boolean('is_public');
        $suggestion->save();
        $mailer->updated($suggestion, $request->user());

        return back()->with('status', 'Suggestion updated.');
    }
}
