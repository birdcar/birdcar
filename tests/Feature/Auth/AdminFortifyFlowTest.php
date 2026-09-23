<?php

use App\Authorization\Admin\Role as AdminRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
    config(['admin.url' => 'http://admin.birdcar.test']);
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
