<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SsoDriver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SsoProviderRequest;
use App\Models\AppSetting;
use App\Models\SsoProvider;
use App\Support\Auth\LoginSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Admin set-up of SSO providers and the e-mail code switches ("Sign-in" page). */
class SsoProviderController extends Controller
{
    public function index(): View
    {
        return view('admin.sso.index', [
            'providers' => SsoProvider::query()->ordered()->withCount('identities')->get(),
            'emailCodes' => LoginSettings::emailCodesEnabled(),
            'emailRegistration' => LoginSettings::emailRegistrationEnabled(),
        ]);
    }

    public function create(): View
    {
        return view('admin.sso.form', ['provider' => new SsoProvider(['enabled' => true]), 'drivers' => SsoDriver::cases()]);
    }

    public function store(SsoProviderRequest $request): RedirectResponse
    {
        $provider = SsoProvider::query()->create($request->providerAttributes());

        return redirect()->route('admin.sso.index')
            ->with('status', "{$provider->name} added. Register its callback URL at the identity provider.");
    }

    public function edit(SsoProvider $provider): View
    {
        return view('admin.sso.form', ['provider' => $provider, 'drivers' => SsoDriver::cases()]);
    }

    public function update(SsoProviderRequest $request, SsoProvider $provider): RedirectResponse
    {
        $attributes = $request->providerAttributes();

        if ($provider->enabled && ! $attributes['enabled'] && $this->isLastWayIn($provider)) {
            return back()->withInput()->withErrors(['enabled' => 'This is the only enabled sign-in method while e-mail codes are off. Switch e-mail codes on first.']);
        }

        $provider->update($attributes);

        return redirect()->route('admin.sso.index')->with('status', "{$provider->name} saved.");
    }

    public function destroy(SsoProvider $provider): RedirectResponse
    {
        if ($provider->enabled && $this->isLastWayIn($provider)) {
            return back()->withErrors(['provider' => 'This is the only enabled sign-in method while e-mail codes are off. Switch e-mail codes on first.']);
        }

        $provider->delete();

        return redirect()->route('admin.sso.index')->with('status', "{$provider->name} removed.");
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email_codes_enabled' => ['required', 'boolean'],
            'email_registration_enabled' => ['required', 'boolean'],
        ]);

        $codes = (bool) $validated['email_codes_enabled'];

        if (! $codes && ! SsoProvider::query()->enabled()->exists()) {
            return back()->withErrors(['email_codes_enabled' => 'Enable an SSO provider before switching e-mail codes off, or nobody can sign in.']);
        }

        AppSetting::put(LoginSettings::EMAIL_CODES, $codes);
        AppSetting::put(LoginSettings::EMAIL_REGISTRATION, (bool) $validated['email_registration_enabled']);

        return redirect()->route('admin.sso.index')->with('status', 'Sign-in settings saved.');
    }

    /** Would losing $provider leave no way to sign in at all? */
    private function isLastWayIn(SsoProvider $provider): bool
    {
        return ! LoginSettings::emailCodesEnabled()
            && ! SsoProvider::query()->enabled()->whereKeyNot($provider->id)->exists();
    }
}
