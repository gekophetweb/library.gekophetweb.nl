<?php

use App\Mail\LoginCodeMail;
use App\Models\AppSetting;
use App\Models\LoginCode;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

beforeEach(fn () => Mail::fake());

/** Requests a code for $email and returns the plain code from the queued mail. */
function requestLoginCode(string $email): ?string
{
    test()->post(route('login.code.request'), ['email' => $email]);

    $code = null;
    Mail::assertQueued(LoginCodeMail::class, function (LoginCodeMail $mail) use ($email, &$code) {
        if ($mail->hasTo($email)) {
            $code = $mail->code;
        }

        return true;
    });

    return $code;
}

it('redirects guests to the login page', function () {
    $this->get('/')->assertRedirect(route('login'));
});

it('shows the e-mail code form on the login page', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('E-mail me a login code');
});

it('mails a 6-digit code to an existing user and stores only its hash', function () {
    User::factory()->create(['email' => 'ann@example.com']);

    $this->post(route('login.code.request'), ['email' => 'Ann@Example.com'])
        ->assertRedirect(route('login.code'))
        ->assertSessionHas('status', 'Check your inbox for a login code.');

    $code = null;
    Mail::assertQueued(LoginCodeMail::class, function (LoginCodeMail $mail) use (&$code) {
        $code = $mail->code;

        return $mail->hasTo('ann@example.com');
    });

    expect($code)->toMatch('/^\d{6}$/')
        ->and(LoginCode::sole()->code_hash)->not->toBe($code)
        ->and(LoginCode::sole()->code_hash)->not->toContain($code);
});

it('gives a new e-mail the same response and creates no account yet', function () {
    $this->post(route('login.code.request'), ['email' => 'new@example.com'])
        ->assertRedirect(route('login.code'))
        ->assertSessionHas('status', 'Check your inbox for a login code.');

    Mail::assertQueued(LoginCodeMail::class, fn (LoginCodeMail $mail) => $mail->hasTo('new@example.com'));
    expect(User::count())->toBe(0);
});

it('logs an existing user in with a correct code', function () {
    $user = User::factory()->create(['email' => 'ann@example.com']);
    $code = requestLoginCode('ann@example.com');

    $this->post(route('login.code.verify'), ['code' => $code])
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
});

it('creates a verified account for a new e-mail and asks for a name', function () {
    $code = requestLoginCode('new@example.com');

    $this->post(route('login.code.verify'), ['code' => $code])
        ->assertRedirect(route('welcome.name'));

    $user = User::sole();
    expect($user->email)->toBe('new@example.com')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->is_admin)->toBeFalse();
    $this->assertAuthenticatedAs($user);

    $this->get(route('welcome.name'))->assertOk()->assertSee('Your name');
    $this->post(route('welcome.name.store'), ['name' => 'New Person'])->assertRedirect(route('home'));
    expect($user->refresh()->name)->toBe('New Person');
});

it('neither mails nor creates an account for a new e-mail when sign-up is closed', function () {
    AppSetting::put('auth.email_registration_enabled', false);
    User::factory()->create(['email' => 'ann@example.com']);

    $this->post(route('login.code.request'), ['email' => 'new@example.com'])
        ->assertRedirect(route('login.code'))
        ->assertSessionHas('status', 'Check your inbox for a login code.');

    Mail::assertNothingQueued();
    expect(LoginCode::count())->toBe(0);

    // Existing users still get codes.
    expect(requestLoginCode('ann@example.com'))->toMatch('/^\d{6}$/');
});

it('accepts a code only once', function () {
    User::factory()->create(['email' => 'ann@example.com']);
    $code = requestLoginCode('ann@example.com');

    $this->post(route('login.code.verify'), ['code' => $code]);
    $this->post(route('logout'));

    $this->withSession(['login.email' => 'ann@example.com'])
        ->post(route('login.code.verify'), ['code' => $code])
        ->assertSessionHasErrors('code');
    $this->assertGuest();
});

