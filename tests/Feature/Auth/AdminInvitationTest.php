<?php

use App\Authorization\Admin\Permission as AdminPermission;
use App\Authorization\Admin\Role as AdminRole;
use App\Authorization\Organizations\Role as OrganizationRole;
use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\User;
use App\Notifications\AdminInvitation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();

    config([
        'admin.url' => 'http://admin.birdcar.test',
        'admin.host' => 'admin.birdcar.test',
        'mail.default' => 'smtp',
        'mail.mailers.smtp.transport' => 'smtp',
        'mail.mailers.smtp.host' => '127.0.0.1',
        'mail.mailers.smtp.port' => 2525,
    ]);
});

test('inviting a new admin provisions root roles and sends an admin-origin setup notification', function (): void {
    Notification::fake();

    $exitCode = Artisan::call('admin:invite', [
        'email' => 'Root.Admin@Example.COM',
        '--name' => 'Root Admin',
        '--no-interaction' => true,
    ]);

    $user = User::query()->where('email', 'root.admin@example.com')->firstOrFail();
    $capturedUrl = null;
    Notification::assertSentTo($user, AdminInvitation::class, function (AdminInvitation $notification) use ($user, &$capturedUrl): bool {
        $message = $notification->toMail($user);
        $capturedUrl = $message->actionUrl;

        expect($message->introLines)->toContain('An operator invited this account to the root Admin role bundle, including Admin access and publishing author capabilities.');

        return is_string($capturedUrl)
            && str_starts_with($capturedUrl, 'http://admin.birdcar.test/reset-password/')
            && str_contains($capturedUrl, 'email=root.admin%40example.com');
    });

    expect($exitCode)->toBe(0)
        ->and($user->name)->toBe('Root Admin')
        ->and($user->hasRole(AdminRole::Access->value))->toBeTrue()
        ->and($user->hasRole(PublishingRole::Author->value))->toBeTrue()
        ->and($user->can(AdminPermission::View->value))->toBeTrue()
        ->and($user->can(PublishingPermission::Write->value))->toBeTrue()
        ->and($user->getDirectPermissions()->count())->toBe(0)
        ->and(Artisan::output())->not->toContain($capturedUrl ?? 'missing-url');
});

test('inviting an existing admin preserves credentials profile two factor and unrelated roles', function (): void {
    Notification::fake();
    $password = Hash::make('current-password');
    $user = User::factory()->create([
        'name' => 'Existing Person',
        'email' => 'existing@example.com',
        'password' => $password,
    ]);
    $user->forceFill([
        'two_factor_secret' => 'encrypted-secret',
        'two_factor_recovery_codes' => 'encrypted-codes',
        'two_factor_confirmed_at' => now(),
    ])->save();
    $user->assignRole(OrganizationRole::Viewer->value);

    $exitCode = Artisan::call('admin:invite', [
        'email' => 'EXISTING@example.com',
        '--name' => 'Ignored Name',
        '--no-interaction' => true,
    ]);

    $user->refresh();
    Notification::assertSentTo($user, AdminInvitation::class);
    expect($exitCode)->toBe(0)
        ->and(User::query()->whereRaw('lower(email) = ?', ['existing@example.com'])->count())->toBe(1)
        ->and($user->name)->toBe('Existing Person')
        ->and($user->password)->toBe($password)
        ->and($user->two_factor_secret)->toBe('encrypted-secret')
        ->and($user->two_factor_recovery_codes)->toBe('encrypted-codes')
        ->and($user->two_factor_confirmed_at)->not->toBeNull()
        ->and($user->hasRole(OrganizationRole::Viewer->value))->toBeTrue()
        ->and($user->hasRole(AdminRole::Access->value))->toBeTrue()
        ->and($user->hasRole(PublishingRole::Author->value))->toBeTrue();
});

test('missing bootstrap roles fail before provisioning an account', function (): void {
    Notification::fake();
    RoleModel::query()->where('name', AdminRole::Access->value)->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $exitCode = Artisan::call('admin:invite', [
        'email' => 'missing-roles@example.com',
        '--no-interaction' => true,
    ]);

    Notification::assertNothingSent();
    expect($exitCode)->toBe(1)
        ->and(User::query()->where('email', 'missing-roles@example.com')->exists())->toBeFalse()
        ->and(Artisan::output())->toContain('authorization:sync');
});

test('direct unsafe mail transports fail before provisioning an account', function (): void {
    Notification::fake();

    foreach (['array', 'log'] as $transport) {
        config([
            'mail.default' => $transport,
            "mail.mailers.{$transport}.transport" => $transport,
        ]);

        $exitCode = Artisan::call('admin:invite', [
            'email' => "unsafe-{$transport}@example.com",
            '--no-interaction' => true,
        ]);

        expect($exitCode)->toBe(1)
            ->and(User::query()->where('email', "unsafe-{$transport}@example.com")->exists())->toBeFalse()
            ->and(Artisan::output())->toContain("unsafe [{$transport}] transport");
    }

    Notification::assertNothingSent();
});

