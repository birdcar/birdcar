<?php

use App\Authorization\Admin\Catalog as AdminCatalog;
use App\Authorization\Contracts\AuthorizationCatalog;
use App\Authorization\Organizations\Catalog as OrganizationsCatalog;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

test('registered catalogs create enum-backed permissions roles and exact mappings', function (): void {
    config()->set('authorization.catalogs', [
        AdminCatalog::class,
        OrganizationsCatalog::class,
    ]);

    $this->artisan('authorization:sync')
        ->expectsOutputToContain('Authorization catalogs synchronized for guard [web].')
        ->expectsOutputToContain('No stale authorization definitions found.')
        ->assertSuccessful();

    $this->assertDatabaseHas('permissions', ['name' => 'admin.view', 'guard_name' => 'web']);
    $this->assertDatabaseHas('permissions', ['name' => 'organizations.view', 'guard_name' => 'web']);
    $this->assertDatabaseHas('permissions', ['name' => 'organizations.update', 'guard_name' => 'web']);
    $this->assertDatabaseHas('roles', ['name' => 'admin.access', 'guard_name' => 'web']);
    $this->assertDatabaseHas('roles', ['name' => 'organizations.viewer', 'guard_name' => 'web']);
    $this->assertDatabaseHas('roles', ['name' => 'organizations.editor', 'guard_name' => 'web']);

    expect(permissionNamesForRole('admin.access'))->toBe(['admin.view'])
        ->and(permissionNamesForRole('organizations.viewer'))->toBe(['organizations.view'])
        ->and(permissionNamesForRole('organizations.editor'))->toBe(['organizations.update']);
});

test('unregistered catalogs are ignored', function (): void {
    config()->set('authorization.catalogs', [SingleCatalog::class]);

    $this->artisan('authorization:sync')->assertSuccessful();

    $this->assertDatabaseHas('permissions', ['name' => 'test.view', 'guard_name' => 'web']);
    $this->assertDatabaseMissing('permissions', ['name' => 'ignored.view', 'guard_name' => 'web']);
    $this->assertDatabaseMissing('roles', ['name' => 'ignored.viewer', 'guard_name' => 'web']);
});

test('idempotent reruns do not duplicate or remap catalog rows', function (): void {
    config()->set('authorization.catalogs', [SingleCatalog::class]);

    $this->artisan('authorization:sync')->assertSuccessful();
    $roleId = Role::findByName('test.viewer', 'web')->id;
    $permissionId = Permission::findByName('test.view', 'web')->id;
    $createdAt = Role::findByName('test.viewer', 'web')->created_at?->toISOString();

    $this->artisan('authorization:sync')->assertSuccessful();

    expect(Role::where('name', 'test.viewer')->where('guard_name', 'web')->count())->toBe(1)
        ->and(Permission::where('name', 'test.view')->where('guard_name', 'web')->count())->toBe(1)
        ->and(Role::findByName('test.viewer', 'web')->id)->toBe($roleId)
        ->and(Permission::findByName('test.view', 'web')->id)->toBe($permissionId)
        ->and(Role::findByName('test.viewer', 'web')->created_at?->toISOString())->toBe($createdAt)
        ->and(permissionNamesForRole('test.viewer'))->toBe(['test.view']);
});

test('normal sync revokes removed role permissions', function (): void {
    config()->set('authorization.catalogs', [WideCatalog::class]);
    $this->artisan('authorization:sync')->assertSuccessful();

    config()->set('authorization.catalogs', [SingleCatalog::class]);

    $this->artisan('authorization:sync')
        ->expectsOutputToContain('permission: test.update')
        ->assertSuccessful();

    expect(permissionNamesForRole('test.viewer'))->toBe(['test.view']);
    $this->assertDatabaseHas('permissions', ['name' => 'test.update', 'guard_name' => 'web']);
});

test('catalog collisions fail before table changes', function (array $catalogs, string $message): void {
    config()->set('authorization.catalogs', $catalogs);

    $this->artisan('authorization:sync')
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect(Permission::count())->toBe(0)
        ->and(Role::count())->toBe(0);
})->with([
    'duplicate permission' => [[SingleCatalog::class, DuplicatePermissionCatalog::class], 'Permission [test.view] is claimed by both'],
    'duplicate role' => [[SingleCatalog::class, DuplicateRoleCatalog::class], 'Role [test.viewer] is claimed by both'],
]);

