<?php

use App\Authorization\Admin\Role as AdminRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Passkey;
use Livewire\Livewire;
use PHPUnit\Framework\Assert;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
});

function securitySettingsUser(): User
{
    $user = User::factory()->create(['email' => 'nick@birdcar.dev', 'password' => 'correct-horse-battery']);
    $user->assignRole(AdminRole::Access->value);

    return $user->fresh() ?? Assert::fail('The user was not persisted.');
}

function securityCurrentCode(User $user): string
{
    $secret = $user->fresh()->two_factor_secret ?? Assert::fail('Two-factor setup has not started.');

    return app(Google2FA::class)->getCurrentOtp(Fortify::currentEncrypter()->decrypt($secret));
}

function securityPasskey(User $user, string $name): Passkey
{
    return $user->passkeys()->create(['name' => $name, 'credential_id' => 'credential-'.$name.'-'.$user->id, 'credential' => ['aaguid' => '00000000-0000-0000-0000-000000000000']]);
}

test('a wrong current password leaves the password unchanged', function (): void {
    $user = securitySettingsUser();

    Livewire::actingAs($user)->test('admin.settings.account.security')
        ->set('currentPassword', 'not-the-password')
        ->set('password', 'a-new-long-password')
        ->set('passwordConfirmation', 'a-new-long-password')
        ->call('updatePassword')
        ->assertHasErrors('current_password')
        ->assertSee('The provided password does not match your current password.');

    expect(Hash::check('correct-horse-battery', $user->fresh()->password ?? ''))->toBeTrue();
});

test('updating the password stores the new one and clears the form', function (): void {
    $user = securitySettingsUser();

    Livewire::actingAs($user)->test('admin.settings.account.security')
        ->set('currentPassword', 'correct-horse-battery')
        ->set('password', 'a-new-long-password')
        ->set('passwordConfirmation', 'a-new-long-password')
        ->call('updatePassword')
        ->assertHasNoErrors()
        ->assertSet('password', '')
        ->assertSee('Password updated.');

    expect(Hash::check('a-new-long-password', $user->fresh()->password ?? ''))->toBeTrue();
});

test('setting up two-factor asks for the password before creating a secret', function (): void {
    $user = securitySettingsUser();

    $page = Livewire::actingAs($user)->test('admin.settings.account.security')
        ->call('enableTwoFactor')
        ->assertSet('confirmingPassword', true);

    expect($user->fresh()?->two_factor_secret)->toBeNull();

    $page->set('confirmablePassword', 'not-the-password')
        ->call('confirmPassword')
        ->assertHasErrors('confirmablePassword')
        ->assertSee("That password isn't right.");

    expect($user->fresh()?->two_factor_secret)->toBeNull();

    $page->set('confirmablePassword', 'correct-horse-battery')
        ->call('confirmPassword')
        ->assertHasNoErrors()
        ->assertSet('confirmingPassword', false)
        ->assertSeeHtml('data-two-factor-state="pending"');

    expect($user->fresh()?->two_factor_secret)->not->toBeNull()
        ->and($user->fresh()?->two_factor_confirmed_at)->toBeNull();
});

test('a recent password confirmation skips the dialog', function (): void {
    session()->passwordConfirmed();

    Livewire::actingAs(securitySettingsUser())->test('admin.settings.account.security')
        ->call('enableTwoFactor')
        ->assertSet('confirmingPassword', false)
        ->assertSeeHtml('data-two-factor-state="pending"');
});

test('an authenticator code finishes setup and shows recovery codes once', function (): void {
    session()->passwordConfirmed();
    $user = securitySettingsUser();
    $page = Livewire::actingAs($user)->test('admin.settings.account.security')->call('enableTwoFactor');

    $page->set('code', '000000')
        ->call('confirmTwoFactor')
        ->assertHasErrors('code')
        ->assertSee("That code didn't match.");

    expect($user->fresh()?->two_factor_confirmed_at)->toBeNull();

    $page->set('code', securityCurrentCode($user))
        ->call('confirmTwoFactor')
        ->assertHasNoErrors()
        ->assertSeeHtml('data-two-factor-state="enabled"')
        ->assertSeeHtml('data-recovery-codes')
        ->assertSeeHtml('x-data="recoveryCodes(')
        ->assertSeeHtml('x-on:click="copy"')
        ->assertSeeHtml('x-on:click="download"');

    $codes = $user->fresh()?->recoveryCodes() ?? [];
    expect($user->fresh()?->two_factor_confirmed_at)->not->toBeNull()
        ->and($codes)->toHaveCount(8);
    $page->assertSee($codes[0]);

    Livewire::actingAs($user->fresh())->test('admin.settings.account.security')
        ->assertDontSee($codes[0]);
});

