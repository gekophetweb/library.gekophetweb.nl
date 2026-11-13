<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The one question a new account gets after its first login: what to call the user. */
class WelcomeNameController extends Controller
{
    public function show(Request $request): View
    {
        return view('welcome.name', ['user' => $request->user()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $request->user()->update(['name' => trim($validated['name'])]);

        return redirect()->route('home');
    }
}