test('catalogs cannot map roles to permissions owned by another domain', function (): void {
    config()->set('authorization.catalogs', [SingleCatalog::class, ForeignPermissionCatalog::class]);

    $this->artisan('authorization:sync')
        ->expectsOutputToContain('maps role [foreign.viewer] to undeclared permission [test.view]')
        ->assertFailed();

    expect(Permission::count())->toBe(0)
        ->and(Role::count())->toBe(0);
});

test('invalid authorization guard configuration fails before table changes', function (mixed $guard, string $message): void {
    config()->set('authorization.guard', $guard);
    config()->set('authorization.catalogs', [SingleCatalog::class]);

    $this->artisan('authorization:sync')
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect(Permission::count())->toBe(0)
        ->and(Role::count())->toBe(0);
})->with([
    'missing guard' => [null, 'Configuration [authorization.guard] must be a non-empty string.'],
    'integer guard' => [123, 'Configuration [authorization.guard] must be a non-empty string.'],
    'empty guard' => ['', 'Configuration [authorization.guard] must be a non-empty string.'],
    'unknown guard' => ['missing', 'Configuration [authorization.guard] references unknown guard [missing].'],
]);

test('invalid authorization catalogs configuration fails before table changes', function (mixed $catalogs, string $message): void {
    config()->set('authorization.catalogs', $catalogs);

    $this->artisan('authorization:sync')
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect(Permission::count())->toBe(0)
        ->and(Role::count())->toBe(0);
})->with([
    'missing catalogs' => [null, 'Configuration [authorization.catalogs] must be an array of catalog classes.'],
    'non-array catalogs' => [SingleCatalog::class, 'Configuration [authorization.catalogs] must be an array of catalog classes.'],
    'non-string catalog entry' => [[null], 'Configuration [authorization.catalogs] must contain resolvable catalog class names.'],
    'unresolvable catalog class' => [['MissingCatalog'], 'Configuration [authorization.catalogs] must contain resolvable catalog class names.'],
    'non-catalog class' => [[NonCatalog::class], 'must implement'],
]);

test('invalid catalog enum values fail before table changes', function (string $catalog, string $message): void {
    config()->set('authorization.catalogs', [$catalog]);

    $this->artisan('authorization:sync')
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect(Permission::count())->toBe(0)
        ->and(Role::count())->toBe(0);
})->with([
    'plain permission value' => [PlainPermissionValueCatalog::class, 'returned an invalid permission value'],
    'empty permission value' => [EmptyPermissionValueCatalog::class, 'returned an invalid permission value'],
    'integer permission value' => [IntegerPermissionValueCatalog::class, 'returned an invalid permission value'],
    'plain role value' => [PlainRoleValueCatalog::class, 'returned a role that is not a backed enum'],
    'empty role value' => [EmptyRoleValueCatalog::class, 'returned an invalid role value'],
    'integer role value' => [IntegerRoleValueCatalog::class, 'returned an invalid role value'],
    'plain mapped permission value' => [PlainMappedPermissionValueCatalog::class, 'mapped role [test.viewer] to an invalid permission value'],
    'empty mapped permission value' => [EmptyMappedPermissionValueCatalog::class, 'mapped role [test.viewer] to an invalid permission value'],
]);

test('definitions for unmanaged guards survive sync and prune', function (): void {
    config()->set('authorization.catalogs', [EmptyCatalog::class]);
    Permission::create(['name' => 'api.view', 'guard_name' => 'api']);
    Role::create(['name' => 'api.viewer', 'guard_name' => 'api']);

    $this->artisan('authorization:sync --prune')->assertSuccessful();

    $this->assertDatabaseHas('permissions', ['name' => 'api.view', 'guard_name' => 'api']);
    $this->assertDatabaseHas('roles', ['name' => 'api.viewer', 'guard_name' => 'api']);
});