it('rejects an expired code', function () {
    User::factory()->create(['email' => 'ann@example.com']);
    $code = requestLoginCode('ann@example.com');

    $this->travel(11)->minutes();

    $this->post(route('login.code.verify'), ['code' => $code])->assertSessionHasErrors('code');
    $this->assertGuest();
});

it('burns a code after five wrong attempts', function () {
    User::factory()->create(['email' => 'ann@example.com']);
    $code = requestLoginCode('ann@example.com');
    $wrong = $code === '000000' ? '111111' : '000000';

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login.code.verify'), ['code' => $wrong])->assertSessionHasErrors('code');
    }

    $this->post(route('login.code.verify'), ['code' => $code])->assertSessionHasErrors('code');
    $this->assertGuest();
});

it('invalidates the previous code when a new one is requested', function () {
    User::factory()->create(['email' => 'ann@example.com']);
    $first = requestLoginCode('ann@example.com');

    Mail::fake();
    $second = requestLoginCode('ann@example.com');

    if ($first !== $second) {
        $this->post(route('login.code.verify'), ['code' => $first])->assertSessionHasErrors('code');
    }

    $this->post(route('login.code.verify'), ['code' => $second])->assertRedirect(route('home'));
});

it('limits code requests per e-mail', function () {
    User::factory()->create(['email' => 'ann@example.com']);

    foreach (range(1, 3) as $request) {
        $this->post(route('login.code.request'), ['email' => 'ann@example.com'])->assertSessionHasNoErrors();
    }

    $this->post(route('login.code.request'), ['email' => 'ann@example.com'])->assertSessionHasErrors('email');
    Mail::assertQueuedCount(3);
});

it('limits code requests per IP address', function () {
    foreach (range(1, 10) as $request) {
        $this->post(route('login.code.request'), ['email' => "person{$request}@example.com"])->assertSessionHasNoErrors();
    }

    $this->post(route('login.code.request'), ['email' => 'person11@example.com'])->assertSessionHasErrors('email');
    Mail::assertQueuedCount(10);
});

it('logs in through the signed link in the mail', function () {
    $user = User::factory()->create(['email' => 'ann@example.com']);
    requestLoginCode('ann@example.com');

    $link = null;
    Mail::assertQueued(LoginCodeMail::class, function (LoginCodeMail $mail) use (&$link) {
        $link = $mail->link;

        return true;
    });

    $this->post(route('logout'));
    $this->get($link)->assertRedirect(route('home'));
    $this->assertAuthenticatedAs($user);
});

it('rejects an expired or tampered sign-in link', function () {
    User::factory()->create(['email' => 'ann@example.com']);
    requestLoginCode('ann@example.com');
    $loginCode = LoginCode::sole();

    $tampered = URL::temporarySignedRoute('login.code.link', now()->addMinutes(10), ['loginCode' => $loginCode->id]).'x';
    $this->get($tampered)->assertForbidden();

    $expired = URL::temporarySignedRoute('login.code.link', $loginCode->expires_at, ['loginCode' => $loginCode->id]);
    $this->travel(11)->minutes();
    $this->get($expired)->assertForbidden();
    $this->assertGuest();
});

it('hides and refuses e-mail codes when an admin switched them off', function () {
    AppSetting::put('auth.email_codes_enabled', false);

    $this->get(route('login'))->assertOk()->assertDontSee('E-mail me a login code');
    $this->post(route('login.code.request'), ['email' => 'ann@example.com'])->assertNotFound();
    Mail::assertNothingQueued();
});

it('logs out', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('prunes login codes older than a day', function () {
    User::factory()->create(['email' => 'ann@example.com']);
    requestLoginCode('ann@example.com');
    $this->travel(25)->hours();

    $this->artisan('model:prune', ['--model' => [LoginCode::class]]);

    expect(LoginCode::count())->toBe(0);
});
