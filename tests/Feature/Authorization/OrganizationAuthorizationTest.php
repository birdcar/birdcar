<?php

use App\Authorization\Organizations\Permission as OrganizationPermission;
use App\Authorization\Organizations\Role as OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->artisan('authorization:sync')->assertSuccessful();
});

test('users without memberships are denied tenant abilities', function (): void {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    expect($user->can('view', $organization))->toBeFalse()
        ->and($user->can('update', $organization))->toBeFalse();
});

test('memberships without roles are denied tenant abilities', function (): void {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
    ]);

    expect($user->can('view', $organization))->toBeFalse()
        ->and($user->can('update', $organization))->toBeFalse();
});

test('viewer and editor roles grant only their mapped tenant permissions', function (): void {
    $organization = Organization::factory()->create();
    $viewer = User::factory()->create();
    $editor = User::factory()->create();
    $both = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $viewer->id,
    ])->assignRole(OrganizationRole::Viewer->value);

    OrganizationMembership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $editor->id,
    ])->assignRole(OrganizationRole::Editor->value);

    OrganizationMembership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $both->id,
    ])->assignRole(OrganizationRole::Viewer->value, OrganizationRole::Editor->value);

    expect($viewer->can('view', $organization))->toBeTrue()
        ->and($viewer->can('update', $organization))->toBeFalse()
        ->and($editor->can('view', $organization))->toBeFalse()
        ->and($editor->can('update', $organization))->toBeTrue()
        ->and($both->can('view', $organization))->toBeTrue()
        ->and($both->can('update', $organization))->toBeTrue();
});

test('another organization membership grants nothing to the target tenant', function (): void {
    $user = User::factory()->create();
    $targetOrganization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $otherOrganization->id,
        'user_id' => $user->id,
    ])->assignRole(OrganizationRole::Viewer->value, OrganizationRole::Editor->value);

    expect($user->can('view', $targetOrganization))->toBeFalse()
        ->and($user->can('update', $targetOrganization))->toBeFalse();
});

test('global user organization roles do not bypass membership policies', function (): void {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    $user->assignRole(OrganizationRole::Viewer->value);

    expect($user->hasPermissionTo(OrganizationPermission::View))->toBeTrue()
        ->and($user->can('view', $organization))->toBeFalse();
});

test('unfinished organization policy abilities remain denied', function (): void {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
    ])->assignRole(OrganizationRole::Viewer->value, OrganizationRole::Editor->value);

    expect($user->can('viewAny', Organization::class))->toBeFalse()
        ->and($user->can('create', Organization::class))->toBeFalse()
        ->and($user->can('delete', $organization))->toBeFalse()
        ->and($user->can('restore', $organization))->toBeFalse()
        ->and($user->can('forceDelete', $organization))->toBeFalse();
});