test('canceling setup removes the unconfirmed secret', function (): void {
    session()->passwordConfirmed();
    $user = securitySettingsUser();

    Livewire::actingAs($user)->test('admin.settings.account.security')
        ->call('enableTwoFactor')
        ->call('cancelTwoFactorSetup')
        ->assertSeeHtml('data-two-factor-state="disabled"');

    expect($user->fresh()?->two_factor_secret)->toBeNull();
});

test('signing in asks for a code once two-factor is on', function (): void {
    session()->passwordConfirmed();
    $user = securitySettingsUser();
    Livewire::actingAs($user)->test('admin.settings.account.security')
        ->call('enableTwoFactor')
        ->set('code', securityCurrentCode($user))
        ->call('confirmTwoFactor');
    auth()->logout();
    session()->flush();

    $this->post('http://admin.birdcar.test/login', ['email' => 'nick@birdcar.dev', 'password' => 'correct-horse-battery'])
        ->assertRedirect('http://admin.birdcar.test/two-factor-challenge');

    $this->assertGuest();
});

test('recovery codes stay hidden until the password is confirmed', function (): void {
    session()->passwordConfirmed();
    $user = securitySettingsUser();
    Livewire::actingAs($user)->test('admin.settings.account.security')
        ->call('enableTwoFactor')
        ->set('code', securityCurrentCode($user))
        ->call('confirmTwoFactor');
    session()->forget('auth.password_confirmed_at');
    $codes = $user->fresh()?->recoveryCodes() ?? [];

    Livewire::actingAs($user->fresh())->test('admin.settings.account.security')
        ->call('showRecoveryCodes')
        ->assertSet('confirmingPassword', true)
        ->assertDontSee($codes[0])
        ->set('confirmablePassword', 'correct-horse-battery')
        ->call('confirmPassword')
        ->assertSee($codes[0]);
});

test('making new recovery codes replaces the old ones', function (): void {
    session()->passwordConfirmed();
    $user = securitySettingsUser();
    $page = Livewire::actingAs($user)->test('admin.settings.account.security')
        ->call('enableTwoFactor')
        ->set('code', securityCurrentCode($user))
        ->call('confirmTwoFactor');
    $before = $user->fresh()?->recoveryCodes() ?? [];

    $page->call('regenerateRecoveryCodes');

    expect($user->fresh()?->recoveryCodes())->not->toBe($before)->toHaveCount(8);
});

test('turning two-factor off needs the password and removes the secret', function (): void {
    session()->passwordConfirmed();
    $user = securitySettingsUser();
    Livewire::actingAs($user)->test('admin.settings.account.security')
        ->call('enableTwoFactor')
        ->set('code', securityCurrentCode($user))
        ->call('confirmTwoFactor');
    session()->forget('auth.password_confirmed_at');

    $page = Livewire::actingAs($user->fresh())->test('admin.settings.account.security')
        ->call('disableTwoFactor')
        ->assertSet('confirmingPassword', true);

    expect($user->fresh()?->hasEnabledTwoFactorAuthentication())->toBeTrue();

    $page->set('confirmablePassword', 'correct-horse-battery')->call('confirmPassword');

    expect($user->fresh()?->two_factor_secret)->toBeNull()
        ->and($user->fresh()?->hasEnabledTwoFactorAuthentication())->toBeFalse();
});

test('password confirmation locks after five wrong attempts', function (): void {
    $page = Livewire::actingAs(securitySettingsUser())->test('admin.settings.account.security')->call('enableTwoFactor');

    foreach (range(1, 5) as $attempt) {
        $page->set('confirmablePassword', 'wrong-'.$attempt)->call('confirmPassword')->assertSee("That password isn't right.");
    }

    $page->set('confirmablePassword', 'correct-horse-battery')
        ->call('confirmPassword')
        ->assertSee('Too many attempts.');
});

