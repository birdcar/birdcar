<?php

use App\Authorization\Admin\Role as AdminRole;
use App\Authorization\Organizations\Role as OrganizationRole;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->artisan('authorization:sync')->assertSuccessful();
});

test('guests cannot reach the admin index', function (): void {
    $this->get('http://admin.birdcar.dev/')
        ->assertRedirect('/login');
});

test('authenticated users without admin permission are forbidden', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('http://admin.birdcar.dev/')
        ->assertForbidden();
});

test('unrelated organization roles do not grant admin access', function (): void {
    $user = User::factory()->create();
    $user->assignRole(OrganizationRole::Viewer->value);

    $this->actingAs($user)
        ->get('http://admin.birdcar.dev/')
        ->assertForbidden();
});

test('admin access role reaches the admin index', function (): void {
    $user = User::factory()->create();
    $user->assignRole(AdminRole::Access->value);

    $this->actingAs($user)
        ->get('http://admin.birdcar.dev/')
        ->assertNoContent();
});