test('normal sync reports and retains stale same-guard definitions', function (): void {
    config()->set('authorization.catalogs', [SingleCatalog::class]);
    Permission::create(['name' => 'legacy.view', 'guard_name' => 'web']);
    Role::create(['name' => 'legacy.viewer', 'guard_name' => 'web']);

    $this->artisan('authorization:sync')
        ->expectsOutputToContain('Stale authorization definitions found:')
        ->expectsOutputToContain('role: legacy.viewer')
        ->expectsOutputToContain('permission: legacy.view')
        ->assertSuccessful();

    $this->assertDatabaseHas('permissions', ['name' => 'legacy.view', 'guard_name' => 'web']);
    $this->assertDatabaseHas('roles', ['name' => 'legacy.viewer', 'guard_name' => 'web']);
});

test('prune deletes unassigned stale definitions', function (): void {
    config()->set('authorization.catalogs', [SingleCatalog::class]);
    Permission::create(['name' => 'legacy.view', 'guard_name' => 'web']);
    Role::create(['name' => 'legacy.viewer', 'guard_name' => 'web']);

    $this->artisan('authorization:sync --prune')
        ->expectsOutputToContain('Pruned stale authorization definitions.')
        ->assertSuccessful();

    $this->assertDatabaseMissing('permissions', ['name' => 'legacy.view', 'guard_name' => 'web']);
    $this->assertDatabaseMissing('roles', ['name' => 'legacy.viewer', 'guard_name' => 'web']);
    $this->assertDatabaseHas('permissions', ['name' => 'test.view', 'guard_name' => 'web']);
    $this->assertDatabaseHas('roles', ['name' => 'test.viewer', 'guard_name' => 'web']);
});

test('prune blocks assigned stale definitions and rolls back sync changes', function (): void {
    config()->set('authorization.catalogs', [SingleCatalog::class]);
    $user = User::factory()->create();
    $managedPermission = Permission::create(['name' => 'test.view', 'guard_name' => 'web']);
    $managedRole = Role::create(['name' => 'test.viewer', 'guard_name' => 'web']);
    $stalePermission = Permission::create(['name' => 'legacy.direct', 'guard_name' => 'web']);
    $staleRole = Role::create(['name' => 'legacy.assigned', 'guard_name' => 'web']);
    $user->givePermissionTo($stalePermission);
    $user->assignRole($staleRole);

    app(PermissionRegistrar::class)->getPermissions();
    expect(cachedRoleNamesForPermission('test.view'))->toBe([]);

    $this->artisan('authorization:sync --prune')
        ->expectsOutputToContain('Cannot prune assigned stale authorization definitions:')
        ->expectsOutputToContain('Role [legacy.assigned] is assigned')
        ->expectsOutputToContain('Permission [legacy.direct] is directly assigned')
        ->assertFailed();

    $this->assertDatabaseHas('permissions', ['name' => 'legacy.direct', 'guard_name' => 'web']);
    $this->assertDatabaseHas('roles', ['name' => 'legacy.assigned', 'guard_name' => 'web']);
    expect($managedRole->fresh()->permissions()->pluck('name')->all())->toBe([])
        ->and($managedPermission->fresh()->roles()->count())->toBe(0)
        ->and(cachedRoleNamesForPermission('test.view'))->toBe([]);
});

test('cache-backed permission reads see synchronized mappings after the command completes', function (): void {
    config()->set('authorization.catalogs', [SingleCatalog::class]);
    app(PermissionRegistrar::class)->getPermissions();

    $this->artisan('authorization:sync')->assertSuccessful();

    expect(Role::findByName('test.viewer', 'web')->hasPermissionTo('test.view'))->toBeTrue();
});

function permissionNamesForRole(string $roleName): array
{
    return Role::findByName($roleName, 'web')
        ->permissions()
        ->orderBy('name')
        ->pluck('name')
        ->all();
}

function cachedRoleNamesForPermission(string $permissionName): array
{
    return Permission::findByName($permissionName, 'web')
        ->roles
        ->pluck('name')
        ->sort()
        ->values()
        ->all();
}

enum TestPermission: string
{
    case View = 'test.view';
    case Update = 'test.update';
}

enum TestRole: string
{
    case Viewer = 'test.viewer';
}

enum IgnoredPermission: string
{
    case View = 'ignored.view';
}

enum IgnoredRole: string
{
    case Viewer = 'ignored.viewer';
}

enum ForeignRole: string
{
    case Viewer = 'foreign.viewer';
}

enum EmptyPermission: string
{
    case Blank = '';
}

enum EmptyRole: string
{
    case Blank = '';
}

