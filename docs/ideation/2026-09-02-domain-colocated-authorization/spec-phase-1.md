# Implementation Spec: Domain-Colocated Authorization Foundation - Phase 1

**Phase**: Domain Catalogs and Safe Synchronization  
**Contract**: ./contract.md  
**Estimated Effort**: M

## Technical Approach

Create a small, explicit authorization catalog foundation without touching existing application models, controllers, routes, migrations, factories, or non-authorization tests. Each domain owns typed backed enums for permission and role names plus a catalog class that maps roles to permissions. A single `authorization:sync` Artisan command reads only the catalog classes registered in `config/authorization.php`, validates the complete in-memory catalog graph before writing, then synchronizes Spatie Permission rows for the configured guard.

The command treats every Spatie role and permission row for `authorization.guard` as catalog-owned. Normal sync reports same-guard stale definitions without deleting them. `--prune` deletes only unassigned stale definitions and fails closed when stale roles or direct permissions still have active model assignments. Definitions for other guards are unmanaged and must survive sync and prune.

## Decisions Considered and Rejected

- **Business-domain catalogs** — rejected model- or surface-owned catalogs because capabilities must be reusable from UI, API, and future MCP tools.
- **Backed Permission/Role enums plus one Catalog per domain** — rejected one class per definition and untyped arrays because enums keep names type-safe without excessive ceremony.
- **Explicit `config/authorization.php` registry** — rejected filesystem discovery and service-provider tagging because one searchable list is clearer than runtime magic.
- **Explicit idempotent command** — rejected seeders, historical migrations, and application-boot writes because production synchronization needs safe, observable semantics under Octane.
- **Configured-guard ownership** — rejected an ownership manifest because Birdcar owns every role and permission for the `web` guard.
- **Report drift and explicitly prune** — rejected automatic deletion and silent additive-only sync because both hide dangerous state changes.
- **Fail closed on assigned stale definitions** — rejected cascading active assignments because access revocation requires an explicit transition.

## Feedback Strategy

**Inner-loop command**: `php artisan test --compact tests/Feature/Authorization/AuthorizationSyncCommandTest.php`

**Playground**: Pest feature tests invoking the real Artisan command against the in-memory SQLite database.

**Why this approach**: The phase is a database-backed CLI operation, so focused command tests provide fast, deterministic feedback for exit codes, output, exact mappings, rollback, and cache behavior.

## File Changes

### New Files

| File Path | Purpose |
| --- | --- |
| `app/Authorization/Contracts/AuthorizationCatalog.php` | Shared catalog interface returning enum-backed permissions and role mappings. |
| `app/Authorization/Admin/Permission.php` | Admin domain permission enum; initially `admin.view`. |
| `app/Authorization/Admin/Role.php` | Admin domain role enum; initially `admin.access`. |
| `app/Authorization/Admin/Catalog.php` | Admin catalog mapping `admin.access` to `admin.view`. |
| `app/Authorization/Organizations/Permission.php` | Organizations domain permission enum; initially `organizations.view` and `organizations.update`. |
| `app/Authorization/Organizations/Role.php` | Organizations domain role enum; initially `organizations.viewer` and `organizations.editor`. |
| `app/Authorization/Organizations/Catalog.php` | Organizations catalog mapping viewer/editor roles to their permissions. |
| `app/Console/Commands/SyncAuthorization.php` | Laravel command skeleton using `protected $signature` / `protected $description` for `authorization:sync {--prune}`. |
| `config/authorization.php` | Explicit guard and catalog registry. |
| `tests/Feature/Authorization/AuthorizationSyncCommandTest.php` | Pest feature coverage for sync, validation, stale reporting/prune, rollback, guard isolation, and cache refresh. |

### Modified Files

None.

### Deleted Files

None.

## Implementation Details

### Catalog Contract and Domain Catalogs

`AuthorizationCatalog` exposes:

```php
/** @return list<BackedEnum> */
public function permissions(): array;

/**
 * @return list<array{
 *     role: BackedEnum,
 *     permissions: list<BackedEnum>
 * }>
 */
public function roles(): array;
```

Catalog enum cases use TitleCase PHP case names and stable string values:

- `App\Authorization\Admin\Permission::View = 'admin.view'`
- `App\Authorization\Admin\Role::Access = 'admin.access'`
- `App\Authorization\Organizations\Permission::View = 'organizations.view'`
- `App\Authorization\Organizations\Permission::Update = 'organizations.update'`
- `App\Authorization\Organizations\Role::Viewer = 'organizations.viewer'`
- `App\Authorization\Organizations\Role::Editor = 'organizations.editor'`

Mappings are exact and intentionally minimal: Admin access grants Admin view; Organizations viewer grants Organizations view; Organizations editor grants Organizations update. Do not add Admin routes, policies, membership behavior, factories, model relationships, schema changes, guidelines, or tenant authorization tests in this phase.

### Configuration

`config/authorization.php` contains:

```php
return [
    'guard' => 'web',
    'catalogs' => [
        AdminCatalog::class,
        OrganizationsCatalog::class,
    ],
];
```

Only listed catalog classes are loaded. Tests may override these config values to exercise validation and fixture catalogs.

