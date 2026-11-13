<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSuggestionRequest;
use App\Support\Suggestions\SuggestionMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The suggestion box for signed-in users: send one, and look back at your own. */
class SuggestionController extends Controller
{
    public function index(Request $request): View
    {
        return view('suggestions.index', [
            'suggestions' => $request->user()->suggestions()->withCount(['voters as votes_count', 'comments'])->latest()->latest('id')->get(),
        ]);
    }

    public function store(StoreSuggestionRequest $request, SuggestionMailer $mailer): RedirectResponse
    {
        $mailer->created($request->user()->suggestions()->create($request->validated()));

        return redirect()->route('suggestions.index')->with('status', 'Thanks! Your suggestion has been sent.');
    }
}
