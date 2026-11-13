<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SuggestionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSuggestionRequest;
use App\Models\Suggestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Every user's suggestions, newest first, for admins to triage. */
class SuggestionController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['status' => ['nullable', Rule::enum(SuggestionStatus::class)]]);
        $status = $request->enum('status', SuggestionStatus::class);

        $suggestions = Suggestion::query()
            ->with('user')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.suggestions.index', [
            'suggestions' => $suggestions,
            'status' => $status,
            'statuses' => SuggestionStatus::cases(),
        ]);
    }

    public function update(UpdateSuggestionRequest $request, Suggestion $suggestion): RedirectResponse
    {
        $suggestion->status = $request->enum('status', SuggestionStatus::class);
        $suggestion->admin_reply = $request->validated('admin_reply') ?: null;
        $suggestion->save();

        return back()->with('status', 'Suggestion updated.');
    }
}
