# Context Map: 2026-09-02-domain-colocated-authorization

**Phase**: 1
**Gates**: 4/5 ready
**Verdict**: GO

## Gates

| Gate                 | Status    | Evidence |
| -------------------- | --------- | -------- |
| Scope clarity        | ready     | `docs/ideation/2026-09-02-domain-colocated-authorization/spec-phase-1.md` names exactly ten new files and no modified/deleted files. |
| Pattern familiarity  | not-ready | The named pattern directory `app/Console/Commands` does not exist yet; related Laravel/Pest/Spatie/config conventions were read in `routes/console.php`, `bootstrap/app.php`, `tests/Pest.php`, `config/permission.php`, and vendor package sources. |
| Dependency awareness | ready     | No existing `app/Authorization` or class command consumers exist; current Spatie consumers are `app/Models/User.php:16,33`, `bootstrap/app.php:7-24`, `config/permission.php:20-209`, and the permission-table migration. |
| Edge case coverage   | ready     | Spec plus code exploration identify concrete edge cases: invalid config, non-catalog classes, duplicate names, empty enum values, foreign permissions, guard isolation, stale assignment blockers, transaction rollback, and cache refresh. |
| Test strategy        | ready     | Pest feature tests use `RefreshDatabase` globally in `tests/Pest.php:17-19`, in-memory SQLite in `phpunit.xml:25-27`, and the spec gives `php artisan test --compact tests/Feature/Authorization/AuthorizationSyncCommandTest.php` plus Pint/static-analysis validation. |

## Key Patterns

- `app/Console/Commands` — Spec references this as the Laravel command location, but the directory is absent in the current repo. Builder should generate it with `php artisan make:command SyncAuthorization --command=authorization:sync --no-interaction` as specified, then use Laravel command conventions (`protected $signature`, dependency-injected `handle`, explicit `Command::SUCCESS`/`Command::FAILURE`).
- `routes/console.php:3-8` — Only existing project command is a closure command via `Artisan::command('inspire', ...)`; useful for output style but not a class-command analogue.
- `bootstrap/app.php:15` — Console routes are loaded from `routes/console.php`; no explicit command registration exists here. Spec states Laravel 13 auto-registers commands in `app/Console/Commands`.
- `bootstrap/app.php:7-24` — Spatie permission middleware aliases are already registered (`role`, `permission`, `role_or_permission`), confirming the package is in active app infrastructure.
- `config/permission.php:20,31,48-88,95-106,151,209` — Spatie model classes, table names, pivot key config, `teams => false`, model morph key, and cache key are all configurable and should be read via `config('permission.*')` instead of hard-coded except for documented defaults when null.
- `database/migrations/2026_09_01_150926_create_permission_tables.php:17-18,26-32,38-50,54-114,119` — Confirms actual table schema, guard-scoped unique indexes, pivot tables, cascade deletes, and cache clearing during migration.
- `vendor/spatie/laravel-permission/src/Models/Permission.php:103-148` — `Permission::findOrCreate(BackedEnum|string $name, ?string $guardName = null)` accepts backed enums/strings and upserts by name/guard.
- `vendor/spatie/laravel-permission/src/Models/Role.php:118-170` — `Role::findOrCreate(BackedEnum|string $name, ?string $guardName = null)` accepts backed enums/strings and upserts by name/guard.
- `vendor/spatie/laravel-permission/src/Traits/HasPermissions.php:476-491` — `syncPermissions()` detaches current permissions and gives the provided exact set, matching the spec’s exact role mapping requirement.
- `vendor/spatie/laravel-permission/src/PermissionRegistrar.php:136-143` — `forgetCachedPermissions()` clears registrar memory and configured cache key; command should call it after success and rollback paths.
- `tests/Pest.php:17-19` — Feature tests automatically extend `Tests\TestCase` and use `RefreshDatabase`; new command tests should be Pest feature tests.
- `phpunit.xml:7-13,21,25-27` — Feature tests are discovered under `tests/Feature`; testing uses `APP_ENV=testing`, array cache, and SQLite `:memory:`.
- `composer.json:15,39,58-63,90-103` — Laravel 13.17, Spatie Permission 8.3, Pest 5.1, PSR-4 `App\ => app/`, and scripts for linting/types/tests are installed.
- `CLAUDE.md` / `AGENTS.md` — Project guidelines require existing conventions, Laravel generators, explicit return types, curly braces, TitleCase enum cases, PHPDoc array shapes, Pest tests, and Pint formatting.
- `.agents/skills/laravel-permission-development/SKILL.md` — Confirms preferred model is roles-have-permissions, users get roles, direct permissions are an anti-pattern but must still be checked for prune blockers, and Spatie accepts enums in many APIs.
- `.agents/skills/testing-best-practices/SKILL.md` — Tests should cover observable behavior, every changed decision/failure mode, and use nearby Pest conventions.

