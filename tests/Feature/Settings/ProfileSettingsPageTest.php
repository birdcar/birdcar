<?php

use App\Authorization\Admin\Role as AdminRole;
use App\Models\User;
use Livewire\Livewire;
use PHPUnit\Framework\Assert;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
});

function profileSettingsUser(): User
{
    $user = User::factory()->create(['name' => 'Nick Cannariato', 'email' => 'nick@birdcar.dev', 'password' => 'correct-horse-battery']);
    $user->assignRole(AdminRole::Access->value);

    return $user->fresh() ?? Assert::fail('The user was not persisted.');
}

test('saving a new name persists it without asking for a password', function (): void {
    $user = profileSettingsUser();

    Livewire::actingAs($user)->test('admin.settings.account.profile')
        ->assertSet('name', 'Nick Cannariato')
        ->set('name', '  Nick C.  ')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('name', 'Nick C.')
        ->assertDispatched('settings-saved', group: 'profile');

    expect($user->fresh()?->name)->toBe('Nick C.');
});

test('changing the email needs the current password', function (string $password, string $message): void {
    $user = profileSettingsUser();

    Livewire::actingAs($user)->test('admin.settings.account.profile')
        ->set('email', 'owner@birdcar.dev')
        ->set('currentPassword', $password)
        ->call('save')
        ->assertHasErrors('currentPassword')
        ->assertSee($message)
        ->assertNotDispatched('settings-saved');

    expect($user->fresh()?->email)->toBe('nick@birdcar.dev');
})->with([
    'missing password' => ['', 'Enter your current password to change your email.'],
    'wrong password' => ['not-the-password', "That password isn't right."],
]);

test('the current password confirms an email change', function (): void {
    $user = profileSettingsUser();

    Livewire::actingAs($user)->test('admin.settings.account.profile')
        ->set('email', 'owner@birdcar.dev')
        ->set('currentPassword', 'correct-horse-battery')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('currentPassword', '');

    expect($user->fresh()?->email)->toBe('owner@birdcar.dev');
});

test('an email another account uses is rejected', function (): void {
    User::factory()->create(['email' => 'taken@birdcar.dev']);
    $user = profileSettingsUser();

    Livewire::actingAs($user)->test('admin.settings.account.profile')
        ->set('email', 'taken@birdcar.dev')
        ->set('currentPassword', 'correct-horse-battery')
        ->call('save')
        ->assertHasErrors(['email' => 'unique'])
        ->assertSee('Fix 1 field to save');

    expect($user->fresh()?->email)->toBe('nick@birdcar.dev');
});

test('discarding restores the saved profile and clears errors', function (): void {
    Livewire::actingAs(profileSettingsUser())->test('admin.settings.account.profile')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors('name')
        ->call('discard')
        ->assertHasNoErrors()
        ->assertSet('name', 'Nick Cannariato');
});

test('a saved name renders escaped in the shell and the form', function (): void {
    $user = profileSettingsUser();
    $user->forceFill(['name' => '<script>alert("name")</script>'])->save();

    $this->actingAs($user)
        ->get('http://admin.birdcar.test/settings/account/profile')
        ->assertOk()
        ->assertDontSee('<script>alert("name")</script>', false)
        ->assertSee('&lt;script&gt;', false);
});
