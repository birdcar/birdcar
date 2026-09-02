<?php

namespace App\Console\Commands;

use App\Authorization\Contracts\AuthorizationCatalog;
use BackedEnum;
use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class SyncAuthorization extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'authorization:sync {--prune : Delete stale, unassigned definitions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize application authorization catalogs';

    /**
     * Execute the console command.
     */
    public function handle(Container $container, PermissionRegistrar $registrar): int
    {
        try {
            $guard = $this->configuredGuard();
            $definitions = $this->catalogDefinitions($container);
        } catch (AuthorizationSyncException $exception) {
            $this->error($exception->getMessage());

            return Command::FAILURE;
        }

        DB::beginTransaction();

        try {
            $this->synchronizeDefinitions($definitions, $guard);
            $stale = $this->staleDefinitions($definitions, $guard);

            if ($this->option('prune')) {
                $blockers = $this->pruneBlockers($stale);

                if ($blockers !== []) {
                    DB::rollBack();
                    $registrar->forgetCachedPermissions();

                    $this->error('Cannot prune assigned stale authorization definitions:');

                    foreach ($blockers as $blocker) {
                        $this->line("  - {$blocker}");
                    }

                    return Command::FAILURE;
                }

                $this->pruneStaleDefinitions($stale);
            }

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            $registrar->forgetCachedPermissions();

            throw $exception;
        }

        $registrar->forgetCachedPermissions();
        $this->info("Authorization catalogs synchronized for guard [{$guard}].");
        $this->reportStaleDefinitions($stale);

        if ($this->option('prune') && ($stale['roles'] !== [] || $stale['permissions'] !== [])) {
            $this->info('Pruned stale authorization definitions.');
        }

        return Command::SUCCESS;
    }

    private function configuredGuard(): string
    {
        $guard = config('authorization.guard');

        if (! is_string($guard) || trim($guard) === '') {
            throw new AuthorizationSyncException('Configuration [authorization.guard] must be a non-empty string.');
        }

        if (config("auth.guards.{$guard}") === null) {
            throw new AuthorizationSyncException("Configuration [authorization.guard] references unknown guard [{$guard}].");
        }

        return $guard;
    }

    /**
     * @return array{
     *     permissions: array<string, string>,
     *     roles: array<string, array{catalog: string, permissions: list<string>}>
     * }
     */
    private function catalogDefinitions(Container $container): array
    {
        $catalogClasses = config('authorization.catalogs');

        if (! is_array($catalogClasses)) {
            throw new AuthorizationSyncException('Configuration [authorization.catalogs] must be an array of catalog classes.');
        }

        $permissionOwners = [];
        $roleDefinitions = [];

        foreach (array_values($catalogClasses) as $catalogClass) {
            if (! is_string($catalogClass) || $catalogClass === '' || ! class_exists($catalogClass)) {
                throw new AuthorizationSyncException('Configuration [authorization.catalogs] must contain resolvable catalog class names.');
            }

            try {
                $catalog = $container->make($catalogClass);
            } catch (Throwable) {
                throw new AuthorizationSyncException("Catalog [{$catalogClass}] could not be resolved from the container.");
            }

            if (! $catalog instanceof AuthorizationCatalog) {
                throw new AuthorizationSyncException("Catalog [{$catalogClass}] must implement [".AuthorizationCatalog::class.'].');
            }

            $localPermissions = $this->permissionNames($catalog, $catalogClass);

            foreach ($localPermissions as $permissionName) {
                if (array_key_exists($permissionName, $permissionOwners)) {
                    throw new AuthorizationSyncException("Permission [{$permissionName}] is claimed by both [{$permissionOwners[$permissionName]}] and [{$catalogClass}].");
                }

                $permissionOwners[$permissionName] = $catalogClass;
            }

            foreach ($this->roleDefinitions($catalog, $catalogClass, $localPermissions) as $roleName => $permissionNames) {
                if (array_key_exists($roleName, $roleDefinitions)) {
                    throw new AuthorizationSyncException("Role [{$roleName}] is claimed by both [{$roleDefinitions[$roleName]['catalog']}] and [{$catalogClass}].");
                }

                $roleDefinitions[$roleName] = [
                    'catalog' => $catalogClass,
                    'permissions' => $permissionNames,
                ];
            }
        }

        return [
            'permissions' => $permissionOwners,
            'roles' => $roleDefinitions,
        ];
    }

    /**
     * @return list<string>
     */
    private function permissionNames(AuthorizationCatalog $catalog, string $catalogClass): array
    {
        $permissionNames = [];

        foreach ($catalog->permissions() as $permission) {
            $permissionNames[] = $this->enumString($permission, "Catalog [{$catalogClass}] returned an invalid permission value.");
        }

        return $permissionNames;
    }

    /**
     * @param  list<string>  $localPermissions
     * @return array<string, list<string>>
     */
    private function roleDefinitions(AuthorizationCatalog $catalog, string $catalogClass, array $localPermissions): array
    {
        $localPermissionLookup = array_fill_keys($localPermissions, true);
        $roleDefinitions = [];

        foreach ($catalog->roles() as $roleDefinition) {
            $roleDefinition = $this->validatedRoleDefinition($roleDefinition, $catalogClass);
            $roleName = $this->enumString($roleDefinition['role'], "Catalog [{$catalogClass}] returned an invalid role value.");

            if (array_key_exists($roleName, $roleDefinitions)) {
                throw new AuthorizationSyncException("Catalog [{$catalogClass}] returned duplicate role [{$roleName}].");
            }

            $permissionNames = [];

            foreach ($roleDefinition['permissions'] as $permission) {
                $permissionName = $this->enumString($permission, "Catalog [{$catalogClass}] mapped role [{$roleName}] to an invalid permission value.");

                if (! array_key_exists($permissionName, $localPermissionLookup)) {
                    throw new AuthorizationSyncException("Catalog [{$catalogClass}] maps role [{$roleName}] to undeclared permission [{$permissionName}].");
                }

                $permissionNames[] = $permissionName;
            }

            $roleDefinitions[$roleName] = array_values(array_unique($permissionNames));
        }

        return $roleDefinitions;
    }

    /**
     * @return array{role: BackedEnum, permissions: array<int, mixed>}
     */
    private function validatedRoleDefinition(mixed $roleDefinition, string $catalogClass): array
    {
        if (! is_array($roleDefinition) || ! array_key_exists('role', $roleDefinition) || ! array_key_exists('permissions', $roleDefinition)) {
            throw new AuthorizationSyncException("Catalog [{$catalogClass}] returned an invalid role mapping.");
        }

        if (! $roleDefinition['role'] instanceof BackedEnum) {
            throw new AuthorizationSyncException("Catalog [{$catalogClass}] returned a role that is not a backed enum.");
        }

        if (! is_array($roleDefinition['permissions'])) {
            throw new AuthorizationSyncException("Catalog [{$catalogClass}] returned an invalid permission list for a role.");
        }

        return [
            'role' => $roleDefinition['role'],
            'permissions' => $roleDefinition['permissions'],
        ];
    }

    private function enumString(mixed $enum, string $message): string
    {
        if (! $enum instanceof BackedEnum || ! is_string($enum->value) || $enum->value === '') {
            throw new AuthorizationSyncException($message);
        }

        return $enum->value;
    }

    /**
     * @param  array{
     *     permissions: array<string, string>,
     *     roles: array<string, array{catalog: string, permissions: list<string>}>
     * }  $definitions
     */
    private function synchronizeDefinitions(array $definitions, string $guard): void
    {
        $permissionModels = [];
        $permissionClass = $this->permissionModelClass();
        $roleClass = $this->roleModelClass();

        foreach (array_keys($definitions['permissions']) as $permissionName) {
            $permissionModels[$permissionName] = $permissionClass::findOrCreate($permissionName, $guard);
        }

        foreach ($definitions['roles'] as $roleName => $roleDefinition) {
            $role = $roleClass::findOrCreate($roleName, $guard);
            $role->syncPermissions(array_map(
                static fn (string $permissionName) => $permissionModels[$permissionName],
                $roleDefinition['permissions'],
            ));
        }
    }

    /**
     * @param  array{
     *     permissions: array<string, string>,
     *     roles: array<string, array{catalog: string, permissions: list<string>}>
     * }  $definitions
     * @return array{
     *     roles: list<array{id: int|string, name: string}>,
     *     permissions: list<array{id: int|string, name: string}>
     * }
     */
    private function staleDefinitions(array $definitions, string $guard): array
    {
        $roleClass = $this->roleModelClass();
        $permissionClass = $this->permissionModelClass();
        $roles = [];

        foreach ($roleClass::query()
            ->where('guard_name', $guard)
            ->whereNotIn('name', array_keys($definitions['roles']))
            ->orderBy('name')
            ->get(['id', 'name']) as $role) {
            $roles[] = [
                'id' => $this->modelKey($role),
                'name' => $role->name,
            ];
        }

        $permissions = [];

        foreach ($permissionClass::query()
            ->where('guard_name', $guard)
            ->whereNotIn('name', array_keys($definitions['permissions']))
            ->orderBy('name')
            ->get(['id', 'name']) as $permission) {
            $permissions[] = [
                'id' => $this->modelKey($permission),
                'name' => $permission->name,
            ];
        }

        return [
            'roles' => $roles,
            'permissions' => $permissions,
        ];
    }

    private function modelKey(PermissionModel|RoleModel $model): int|string
    {
        $key = $model->getKey();

        if (! is_int($key) && ! is_string($key)) {
            throw new AuthorizationSyncException('Spatie permission model keys must be integers or strings.');
        }

        return $key;
    }

    /**
     * @param  array{
     *     roles: list<array{id: int|string, name: string}>,
     *     permissions: list<array{id: int|string, name: string}>
     * }  $stale
     * @return list<string>
     */
    private function pruneBlockers(array $stale): array
    {
        $tables = $this->permissionTableNames();
        $columns = $this->permissionColumnNames();
        $blockers = [];

        foreach ($stale['roles'] as $role) {
            if (DB::table($tables['model_has_roles'])->where($columns['role_pivot_key'], $role['id'])->exists()) {
                $blockers[] = "Role [{$role['name']}] is assigned through [{$tables['model_has_roles']}].";
            }
        }

        foreach ($stale['permissions'] as $permission) {
            if (DB::table($tables['model_has_permissions'])->where($columns['permission_pivot_key'], $permission['id'])->exists()) {
                $blockers[] = "Permission [{$permission['name']}] is directly assigned through [{$tables['model_has_permissions']}].";
            }
        }

        return $blockers;
    }

    /**
     * @param  array{
     *     roles: list<array{id: int|string, name: string}>,
     *     permissions: list<array{id: int|string, name: string}>
     * }  $stale
     */
    private function pruneStaleDefinitions(array $stale): void
    {
        $roleClass = $this->roleModelClass();
        $permissionClass = $this->permissionModelClass();

        foreach ($stale['roles'] as $role) {
            $roleClass::query()->whereKey($role['id'])->delete();
        }

        foreach ($stale['permissions'] as $permission) {
            $permissionClass::query()->whereKey($permission['id'])->delete();
        }
    }

    /**
     * @param  array{
     *     roles: list<array{id: int|string, name: string}>,
     *     permissions: list<array{id: int|string, name: string}>
     * }  $stale
     */
    private function reportStaleDefinitions(array $stale): void
    {
        if ($stale['roles'] === [] && $stale['permissions'] === []) {
            $this->info('No stale authorization definitions found.');

            return;
        }

        $this->warn('Stale authorization definitions found:');

        foreach ($stale['roles'] as $role) {
            $this->line("  - role: {$role['name']}");
        }

        foreach ($stale['permissions'] as $permission) {
            $this->line("  - permission: {$permission['name']}");
        }
    }

    /**
     * @return array{
     *     model_has_roles: string,
     *     model_has_permissions: string
     * }
     */
    private function permissionTableNames(): array
    {
        $tableNames = config('permission.table_names');

        if (! is_array($tableNames)) {
            throw new AuthorizationSyncException('Configuration [permission.table_names] must be an array.');
        }

        return [
            'model_has_roles' => $this->configString($tableNames['model_has_roles'] ?? null, 'model_has_roles'),
            'model_has_permissions' => $this->configString($tableNames['model_has_permissions'] ?? null, 'model_has_permissions'),
        ];
    }

    /**
     * @return array{
     *     role_pivot_key: string,
     *     permission_pivot_key: string
     * }
     */
    private function permissionColumnNames(): array
    {
        $columnNames = config('permission.column_names');

        if (! is_array($columnNames)) {
            throw new AuthorizationSyncException('Configuration [permission.column_names] must be an array.');
        }

        return [
            'role_pivot_key' => $this->configString($columnNames['role_pivot_key'] ?? null, 'role_id'),
            'permission_pivot_key' => $this->configString($columnNames['permission_pivot_key'] ?? null, 'permission_id'),
        ];
    }

    private function configString(mixed $configuredValue, string $default): string
    {
        if ($configuredValue === null) {
            return $default;
        }

        if (! is_string($configuredValue) || $configuredValue === '') {
            throw new AuthorizationSyncException('Spatie permission table and column configuration values must be strings when set.');
        }

        return $configuredValue;
    }

    /**
     * @return class-string<PermissionModel>
     */
    private function permissionModelClass(): string
    {
        $permissionClass = config('permission.models.permission', PermissionModel::class);

        if (! is_string($permissionClass) || ! is_a($permissionClass, PermissionModel::class, true)) {
            return PermissionModel::class;
        }

        return $permissionClass;
    }

    /**
     * @return class-string<RoleModel>
     */
    private function roleModelClass(): string
    {
        $roleClass = config('permission.models.role', RoleModel::class);

        if (! is_string($roleClass) || ! is_a($roleClass, RoleModel::class, true)) {
            return RoleModel::class;
        }

        return $roleClass;
    }
}

final class AuthorizationSyncException extends RuntimeException
{
    //
}
