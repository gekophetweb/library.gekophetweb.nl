<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auth\DevLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Local development only: sign in as the seeded dev user without SSO or an e-mail code. */
class DevLoginController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(DevLogin::available(), 404);

        $user = User::query()->where('email', config('auth.dev_user_email'))->first();

        if ($user === null) {
            return redirect()->route('login')->withErrors([
                'email' => 'The dev user does not exist yet. Run: php artisan db:seed',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home');
    }
}
