<?php

use App\Authorization\Admin\Role as AdminRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
    config(['admin.url' => 'http://admin.birdcar.test']);
});

test('admin guest forms render Flux controls and system appearance', function (string $path, string $submit): void {
    $this->get('http://admin.birdcar.test'.$path)
        ->assertOk()
        ->assertSee('window.Flux.applyAppearance', false)
        ->assertSee("window.localStorage.getItem('flux.appearance') || 'system'", false)
        ->assertSee('data-flux-input', false)
        ->assertSee('data-flux-button', false)
        ->assertSee('type="submit"', false)
        ->assertSee('aria-label="Admin sign in"', false)
        ->assertSee('data-flux-brand', false)
        ->assertSee('src="/favicon.svg"', false)
        ->assertSee('name="email"', false)
        ->assertSee($submit)
        ->assertDontSee('<html lang="en" class="dark">', false);
})->with([
    'sign in' => ['/login', 'Sign in'],
    'password setup' => ['/reset-password/preview-token?email=reader%40example.com', 'Set password'],
]);

test('sign in displays field errors and retains only the email input', function (): void {
    $this->from('http://admin.birdcar.test/login')
        ->post('http://admin.birdcar.test/login', ['email' => 'reader@example.com', 'password' => 'wrong-password'])
        ->assertRedirect('http://admin.birdcar.test/login')
        ->assertSessionHasErrors('email');

    $this->withCookie(config('session.cookie'), session()->getId())
        ->get('http://admin.birdcar.test/login')
        ->assertOk()
        ->assertSee('These credentials do not match our records.')
        ->assertSee('data-flux-error', false)
        ->assertSee('value="reader@example.com"', false)
        ->assertSee('autocomplete="current-password"', false)
        ->assertDontSee('value="wrong-password"', false);
});

test('two factor form uses labeled Flux inputs and the existing challenge action', function (): void {
    $user = User::factory()->create();

    $this->withSession(['login.id' => $user->id])
        ->get('http://admin.birdcar.test/two-factor-challenge')
        ->assertOk()
        ->assertSee('window.Flux.applyAppearance', false)
        ->assertSee('name="code"', false)
        ->assertSee('name="recovery_code"', false)
        ->assertSee('autocomplete="one-time-code"', false)
        ->assertSee('data-flux-input', false)
        ->assertSee('type="submit"', false);
});

test('password setup displays expired invitation errors beside the email field', function (): void {
    $user = User::factory()->create();
    $url = 'http://admin.birdcar.test/reset-password/expired-token?email='.urlencode($user->email);

    $this->from($url)->post('http://admin.birdcar.test/reset-password', [
        'token' => 'expired-token',
        'email' => $user->email,
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ])->assertSessionHasErrors('email');

    $this->withCookie(config('session.cookie'), session()->getId())->get($url)
        ->assertOk()
        ->assertSee(trans(Password::INVALID_TOKEN));
});

test('two factor form displays rejected recovery code errors', function (): void {
    $user = User::factory()->create();

    $this->withSession([
        'login.id' => $user->id,
        'errors' => ['default' => [
            'format' => ':message',
            'messages' => ['recovery_code' => ['The provided two factor recovery code was invalid.']],
        ]],
    ])->get('http://admin.birdcar.test/two-factor-challenge')
        ->assertOk()
        ->assertSee('The provided two factor recovery code was invalid.');
});

test('existing admin users can log in and reach safe intended admin urls', function (): void {
    $user = User::factory()->create(['password' => Hash::make('secret-password')]);
    $user->assignRole(AdminRole::Access->value);

    $this->withSession(['url.intended' => 'http://admin.birdcar.test/publishing'])
        ->post('http://admin.birdcar.test/login', ['email' => $user->email, 'password' => 'secret-password'])
        ->assertRedirect('http://admin.birdcar.test/publishing');
});

test('unsafe intended login redirects fall back to admin index', function (): void {
    $user = User::factory()->create(['password' => Hash::make('secret-password')]);
    $user->assignRole(AdminRole::Access->value);

    $this->withSession(['url.intended' => 'https://evil.example/phish'])
        ->post('http://admin.birdcar.test/login', ['email' => $user->email, 'password' => 'secret-password'])
        ->assertRedirect('http://admin.birdcar.test/');
});

test('intended login redirects reject mismatched admin origin scheme or port', function (): void {
    $user = User::factory()->create(['password' => Hash::make('secret-password')]);
    $user->assignRole(AdminRole::Access->value);

    $this->withSession(['url.intended' => 'https://admin.birdcar.test/publishing'])
        ->post('http://admin.birdcar.test/login', ['email' => $user->email, 'password' => 'secret-password'])
        ->assertRedirect('http://admin.birdcar.test/');

    auth()->logout();

    $this->withSession(['url.intended' => 'http://admin.birdcar.test:8443/publishing'])
        ->post('http://admin.birdcar.test/login', ['email' => $user->email, 'password' => 'secret-password'])
        ->assertRedirect('http://admin.birdcar.test/');
});

test('invalid credentials return to login with errors', function (): void {
    $user = User::factory()->create(['password' => Hash::make('secret-password')]);

    $this->post('http://admin.birdcar.test/login', ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertSessionHasErrors('email');
});

test('logout clears intended urls and returns to login', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['url.intended' => 'https://evil.example'])
        ->post('http://admin.birdcar.test/logout')
        ->assertRedirect('http://admin.birdcar.test/login')
        ->assertSessionMissing('url.intended');
});

test('admin url preserves nondefault scheme and port for auth fallbacks', function (): void {
    config(['admin.url' => 'https://admin.birdcar.test:8443']);
    $user = User::factory()->create(['password' => Hash::make('secret-password')]);
    $user->assignRole(AdminRole::Access->value);

    $this->withSession(['url.intended' => 'https://admin.birdcar.test:8443/publishing'])
        ->post('https://admin.birdcar.test:8443/login', ['email' => $user->email, 'password' => 'secret-password'])
        ->assertRedirect('https://admin.birdcar.test:8443/publishing');

    $this->actingAs($user)
        ->post('https://admin.birdcar.test:8443/logout')
        ->assertRedirect('https://admin.birdcar.test:8443/login');
});

test('two factor challenge route is registered and guarded by Fortify state', function (): void {
    $this->get('http://admin.birdcar.test/two-factor-challenge')
        ->assertRedirect('/login');
});

test('password reset success redirects browsers to the admin login and shows status', function (): void {
    $user = User::factory()->create(['password' => Hash::make('old-password')]);
    $token = Password::broker('users')->createToken($user);

    $this->post('http://admin.birdcar.test/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ])->assertRedirect('http://admin.birdcar.test/login')
        ->assertSessionHas('status');

    auth()->logout();
    $this->withSession(['status' => trans(Password::PASSWORD_RESET)])
        ->get('http://admin.birdcar.test/login')
        ->assertSee(trans(Password::PASSWORD_RESET));
});

test('password reset success keeps Fortify JSON response behavior', function (): void {
    $user = User::factory()->create(['password' => Hash::make('old-password')]);
    $token = Password::broker('users')->createToken($user);

    $this->postJson('http://admin.birdcar.test/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ])->assertOk()
        ->assertJson(['message' => trans(Password::PASSWORD_RESET)]);
});
