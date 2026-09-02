<?php

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

test('persists a membership with first class identity on the conventional table', function (): void {
    $membership = OrganizationMembership::factory()->create();

    expect($membership->id)->not->toBeNull();

    $this->assertDatabaseHas('organization_memberships', [
        'id' => $membership->id,
        'organization_id' => $membership->organization_id,
        'user_id' => $membership->user_id,
    ]);
});

test('duplicate user organization pairs are rejected', function (): void {
    $membership = OrganizationMembership::factory()->create();

    expect(fn () => OrganizationMembership::create([
        'organization_id' => $membership->organization_id,
        'user_id' => $membership->user_id,
    ]))->toThrow(QueryException::class);
});

test('users may join multiple organizations and organizations may contain multiple users', function (): void {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $otherUser = User::factory()->create();

    OrganizationMembership::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id]);
    OrganizationMembership::factory()->create(['organization_id' => $otherOrganization->id, 'user_id' => $user->id]);
    OrganizationMembership::factory()->create(['organization_id' => $organization->id, 'user_id' => $otherUser->id]);

    expect($user->organizationMemberships()->count())->toBe(2)
        ->and($organization->memberships()->count())->toBe(2);
});

test('relationships resolve users organizations and inverse collections', function (): void {
    $membership = OrganizationMembership::factory()->create();

    expect($membership->user->is($membership->user()->first()))->toBeTrue()
        ->and($membership->organization->is($membership->organization()->first()))->toBeTrue()
        ->and($membership->user->organizationMemberships->contains($membership))->toBeTrue()
        ->and($membership->organization->memberships->contains($membership))->toBeTrue();
});

test('membership lookup resolves matching rows and returns null for non members', function (): void {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $unrelatedOrganization = Organization::factory()->create();

    $membership = OrganizationMembership::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id]);
    $otherMembership = OrganizationMembership::factory()->create(['organization_id' => $otherOrganization->id, 'user_id' => $user->id]);

    expect($user->membershipFor($organization)?->is($membership))->toBeTrue()
        ->and($user->membershipFor($otherOrganization)?->is($otherMembership))->toBeTrue()
        ->and($user->membershipFor($unrelatedOrganization))->toBeNull();
});

test('membership accepts web guard roles and deleting it removes role assignments', function (): void {
    $role = Role::create(['name' => 'tenant.viewer', 'guard_name' => 'web']);
    $membership = OrganizationMembership::factory()->create();

    $membership->assignRole($role);

    $this->assertDatabaseHas('model_has_roles', [
        'role_id' => $role->id,
        'model_type' => $membership->getMorphClass(),
        'model_id' => $membership->id,
    ]);

    $membership->delete();

    $this->assertDatabaseMissing('model_has_roles', [
        'role_id' => $role->id,
        'model_type' => $membership->getMorphClass(),
        'model_id' => $membership->id,
    ]);
});

test('deleting a user cleans membership authorization assignments in a fixed number of queries', function (): void {
    $role = Role::create(['name' => 'tenant.member', 'guard_name' => 'web']);
    $permission = Permission::create(['name' => 'tenant.view', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $memberships = OrganizationMembership::factory()
        ->count(101)
        ->create(['user_id' => $user->id]);

    $memberships->each->assignRole($role);
    $memberships->first()->givePermissionTo($permission);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $user->deleteOrFail();
    $assignmentDeleteQueryCount = membershipAssignmentDeleteQueryCount(DB::getQueryLog());
    DB::disableQueryLog();

    expect(OrganizationMembership::count())->toBe(0)
        ->and(DB::table('model_has_roles')->where('model_type', OrganizationMembership::class)->count())->toBe(0)
        ->and(DB::table('model_has_permissions')->where('model_type', OrganizationMembership::class)->count())->toBe(0)
        ->and($assignmentDeleteQueryCount)->toBe(2);
});

test('deleting an organization cleans membership authorization assignments in a fixed number of queries', function (): void {
    $role = Role::create(['name' => 'tenant.editor', 'guard_name' => 'web']);
    $permission = Permission::create(['name' => 'tenant.update', 'guard_name' => 'web']);
    $organization = Organization::factory()->create();
    $memberships = OrganizationMembership::factory()
        ->count(101)
        ->create(['organization_id' => $organization->id]);

    $memberships->each->assignRole($role);
    $memberships->first()->givePermissionTo($permission);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $organization->deleteOrFail();
    $assignmentDeleteQueryCount = membershipAssignmentDeleteQueryCount(DB::getQueryLog());
    DB::disableQueryLog();

    expect(OrganizationMembership::count())->toBe(0)
        ->and(DB::table('model_has_roles')->where('model_type', OrganizationMembership::class)->count())->toBe(0)
        ->and(DB::table('model_has_permissions')->where('model_type', OrganizationMembership::class)->count())->toBe(0)
        ->and($assignmentDeleteQueryCount)->toBe(2);
});

test('failed user deletion rolls back the parent membership and all authorization assignments', function (): void {
    $role = Role::create(['name' => 'tenant.rollback', 'guard_name' => 'web']);
    $permission = Permission::create(['name' => 'tenant.rollback', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $membership = OrganizationMembership::factory()->create(['user_id' => $user->id]);

    $user->assignRole($role);
    $membership->assignRole($role);
    $membership->givePermissionTo($permission);

    $shouldFail = true;

    User::deleted(function (User $deletedUser) use ($user, &$shouldFail): void {
        if ($shouldFail && $deletedUser->is($user)) {
            $shouldFail = false;

            throw new RuntimeException('Simulated post-delete failure.');
        }
    });

    expect(fn () => $user->deleteOrFail())
        ->toThrow(RuntimeException::class, 'Simulated post-delete failure.');

    $this->assertDatabaseHas('users', ['id' => $user->id]);
    $this->assertDatabaseHas('organization_memberships', ['id' => $membership->id]);
    $this->assertDatabaseHas('model_has_roles', [
        'role_id' => $role->id,
        'model_type' => $user->getMorphClass(),
        'model_id' => $user->id,
    ]);
    $this->assertDatabaseHas('model_has_roles', [
        'role_id' => $role->id,
        'model_type' => $membership->getMorphClass(),
        'model_id' => $membership->id,
    ]);
    $this->assertDatabaseHas('model_has_permissions', [
        'permission_id' => $permission->id,
        'model_type' => $membership->getMorphClass(),
        'model_id' => $membership->id,
    ]);
});

/**
 * @param  list<array{query: string, bindings: array<int, mixed>, time: float|null}>  $queries
 */
function membershipAssignmentDeleteQueryCount(array $queries): int
{
    $morphClass = (new OrganizationMembership)->getMorphClass();

    return collect($queries)
        ->filter(fn (array $query): bool => str_starts_with(strtolower(ltrim($query['query'])), 'delete')
            && in_array($morphClass, $query['bindings'], true))
        ->count();
}