test('adding a passkey needs a name and a confirmed password before registration options are issued', function (): void {
    $page = Livewire::actingAs(securitySettingsUser())->test('admin.settings.account.security')
        ->call('beginPasskeyRegistration')
        ->assertHasErrors('passkeyName')
        ->assertSee('Name this passkey so you can recognize it later.')
        ->set('passkeyName', 'MacBook')
        ->call('beginPasskeyRegistration')
        ->assertSet('confirmingPassword', true)
        ->assertReturned(null);

    expect(session()->has('passkey.registration_options'))->toBeFalse();

    $page->set('confirmablePassword', 'correct-horse-battery')
        ->call('confirmPassword')
        ->assertDispatched('settings-passkey-confirmed')
        ->call('beginPasskeyRegistration')
        ->assertReturned(fn (mixed $returned): bool => is_array($returned) && isset($returned['options']['challenge'], $returned['options']['rp']['id']));

    expect(session()->has('passkey.registration_options'))->toBeTrue();
});

test('a credential the server cannot verify stores nothing', function (): void {
    session()->passwordConfirmed();
    $user = securitySettingsUser();

    Livewire::actingAs($user)->test('admin.settings.account.security')
        ->set('passkeyName', 'MacBook')
        ->call('beginPasskeyRegistration')
        ->call('finishPasskeyRegistration', ['id' => 'forged', 'rawId' => 'forged', 'type' => 'public-key', 'response' => []])
        ->assertSee("Couldn't add that passkey. Choose Add passkey to try again.");

    expect($user->passkeys()->count())->toBe(0);
});

test('finishing a passkey without a pending registration asks to start again', function (): void {
    session()->passwordConfirmed();

    Livewire::actingAs(securitySettingsUser())->test('admin.settings.account.security')
        ->set('passkeyName', 'MacBook')
        ->call('finishPasskeyRegistration', ['id' => 'x'])
        ->assertSee('Passkey setup expired. Choose Add passkey to try again.');
});

test('removing a passkey needs the password and deletes only that passkey', function (): void {
    $user = securitySettingsUser();
    $keep = securityPasskey($user, 'iPhone');
    $remove = securityPasskey($user, 'MacBook');

    $page = Livewire::actingAs($user)->test('admin.settings.account.security')
        ->assertSee('MacBook')
        ->call('confirmPasskeyRemoval', $remove->id)
        ->assertSet('confirmingPasskeyRemoval', true)
        ->call('removePasskey')
        ->assertSet('confirmingPassword', true);

    expect(Passkey::query()->whereKey($remove->id)->exists())->toBeTrue();

    $page->set('confirmablePassword', 'correct-horse-battery')
        ->call('confirmPassword')
        ->assertSee('MacBook was removed.');

    expect(Passkey::query()->whereKey($remove->id)->exists())->toBeFalse()
        ->and(Passkey::query()->whereKey($keep->id)->exists())->toBeTrue();
});

test('another account\'s passkey cannot be targeted for removal', function (): void {
    $other = securityPasskey(securitySettingsUserWithEmail('other@birdcar.dev'), 'Other laptop');
    session()->passwordConfirmed();

    Livewire::actingAs(securitySettingsUser())->test('admin.settings.account.security')
        ->call('confirmPasskeyRemoval', $other->id)
        ->assertNotFound();

    expect(Passkey::query()->whereKey($other->id)->exists())->toBeTrue();
});

test('losing admin access blocks account changes on an open page', function (): void {
    $user = securitySettingsUser();
    $page = Livewire::actingAs($user)->test('admin.settings.account.security')
        ->set('currentPassword', 'correct-horse-battery')
        ->set('password', 'a-new-long-password')
        ->set('passwordConfirmation', 'a-new-long-password');

    $user->removeRole(AdminRole::Access->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $page->call('updatePassword')->assertForbidden();

    expect(Hash::check('correct-horse-battery', $user->fresh()->password ?? ''))->toBeTrue();
});

function securitySettingsUserWithEmail(string $email): User
{
    $user = User::factory()->create(['email' => $email]);
    $user->assignRole(AdminRole::Access->value);

    return $user->fresh() ?? Assert::fail('The user was not persisted.');
}