## Dependencies

- `app/Authorization/Contracts/AuthorizationCatalog.php` — new file; expected consumed by → `app/Authorization/Admin/Catalog.php`, `app/Authorization/Organizations/Catalog.php`, `app/Console/Commands/SyncAuthorization.php`, `tests/Feature/Authorization/AuthorizationSyncCommandTest.php`.
- `app/Authorization/Admin/Permission.php` — new file; expected consumed by → `app/Authorization/Admin/Catalog.php`, possibly command tests through configured Admin catalog.
- `app/Authorization/Admin/Role.php` — new file; expected consumed by → `app/Authorization/Admin/Catalog.php`, possibly command tests through configured Admin catalog.
- `app/Authorization/Admin/Catalog.php` — new file; expected consumed by → `config/authorization.php` catalog registry and resolved by `app/Console/Commands/SyncAuthorization.php`.
- `app/Authorization/Organizations/Permission.php` — new file; expected consumed by → `app/Authorization/Organizations/Catalog.php`, possibly future organization policy/controller work.
- `app/Authorization/Organizations/Role.php` — new file; expected consumed by → `app/Authorization/Organizations/Catalog.php`, possibly future role assignment flows.
- `app/Authorization/Organizations/Catalog.php` — new file; expected consumed by → `config/authorization.php` catalog registry and resolved by `app/Console/Commands/SyncAuthorization.php`.
- `app/Console/Commands/SyncAuthorization.php` — new file; expected consumed by → Laravel Artisan command discovery in Laravel 13, `php artisan authorization:sync`, and `tests/Feature/Authorization/AuthorizationSyncCommandTest.php`.
- `config/authorization.php` — new file; expected consumed by → `app/Console/Commands/SyncAuthorization.php` via `config('authorization.guard')` and `config('authorization.catalogs')`; tests will override these keys with `config()->set(...)`.
- `tests/Feature/Authorization/AuthorizationSyncCommandTest.php` — new file; consumed by → Pest/PHPUnit discovery under `tests/Feature`.
- `app/Models/User.php:16,33` — consumes Spatie `HasRoles`; relevant for assigned stale role/direct-permission prune-blocker fixtures.
- `bootstrap/app.php:7-24` — consumes Spatie permission middleware; authorization package is already configured at middleware level but sync command should not change bootstrap registration.
- `config/permission.php:20,31,56-88,95-106,151,209` — consumed by Spatie package and should be consumed by sync command for model/table/pivot/cache names.
- `database/migrations/2026_09_01_150926_create_permission_tables.php:26-114` — creates the tables the command mutates and assignment pivots it inspects.

No existing external consumers of the new `App\Authorization` namespace were found; changes are mostly additive and command-test scoped.

## Conventions

