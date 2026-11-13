<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SsoProvider;
use App\Services\Auth\SsoDriverFactory;
use App\Services\Auth\SsoLogin;
use App\Services\Auth\SsoProfile;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Laravel\Socialite\Two\InvalidStateException;
use RuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

/**
 * One redirect + callback pair per provider, used three ways (kept in the session):
 * `login` for a guest, `link` for a signed-in user adding a sign-in method, and `test`
 * for an admin checking a provider's setup without logging in or linking anything.
 */
class SsoController extends Controller
{
    public function __construct(
        private readonly SsoDriverFactory $drivers,
        private readonly SsoLogin $sso,
    ) {}

    public function redirect(Request $request, SsoProvider $provider): SymfonyRedirect
    {
        abort_unless($provider->enabled, 404);

        return $this->start($request, $provider, $request->user() ? 'link' : 'login');
    }

    /** Admin "Test login": works for disabled providers too, so a setup can be checked first. */
    public function test(Request $request, SsoProvider $provider): SymfonyRedirect
    {
        return $this->start($request, $provider, 'test');
    }

    public function callback(Request $request, SsoProvider $provider): RedirectResponse
    {
        $flow = $request->session()->pull('sso.flow');
        $mode = ($flow['provider'] ?? null) === $provider->id ? $flow['mode'] : ($request->user() ? 'link' : 'login');

        if ($mode === 'test') {
            abort_unless(Gate::allows('admin'), 403);
        } else {
            abort_unless($provider->enabled, 404);
        }

        $failed = match ($mode) {
            'test' => redirect()->route('admin.sso.index'),
            'link' => redirect()->route('settings.edit'),
            default => redirect()->route('login'),
        };

        try {
            $profile = SsoProfile::from($provider->driver, $this->drivers->for($provider)->user());
        } catch (InvalidStateException|GuzzleException|RequestException|ConnectionException|RuntimeException) {
            return $failed->withErrors(['sso' => "Signing in with {$provider->name} did not work. Please try again."]);
        }

        return match ($mode) {
            'test' => $this->reportTest($provider, $profile),
            'link' => $this->linkToCurrentUser($request, $provider, $profile, $failed),
            default => $this->logIn($request, $provider, $profile, $failed),
        };
    }

    private function start(Request $request, SsoProvider $provider, string $mode): SymfonyRedirect
    {
        $request->session()->put('sso.flow', ['provider' => $provider->id, 'mode' => $mode]);

        try {
            return $this->drivers->for($provider)->redirect();
        } catch (RequestException|ConnectionException|RuntimeException) {
            return redirect()->route($mode === 'test' ? 'admin.sso.index' : ($mode === 'link' ? 'settings.edit' : 'login'))
                ->withErrors(['sso' => "{$provider->name} could not be reached. Check its settings."]);
        }
    }

    private function logIn(Request $request, SsoProvider $provider, SsoProfile $profile, RedirectResponse $failed): RedirectResponse
    {
        $user = $this->sso->resolve($provider, $profile);

        if ($user === null) {
            return $failed->withErrors(['sso' => $profile->emailVerified
                ? "There is no account for this {$provider->name} login. Sign in another way first, then link {$provider->name} in your settings."
                : "{$provider->name} did not confirm your e-mail address. Sign in another way first, then link {$provider->name} in your settings."]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    private function linkToCurrentUser(Request $request, SsoProvider $provider, SsoProfile $profile, RedirectResponse $failed): RedirectResponse
    {
        if (! $this->sso->link($request->user(), $provider, $profile)) {
            return $failed->withErrors(['sso' => "That {$provider->name} login is already linked to another account."]);
        }

        return redirect()->route('settings.edit')->with('status', "{$provider->name} is now linked.");
    }

    private function reportTest(SsoProvider $provider, SsoProfile $profile): RedirectResponse
    {
        $email = $profile->email ?? 'no e-mail';
        $verified = $profile->emailVerified ? 'verified' : 'not verified';

        return redirect()->route('admin.sso.index')
            ->with('status', "Test login with {$provider->name} worked: subject {$profile->subject}, {$email} ({$verified}).");
    }
}
