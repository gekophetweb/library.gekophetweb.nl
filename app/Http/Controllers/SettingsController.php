<?php

namespace App\Http\Controllers;

use App\Models\SsoProvider;
use App\Models\UserIdentity;
use App\Support\Auth\LoginSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $identities = $request->user()->identities()->with('provider')->get();

        return view('settings.edit', [
            'user' => $request->user(),
            'identities' => $identities,
            'linkable' => SsoProvider::query()->enabled()->ordered()
                ->whereNotIn('id', $identities->pluck('sso_provider_id'))
                ->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $request->user()->update(['name' => trim($validated['name'])]);

        return redirect()->route('settings.edit')->with('status', 'Saved.');
    }

    public function destroyIdentity(Request $request, UserIdentity $identity): RedirectResponse
    {
        abort_unless($identity->user_id === $request->user()->id, 404);

        if (! LoginSettings::emailCodesEnabled() && $request->user()->identities()->count() <= 1) {
            return back()->withErrors(['identity' => 'This is your only way to sign in, so it cannot be unlinked.']);
        }

        $identity->delete();

        return redirect()->route('settings.edit')->with('status', 'Sign-in method unlinked.');
    }
}
