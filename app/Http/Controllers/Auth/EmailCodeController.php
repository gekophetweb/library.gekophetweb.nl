<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginCode;
use App\Services\Auth\EmailLoginCodes;
use App\Support\Auth\LoginSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Login without SSO: a single-use code sent by e-mail (any address may sign up). */
class EmailCodeController extends Controller
{
    private const string STATUS = 'Check your inbox for a login code.';

    public function __construct(private readonly EmailLoginCodes $codes) {}

    public function request(Request $request): RedirectResponse
    {
        abort_unless(LoginSettings::emailCodesEnabled(), 404);

        $email = EmailLoginCodes::normalizeEmail((string) $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ])['email']);

        // Anyone can ask for a code for any address, so these limits are what keep the
        // form from being used to flood someone's inbox.
        $limits = [
            'login-code:email:'.sha1($email) => [3, 10 * 60],
            'login-code:ip:'.$request->ip() => [10, 60 * 60],
        ];

        foreach ($limits as $key => [$max, $decay]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                throw ValidationException::withMessages([
                    'email' => 'Too many login codes requested. Try again in '
                        .max(1, (int) ceil(RateLimiter::availableIn($key) / 60)).' minutes.',
                ]);
            }
        }

        foreach ($limits as $key => [, $decay]) {
            RateLimiter::hit($key, $decay);
        }

        $this->codes->send($email, $request->ip());

        // The address goes in the session, never in the URL.
        $request->session()->put('login.email', $email);

        return redirect()->route('login.code')->with('status', self::STATUS);
    }

    public function show(Request $request): View|RedirectResponse
    {
        $email = $request->session()->get('login.email');

        if ($email === null || ! LoginSettings::emailCodesEnabled()) {
            return redirect()->route('login');
        }

        return view('auth.code', ['email' => $email, 'minutes' => EmailLoginCodes::MINUTES_VALID]);
    }

    public function verify(Request $request): RedirectResponse
    {
        abort_unless(LoginSettings::emailCodesEnabled(), 404);

        $email = $request->session()->get('login.email');

        if ($email === null) {
            return redirect()->route('login');
        }

        $code = preg_replace('/\D/', '', (string) $request->validate([
            'code' => ['required', 'string', 'max:20'],
        ])['code']);

        $loginCode = $this->codes->check($email, $code);

        if ($loginCode === null) {
            throw ValidationException::withMessages(['code' => 'That code is wrong or has expired.']);
        }

        return $this->logIn($request, $loginCode);
    }

    /** The one-click link in the mail (a signed URL that expires with the code). */
    public function link(Request $request, LoginCode $loginCode): RedirectResponse
    {
        abort_unless(LoginSettings::emailCodesEnabled(), 404);

        if (! $loginCode->isUsable()) {
            return redirect()->route('login')->withErrors(['email' => 'That sign-in link has expired. Request a new code.']);
        }

        return $this->logIn($request, $loginCode);
    }

    private function logIn(Request $request, LoginCode $loginCode): RedirectResponse
    {
        $result = $this->codes->redeem($loginCode);

        if ($result === null) {
            throw ValidationException::withMessages(['code' => 'That code is wrong or has expired.']);
        }

        [$user, $created] = $result;

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('login.email');

        return $created
            ? redirect()->route('welcome.name')
            : redirect()->intended(route('home'));
    }
}
