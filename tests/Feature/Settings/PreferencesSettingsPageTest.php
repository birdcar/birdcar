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

function preferencesSettingsUser(?string $timezone = null): User
{
    $user = User::factory()->create(['timezone' => $timezone]);
    $user->assignRole(AdminRole::Access->value);

    return $user->fresh() ?? Assert::fail('The user was not persisted.');
}

test('saving a timezone stores it on the account', function (): void {
    $user = preferencesSettingsUser();

    Livewire::actingAs($user)->test('admin.settings.account.preferences')
        ->assertSet('timezone', '')
        ->set('timezone', 'America/Chicago')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('settings-saved', group: 'time');

    expect($user->fresh()?->timezone)->toBe('America/Chicago');
});

test('choosing the admin default clears a saved timezone', function (): void {
    $user = preferencesSettingsUser('Europe/London');

    Livewire::actingAs($user)->test('admin.settings.account.preferences')
        ->assertSet('timezone', 'Europe/London')
        ->set('timezone', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()?->timezone)->toBeNull();
});

test('a timezone outside the list is rejected', function (): void {
    $user = preferencesSettingsUser('Europe/London');

    Livewire::actingAs($user)->test('admin.settings.account.preferences')
        ->set('timezone', 'Mars/Olympus_Mons')
        ->call('save')
        ->assertHasErrors('timezone')
        ->assertSee('Choose a timezone from the list.')
        ->assertSee('Fix 1 field to save');

    expect($user->fresh()?->timezone)->toBe('Europe/London');
});

test('appearance is offered per device without a saved account value', function (): void {
    $this->actingAs(preferencesSettingsUser())
        ->get('http://admin.birdcar.test/settings/account/preferences')
        ->assertOk()
        ->assertSee('Applies right away, on this device only.')
        ->assertSeeHtml('x-model="$flux.appearance"');
});
