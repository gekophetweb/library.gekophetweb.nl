<?php

namespace App\Http\Controllers;

use App\Enums\ApiKeyProvider;
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
            'apiKeyProviders' => ApiKeyProvider::cases(),
            'savedApiKeys' => $request->user()->apiKeys()->pluck('updated_at', 'provider'),
            'tokens' => $request->user()->tokens()->latest()->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $request->user()->update(['name' => trim($validated['name'])]);

        return redirect()->route('settings.edit')->with('status', 'Saved.');
    }

    public function updateApiKey(Request $request, string $provider): RedirectResponse
    {
        $provider = ApiKeyProvider::tryFrom($provider) ?? abort(404);
        $validated = $request->validate(['key' => ['required', 'string', 'max:500']]);

        $request->user()->apiKeys()->updateOrCreate(['provider' => $provider], ['key' => trim($validated['key'])]);

        return redirect()->route('settings.edit')->with('status', "{$provider->label()} key saved.");
    }

    public function destroyApiKey(Request $request, string $provider): RedirectResponse
    {
        $provider = ApiKeyProvider::tryFrom($provider) ?? abort(404);

        $request->user()->apiKeys()->where('provider', $provider->value)->delete();

        return redirect()->route('settings.edit')->with('status', "{$provider->label()} key removed.");
    }

    /** A token for the phone app. The plain text is shown once, right after creating it. */
    public function storeToken(Request $request): RedirectResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:100']]);

        $token = $request->user()->createToken(trim($validated['name']));

        return redirect()->route('settings.edit')
            ->with('token', $token->plainTextToken)
            ->with('status', 'Token created. Copy it now: it is not shown again.');
    }

    public function destroyToken(Request $request, int $token): RedirectResponse
    {
        $request->user()->tokens()->whereKey($token)->firstOrFail()->delete();

        return redirect()->route('settings.edit')->with('status', 'Token revoked.');
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
