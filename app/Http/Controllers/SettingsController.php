<?php

namespace App\Http\Controllers;

use App\Enums\ApiKeyProvider;
use App\Models\Category;
use App\Models\LibraryShare;
use App\Models\LoginCode;
use App\Models\Series;
use App\Models\SsoProvider;
use App\Models\User;
use App\Models\UserIdentity;
use App\Support\Auth\LoginSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings.edit', ['user' => $request->user()]);
    }

    public function apiKeys(Request $request): View
    {
        return view('settings.api-keys.index', [
            'apiKeyProviders' => ApiKeyProvider::cases(),
            'savedApiKeys' => $request->user()->apiKeys()->pluck('updated_at', 'provider'),
        ]);
    }

    /** One provider's step-by-step guide to getting a key, with the form to save it. */
    public function apiKeyGuide(Request $request, string $provider): View
    {
        $provider = ApiKeyProvider::tryFrom($provider) ?? abort(404);

        return view('settings.api-keys.show', [
            'provider' => $provider,
            'saved' => $request->user()->apiKey($provider) !== null,
        ]);
    }

    public function tokens(Request $request): View
    {
        return view('settings.tokens', ['tokens' => $request->user()->tokens()->latest()->get()]);
    }

    public function shares(Request $request): View
    {
        return view('settings.shares', [
            'shares' => LibraryShare::query()->whereBelongsTo($request->user())->with('shareable')->latest()->get(),
            'shareCategories' => Category::query()->whereNull('parent_id')->orderBy('sort')->orderBy('id')->get(),
            'shareSeries' => Series::query()->ownedBy($request->user())->orderBy('name')->get(),
        ]);
    }

    public function signIn(Request $request): View
    {
        $identities = $request->user()->identities()->with('provider')->get();

        return view('settings.sign-in', [
            'user' => $request->user(),
            'identities' => $identities,
            'linkable' => SsoProvider::query()->enabled()->ordered()
                ->whereNotIn('id', $identities->pluck('sso_provider_id'))
                ->get(),
        ]);
    }

    public function account(Request $request): View
    {
        return view('settings.account', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $request->user()->update(['name' => trim($validated['name'])]);

        return redirect()->route('settings.edit')->with('status', 'Saved.');
    }

    /** The one opt-out for suggestion e-mails (plan suggestion-notifications). */
    public function updateNotifications(Request $request): RedirectResponse
    {
        $request->user()->update(['suggestion_emails' => $request->boolean('suggestion_emails')]);

        return redirect()->route('settings.edit')->with('status', 'E-mail settings saved.');
    }

    public function updateApiKey(Request $request, string $provider): RedirectResponse
    {
        $provider = ApiKeyProvider::tryFrom($provider) ?? abort(404);
        $validated = $request->validate(['key' => ['required', 'string', 'max:500']]);

        $request->user()->apiKeys()->updateOrCreate(['provider' => $provider], ['key' => trim($validated['key'])]);

        return redirect()->route('settings.api-keys')->with('status', "{$provider->label()} key saved.");
    }

    public function destroyApiKey(Request $request, string $provider): RedirectResponse
    {
        $provider = ApiKeyProvider::tryFrom($provider) ?? abort(404);

        $request->user()->apiKeys()->where('provider', $provider->value)->delete();

        return redirect()->route('settings.api-keys')->with('status', "{$provider->label()} key removed.");
    }

    /** A token for the phone app. The plain text is shown once, right after creating it. */
    public function storeToken(Request $request): RedirectResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:100']]);

        $token = $request->user()->createToken(trim($validated['name']));

        return redirect()->route('settings.tokens')
            ->with('token', $token->plainTextToken)
            ->with('status', 'Token created. Copy it now: it is not shown again.');
    }

    public function destroyToken(Request $request, int $token): RedirectResponse
    {
        $request->user()->tokens()->whereKey($token)->firstOrFail()->delete();

        return redirect()->route('settings.tokens')->with('status', 'Token revoked.');
    }

    public function destroyIdentity(Request $request, UserIdentity $identity): RedirectResponse
    {
        abort_unless($identity->user_id === $request->user()->id, 404);

        if (! LoginSettings::emailCodesEnabled() && $request->user()->identities()->count() <= 1) {
            return back()->withErrors(['identity' => 'This is your only way to sign in, so it cannot be unlinked.']);
        }

        $identity->delete();

        return redirect()->route('settings.sign-in')->with('status', 'Sign-in method unlinked.');
    }

    /**
     * Deletes the signed-in account and everything that is only theirs (collection, series,
     * shares, keys, tokens, sign-in links, votes). Suggestions and comments stay, shown as
     * "[deleted user]". Confirmed by typing the account's e-mail address; the last admin
     * can't delete their account, so the app always keeps one.
     */
    public function destroyAccount(Request $request): RedirectResponse
    {
        $user = $request->user();
        $typed = Str::lower(trim((string) $request->validateWithBag('deleteAccount', [
            'confirm_email' => ['required', 'string', 'max:255'],
        ])['confirm_email']));

        if ($typed !== Str::lower($user->email)) {
            return back()->withErrors(['confirm_email' => 'Type your e-mail address exactly to confirm.'], 'deleteAccount');
        }

        if ($user->is_admin && ! User::query()->where('is_admin', true)->whereKeyNot($user->id)->exists()) {
            return back()->withErrors(['confirm_email' => 'You are the only admin. Make someone else an admin first (Admin → Users), so the site keeps one.'], 'deleteAccount');
        }

        // Log out first: logout() saves a fresh remember token on the user, which on an
        // already-deleted model would insert the account again.
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            LoginCode::query()->where('email', $user->email)->delete();

            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }

            // Foreign keys cascade to the user's own data and set suggestion authors to null.
            $user->delete();
        });

        return redirect()->route('login')->with('status', 'Your account has been deleted.');
    }
}