- **Naming**: PHP classes use PSR-4 namespaces under `App\`; existing classes are singular descriptive names (`OrganizationController`, `StoreOrganizationRequest`, `OrganizationPolicy`). Enum cases should be TitleCase per project guidelines, matching the spec (`View`, `Update`, `Access`, `Viewer`, `Editor`).
- **Imports**: Existing PHP files import framework/package classes explicitly after the namespace (`app/Models/User.php:5-18`, `bootstrap/app.php:3-9`). Config files use fully-qualified imported class names at top where helpful (`config/permission.php:3-5`).
- **Error handling**: Project docs require explicit return types and Laravel-style failures; spec requires command-level validation failures before writes. Migration uses `throw_if(...)` for hard migration config failures, but command should print actionable errors and return `Command::FAILURE` for operator-correctable config/catalog problems.
- **Types**: Existing code uses typed method returns (`User::casts(): array`, `User::initials(): string`, policy methods `: bool`). PHPDoc generics/array shapes are used (`StoreOrganizationRequest.php:21`, `UserFactory.php`). New contract should use precise list/array-shape PHPDoc with `BackedEnum`.
- **Testing**: Pest is the project convention; Feature tests automatically use `RefreshDatabase` (`tests/Pest.php:17-19`). Current tests are minimal examples, so the new test file should establish the command behavior convention using Laravel command test assertions (`artisan(...)->assertSuccessful()/assertFailed()`), database assertions, and config overrides.
- **Configuration**: Config files return arrays and are loaded by dot notation; new `config/authorization.php` should mirror simple array style in `config/*.php` and list catalog class strings explicitly.
- **Spatie Permission**: Teams are disabled (`config/permission.php:151`), but pivot/table/key names remain configurable (`config/permission.php:48-106`). Use configured model classes (`config/permission.php:20,31`) or Spatie defaults carefully, and guard-scope all role/permission queries.
- **Formatting/static analysis**: Pint preset is Laravel (`pint.json`), Larastan is level 7 over `app`, `bootstrap/app.php`, `config`, `database`, and `routes` (`phpstan.neon`). Builder should keep config/test helper classes compatible with these paths where applicable.

## Risks

- `app/Console/Commands` does not exist, so there is no in-repo class-command pattern; builder should rely on the generated Laravel 13 command skeleton and verify command discovery with the focused test.
- Spatie pivot key config values are `null` for defaults (`config/permission.php:95-96`); prune assignment checks must normalize to `role_id` and `permission_id`, as the migration does at `database/migrations/2026_09_01_150926_create_permission_tables.php:17-18`.
- Spatie teams are disabled (`config/permission.php:151`), but the migration contains conditional team columns for testing; command should not introduce team assumptions or include nonexistent team keys in normal queries.
- The command will own all same-guard Spatie roles/permissions. No existing seeders or app roles were found, but future manual same-guard rows will be reported/pruned as stale by design.
- `syncPermissions()` mutates pivots and cache state before later prune blockers may fail; spec requires one DB transaction and explicit cache flush on rollback/success to avoid stale in-memory permission state.
- Direct permissions are considered an anti-pattern by the permission skill, but direct assignments must still be supported in test fixtures because stale direct permissions block pruning per spec.
- Decision-log contradiction check: no contradiction found. The rejected alternatives are not already implemented; there is no existing `app/Authorization`, no role/permission seeders found in targeted search, and no ownership table beyond Spatie package tables.

## Edge Cases for Builder

- Missing `authorization.guard`, non-string guard, empty guard, or guard not configured in `auth.guards`.
- Missing `authorization.catalogs`, non-array catalogs, non-string/non-class entries, unresolvable class names, and resolved objects that do not implement `AuthorizationCatalog`.
- Catalog permissions/roles returning non-`BackedEnum` values despite PHPDoc, non-string backed values, or empty-string enum values.
- Duplicate permission values across catalogs and duplicate role values across catalogs, even if mappings are identical.
- Role mappings that reference permissions not declared by the same catalog, including permissions declared by a different domain catalog.
- Empty catalog list or empty individual catalog should not accidentally mark all existing same-guard definitions for deletion unless that is an explicit expected result in tests.
- Idempotent reruns should not duplicate rows or alter mappings unnecessarily; exact mapping changes should revoke removed role permissions on normal sync.
- Stale reporting should be deterministic and guard-scoped; `api` guard roles/permissions must survive sync and prune.
- `--prune` should fail before deleting anything when stale roles appear in `model_has_roles` or stale permissions appear in `model_has_permissions`.
- Safe prune should delete stale roles before stale permissions so role-permission pivots cascade cleanly.
- Any database exception or blocked prune should roll back all writes from that command run and refresh Spatie permission cache state.

## Verification Commands

- `php artisan test --compact tests/Feature/Authorization/AuthorizationSyncCommandTest.php`
- `php artisan test --compact tests/Feature/Authorization/AuthorizationSyncCommandTest.php --filter='idempotent|catalogs|collisions'`
- `php artisan test --compact tests/Feature/Authorization/AuthorizationSyncCommandTest.php --filter='reports|prune'`
- `vendor/bin/pint --dirty --format agent`
- `composer types:check`