test('nested unsafe failover and round-robin mail transports fail before provisioning an account', function (): void {
    Notification::fake();

    $cases = [
        'failover' => [
            'mail.default' => 'outer_failover',
            'mail.mailers.outer_failover' => ['transport' => 'failover', 'mailers' => ['smtp', 'inner_roundrobin']],
            'mail.mailers.inner_roundrobin' => ['transport' => 'roundrobin', 'mailers' => ['array']],
        ],
        'roundrobin' => [
            'mail.default' => 'outer_roundrobin',
            'mail.mailers.outer_roundrobin' => ['transport' => 'roundrobin', 'mailers' => ['smtp', 'inner_failover']],
            'mail.mailers.inner_failover' => ['transport' => 'failover', 'mailers' => ['log']],
        ],
    ];

    foreach ($cases as $label => $mailConfig) {
        config($mailConfig);

        $exitCode = Artisan::call('admin:invite', [
            'email' => "nested-unsafe-{$label}@example.com",
            '--no-interaction' => true,
        ]);

        expect($exitCode)->toBe(1)
            ->and(User::query()->where('email', "nested-unsafe-{$label}@example.com")->exists())->toBeFalse()
            ->and(Artisan::output())->toContain('unsafe');
    }

    Notification::assertNothingSent();
});

test('invalid admin origins fail before provisioning an account', function (): void {
    Notification::fake();

    $origins = [
        'ftp://admin.birdcar.test',
        'https://operator:secret@admin.birdcar.test',
        'https://admin.birdcar.test/reset',
        'https://admin.birdcar.test?token=leak',
        'https://admin.birdcar.test#fragment',
    ];

    foreach ($origins as $index => $origin) {
        config(['admin.url' => $origin]);

        $exitCode = Artisan::call('admin:invite', [
            'email' => "invalid-origin-{$index}@example.com",
            '--no-interaction' => true,
        ]);

        expect($exitCode)->toBe(1)
            ->and(User::query()->where('email', "invalid-origin-{$index}@example.com")->exists())->toBeFalse();
    }

    Notification::assertNothingSent();
});

test('delivery failure happens after safe provisioning and a later retry sends one invitation', function (): void {
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.transport' => 'smtp',
        'mail.mailers.smtp.host' => '127.0.0.1',
        'mail.mailers.smtp.port' => 65000,
        'mail.mailers.smtp.timeout' => 1,
    ]);

    $firstExitCode = Artisan::call('admin:invite', [
        'email' => 'delivery-failure@example.com',
        '--name' => 'Delivery Failure',
        '--no-interaction' => true,
    ]);

    $user = User::query()->where('email', 'delivery-failure@example.com')->firstOrFail();

    expect($firstExitCode)->toBe(1)
        ->and($user->hasRole(AdminRole::Access->value))->toBeTrue()
        ->and($user->hasRole(PublishingRole::Author->value))->toBeTrue()
        ->and(Artisan::output())->toContain('Invitation delivery failed after account provisioning')
        ->and(Artisan::output())->not->toContain('127.0.0.1')
        ->and(Artisan::output())->not->toContain('65000')
        ->and(Artisan::output())->not->toContain('/reset-password/');

    $this->travel(61)->seconds();
    Notification::fake();

    $retryExitCode = Artisan::call('admin:invite', [
        'email' => 'DELIVERY-FAILURE@example.com',
        '--no-interaction' => true,
    ]);

    Notification::assertSentToTimes($user, AdminInvitation::class, 1);
    expect($retryExitCode)->toBe(0)
        ->and(User::query()->whereRaw('lower(email) = ?', ['delivery-failure@example.com'])->count())->toBe(1);
});

test('broker throttling is reported without sending another invitation', function (): void {
    Notification::fake();

    $firstExitCode = Artisan::call('admin:invite', [
        'email' => 'throttled@example.com',
        '--no-interaction' => true,
    ]);
    $user = User::query()->where('email', 'throttled@example.com')->firstOrFail();

    $secondExitCode = Artisan::call('admin:invite', [
        'email' => 'THROTTLED@example.com',
        '--no-interaction' => true,
    ]);

    Notification::assertSentToTimes($user, AdminInvitation::class, 1);
    expect($firstExitCode)->toBe(0)
        ->and($secondExitCode)->toBe(1)
        ->and(User::query()->whereRaw('lower(email) = ?', ['throttled@example.com'])->count())->toBe(1)
        ->and(Artisan::output())->toContain('throttled');
});

test('the emailed setup link uses the real broker token and resets the password once', function (): void {
    Notification::fake();

    Artisan::call('admin:invite', [
        'email' => 'setup@example.com',
        '--no-interaction' => true,
    ]);

    $user = User::query()->where('email', 'setup@example.com')->firstOrFail();
    $setupUrl = null;
    Notification::assertSentTo($user, AdminInvitation::class, function (AdminInvitation $notification) use ($user, &$setupUrl): bool {
        $setupUrl = $notification->toMail($user)->actionUrl;

        return is_string($setupUrl);
    });

    $path = parse_url((string) $setupUrl, PHP_URL_PATH);
    $token = basename((string) $path);

    $this->get((string) $setupUrl)
        ->assertOk()
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertSee('Set your Admin password');

    $this->post('http://admin.birdcar.test/reset-password', [
        'token' => $token,
        'email' => 'setup@example.com',
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ])->assertRedirect('http://admin.birdcar.test/login')
        ->assertSessionHas('status');

    expect(Hash::check('new-secret-password', $user->refresh()->password))->toBeTrue();

    auth()->logout();
    $this->post('http://admin.birdcar.test/reset-password', [
        'token' => $token,
        'email' => 'setup@example.com',
        'password' => 'second-secret-password',
        'password_confirmation' => 'second-secret-password',
    ])->assertSessionHasErrors('email');
    expect(Hash::check('second-secret-password', $user->refresh()->password))->toBeFalse();
});