### Synchronization Command

Generate/follow the standard Laravel class-command skeleton under `app/Console/Commands` and use:

```php
protected $signature = 'authorization:sync {--prune : Delete stale, unassigned definitions}';
protected $description = 'Synchronize application authorization catalogs';
```

The command must:

1. Validate `authorization.guard` is a non-empty configured auth guard.
2. Validate `authorization.catalogs` is an array of resolvable classes implementing `AuthorizationCatalog`.
3. Resolve catalogs through the container.
4. Validate every permission, role, and mapped permission is a backed enum with a non-empty string value.
5. Fail before database writes on duplicate permission names, duplicate role names, invalid role mappings, invalid config, non-catalog classes, or role mappings to permissions not declared by the same catalog.
6. Upsert permissions and roles for the configured guard with Spatie's configured models.
7. Synchronize each role's permissions exactly, revoking removed mapped permissions on normal sync.
8. Detect same-guard stale roles and permissions deterministically and report them during normal sync.
9. Keep other guards unmanaged.
10. When `--prune` is supplied, rollback and fail if stale roles are assigned through `model_has_roles` or stale permissions are directly assigned through `model_has_permissions`; otherwise delete stale roles before stale permissions.
11. Use one database transaction for writes/prune and call `PermissionRegistrar::forgetCachedPermissions()` after success and rollback paths.

Spatie table and pivot column names must be read from `config('permission.table_names')` and `config('permission.column_names')`, defaulting null pivot keys to `role_id` / `permission_id` consistently with the package migration.

## Testing Requirements

`tests/Feature/Authorization/AuthorizationSyncCommandTest.php` must cover observable command behavior:

- Registered Admin and Organizations catalogs create exact enum-backed permissions, roles, and mappings for `web`.
- Unregistered fixture catalogs are ignored.
- Reruns are idempotent and do not duplicate rows.
- Changed mappings revoke removed role permissions on normal sync.
- Duplicate role/permission names fail before writes.
- Role mappings cannot reference permissions from another domain catalog.
- Invalid `authorization.guard` values fail: missing/non-string, empty, and unknown guard.
- Invalid `authorization.catalogs` values fail: missing/non-array, non-string entries, unresolvable classes, and classes that do not implement `AuthorizationCatalog`.
- Invalid enum values fail before writes: non-backed values, non-string backed values, and empty-string backed values for permissions, roles, and mapped permissions.
- Other-guard roles/permissions survive sync and prune.
- Normal sync reports and retains stale same-guard definitions.
- `--prune` deletes unassigned stale definitions.
- `--prune` blocks assigned stale roles/direct permissions, rolls back sync changes, and refreshes Spatie cache.
- Cache-backed permission reads observe synchronized mappings after command completion.

## Error Handling

| Error Scenario | Handling Strategy |
| --- | --- |
| Invalid guard or catalog registry | Name the configuration key and fail before writes. |
| Unresolvable/non-catalog class | Name the class and fail before writes. |
| Duplicate role or permission ownership | Name both catalogs and the conflicting value; fail before writes. |
| Foreign or invalid enum mapping | Name the catalog, role, and permission; fail before writes. |
| Assigned stale definition during prune | List blockers, roll back the complete run, refresh the permission cache, and return failure. |
| Database exception | Roll back, refresh cache state, and rethrow for normal Laravel reporting. |

## Failure Modes

| Component | Failure Mode | Trigger | Impact | Mitigation |
| --- | --- | --- | --- | --- |
| Catalog validation | Partial writes | A later catalog is invalid after an earlier catalog resolves | Incomplete authorization graph | Build and validate the full graph before opening the transaction. |
| Exact mapping sync | Stale privilege retained | A permission is removed from a role in code | Existing subjects keep unintended authority | Use `syncPermissions()` for every expected role. |
| Guard scoping | Foreign definitions deleted | Drift query omits the configured guard | Future API roles disappear | Scope every query and test another guard. |
| Pruning | Partial destructive update | One stale row is assigned after another is deleted | Failed command still changes access | Preflight all blockers and wrap sync/prune in one transaction. |
| Cache | Rolled-back values remain in memory | Package methods flush while transaction later fails | Octane serves stale authorization | Explicitly forget cached permissions after success and rollback. |
| Concurrent runs | Two deployments sync together | Overlapping release commands | Lock contention or duplicate work | Database constraints and transactions protect integrity; defer a lock until an observed need. |

## Validation Commands

```bash
php artisan test --compact tests/Feature/Authorization/AuthorizationSyncCommandTest.php
php artisan test --compact tests/Feature/Authorization/AuthorizationSyncCommandTest.php --filter='idempotent|catalogs|collisions'
php artisan test --compact tests/Feature/Authorization/AuthorizationSyncCommandTest.php --filter='reports|prune'
vendor/bin/pint --dirty --format agent
composer types:check
```

## Rollout Considerations

Run `php artisan authorization:sync` during deployment after migrations. Use normal sync first to review stale definitions; use `--prune` only after confirming stale records are unassigned and safe to remove.

## Open Items

None.

---

_This spec is ready for implementation. Follow the patterns and validate at each step._