enum IntegerPermission: int
{
    case View = 1;
}

enum IntegerRole: int
{
    case Viewer = 1;
}

final class NonCatalog
{
    //
}

final class SingleCatalog implements AuthorizationCatalog
{
    public function permissions(): array
    {
        return [TestPermission::View];
    }

    public function roles(): array
    {
        return [
            [
                'role' => TestRole::Viewer,
                'permissions' => [TestPermission::View],
            ],
        ];
    }
}

final class WideCatalog implements AuthorizationCatalog
{
    public function permissions(): array
    {
        return [TestPermission::View, TestPermission::Update];
    }

    public function roles(): array
    {
        return [
            [
                'role' => TestRole::Viewer,
                'permissions' => [TestPermission::View, TestPermission::Update],
            ],
        ];
    }
}

final class DuplicatePermissionCatalog implements AuthorizationCatalog
{
    public function permissions(): array
    {
        return [TestPermission::View];
    }

    public function roles(): array
    {
        return [];
    }
}

final class DuplicateRoleCatalog implements AuthorizationCatalog
{
    public function permissions(): array
    {
        return [TestPermission::Update];
    }

    public function roles(): array
    {
        return [
            [
                'role' => TestRole::Viewer,
                'permissions' => [TestPermission::Update],
            ],
        ];
    }
}

final class ForeignPermissionCatalog implements AuthorizationCatalog
{
    public function permissions(): array
    {
        return [];
    }

    public function roles(): array
    {
        return [
            [
                'role' => ForeignRole::Viewer,
                'permissions' => [TestPermission::View],
            ],
        ];
    }
}

final class EmptyCatalog implements AuthorizationCatalog
{
    public function permissions(): array
    {
        return [];
    }

    public function roles(): array
    {
        return [];
    }
}

final class IgnoredCatalog implements AuthorizationCatalog
{
    public function permissions(): array
    {
        return [IgnoredPermission::View];
    }

    public function roles(): array
    {
        return [
            [
                'role' => IgnoredRole::Viewer,
                'permissions' => [IgnoredPermission::View],
            ],
        ];
    }
}

final class PlainPermissionValueCatalog implements AuthorizationCatalog
{
    public function permissions(): array
    {
        return ['test.view'];
    }

    public function roles(): array
    {
        return [];
    }
}

final class EmptyPermissionValueCatalog implements AuthorizationCatalog
{
    public function permissions(): array
    {
        return [EmptyPermission::Blank];
    }

    public function roles(): array
    {
        return [];
    }
}

final class PlainRoleValueCatalog implements AuthorizationCatalog
{
    public function permissions(): array
    {
        return [TestPermission::View];
    }

    public function roles(): array
    {
        return [
            [
                'role' => 'test.viewer',
                'permissions' => [TestPermission::View],
            ],
        ];
    }
}

final class IntegerPermissionValueCatalog implements AuthorizationCatalog
{
    public function permissions(): array
    {
        return [IntegerPermission::View];
    }

    public function roles(): array
    {
        return [];
    }
}

final class EmptyRoleValueCatalog implements AuthorizationCatalog
{
    public function permissions(): array
    {
        return [TestPermission::View];
    }

    public function roles(): array
    {
        return [
            [
                'role' => EmptyRole::Blank,
                'permissions' => [TestPermission::View],
            ],
        ];
    }
}

final class IntegerRoleValueCatalog implements AuthorizationCatalog
{
    public function permissions(): array
    {
        return [TestPermission::View];
    }

    public function roles(): array
    {
        return [
            [
                'role' => IntegerRole::Viewer,
                'permissions' => [TestPermission::View],
            ],
        ];
    }
}

final class PlainMappedPermissionValueCatalog implements AuthorizationCatalog
{
    public function permissions(): array
    {
        return [TestPermission::View];
    }

    public function roles(): array
    {
        return [
            [
                'role' => TestRole::Viewer,
                'permissions' => ['test.view'],
            ],
        ];
    }
}

final class EmptyMappedPermissionValueCatalog implements AuthorizationCatalog
{
    public function permissions(): array
    {
        return [TestPermission::View];
    }

    public function roles(): array
    {
        return [
            [
                'role' => TestRole::Viewer,
                'permissions' => [EmptyPermission::Blank],
            ],
        ];
    }
}