test('expired setup tokens cannot reset credentials', function (): void {
    $oldPassword = Hash::make('old-password');
    $user = User::factory()->create([
        'email' => 'expired-token@example.com',
        'password' => $oldPassword,
    ]);
    $token = Password::broker('users')->createToken($user);
    $expiryMinutes = (int) config('auth.passwords.users.expire', 60);

    DB::table('password_reset_tokens')
        ->where('email', $user->email)
        ->update(['created_at' => now()->subMinutes($expiryMinutes + 1)]);

    $this->post('http://admin.birdcar.test/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ])->assertSessionHasErrors('email');

    expect($user->refresh()->password)->toBe($oldPassword);
});

test('wrong setup email or token cannot reset credentials', function (): void {
    $oldPassword = Hash::make('old-password');
    $user = User::factory()->create([
        'email' => 'wrong-token@example.com',
        'password' => $oldPassword,
    ]);
    $token = Password::broker('users')->createToken($user);

    $this->post('http://admin.birdcar.test/reset-password', [
        'token' => $token,
        'email' => 'other-person@example.com',
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ])->assertSessionHasErrors('email');

    $this->post('http://admin.birdcar.test/reset-password', [
        'token' => 'not-the-real-token',
        'email' => $user->email,
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ])->assertSessionHasErrors('email');

    expect($user->refresh()->password)->toBe($oldPassword);
});

test('invalid setup passwords do not change credentials', function (): void {
    $oldPassword = Hash::make('old-password');
    $user = User::factory()->create([
        'email' => 'invalid-password@example.com',
        'password' => $oldPassword,
    ]);
    $token = Password::broker('users')->createToken($user);

    $this->post('http://admin.birdcar.test/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-secret-password',
        'password_confirmation' => 'different-secret-password',
    ])->assertSessionHasErrors('password');

    expect($user->refresh()->password)->toBe($oldPassword);
});

test('resending an invitation invalidates the older setup token', function (): void {
    Notification::fake();

    Artisan::call('admin:invite', [
        'email' => 'resend@example.com',
        '--no-interaction' => true,
    ]);

    $user = User::query()->where('email', 'resend@example.com')->firstOrFail();
    $firstToken = Notification::sent($user, AdminInvitation::class)->first()->token;

    $this->travel(61)->seconds();

    Artisan::call('admin:invite', [
        'email' => 'resend@example.com',
        '--no-interaction' => true,
    ]);

    $sentInvitations = Notification::sent($user, AdminInvitation::class)->values();
    $secondToken = $sentInvitations->get(1)->token;

    expect($sentInvitations)->toHaveCount(2)
        ->and($secondToken)->not->toBe($firstToken);

    $this->post('http://admin.birdcar.test/reset-password', [
        'token' => $firstToken,
        'email' => $user->email,
        'password' => 'older-token-password',
        'password_confirmation' => 'older-token-password',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('older-token-password', $user->refresh()->password))->toBeFalse();

    $this->post('http://admin.birdcar.test/reset-password', [
        'token' => $secondToken,
        'email' => $user->email,
        'password' => 'resent-token-password',
        'password_confirmation' => 'resent-token-password',
    ])->assertRedirect('http://admin.birdcar.test/login');

    expect(Hash::check('resent-token-password', $user->refresh()->password))->toBeTrue();
});

test('password setup for an existing user preserves confirmed two factor secrets', function (): void {
    Notification::fake();
    $user = User::factory()->create([
        'email' => 'two-factor-reset@example.com',
        'password' => Hash::make('old-password'),
    ]);
    $user->forceFill([
        'two_factor_secret' => 'encrypted-secret',
        'two_factor_recovery_codes' => 'encrypted-codes',
        'two_factor_confirmed_at' => now(),
    ])->save();

    Artisan::call('admin:invite', [
        'email' => 'two-factor-reset@example.com',
        '--no-interaction' => true,
    ]);

    $token = Notification::sent($user, AdminInvitation::class)->first()->token;

    $this->post('http://admin.birdcar.test/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ])->assertRedirect('http://admin.birdcar.test/login');

    $user->refresh();
    expect(Hash::check('new-secret-password', $user->password))->toBeTrue()
        ->and($user->two_factor_secret)->toBe('encrypted-secret')
        ->and($user->two_factor_recovery_codes)->toBe('encrypted-codes')
        ->and($user->two_factor_confirmed_at)->not->toBeNull();
});
