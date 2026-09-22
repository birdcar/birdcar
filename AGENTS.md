<laravel-boost-guidelines>
=== .ai/authorization rules ===

# Authorization

- Use one User identity for overlapping Employee, Customer, and future Subscriber contexts; never add a mutually exclusive `users.type` for these identities.
- Attach global roles to User and tenant roles to OrganizationMembership; keep Spatie Teams disabled.
- Treat membership as the first-class `organization_memberships` model, and keep it on the `web` guard.
- Assign domain-local roles instead of direct model permissions, and authorize by checking permissions rather than role names.
- Define authorization in `app/Authorization/{Domain}/{Permission,Role,Catalog}.php`; every Catalog implements `AuthorizationCatalog` and is registered in `config/authorization.php`.
- Never create authorization definitions in a monolithic seeder, historical migrations, or application boot; run `authorization:sync`.
- Normal sync reports stale web-guard definitions; explicit prune fails when active assignments exist.
- Use `admin.view` only to admit the Admin surface; protect actions with transport-neutral domain capabilities.
- Tenant policies must check the matching membership and must never accept global User permissions as a bypass.
- Model subscription and product access with future entitlement models, not roles.
- Keep future Admin and Customer MCP servers separate, reuse domain permission enums, filter discovery with `shouldRegister()`, and authorize again in `handle()`.
- Design future machine and delegated agent identities separately before introducing them.
- Cover authorization changes with focused global, membership, and cross-tenant denial tests.

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.5. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `bun run build`, `bun run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd at `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs. Never run commands to serve the site. It is always available.
- Use the `herd` CLI to manage services, PHP versions, and sites (e.g. `herd sites`, `herd services:start <service>`, `herd php:list`). Run `herd list` to discover all available commands.

=== tests rules ===

# Test Enforcement

- Test every code change by adding or updating a test.
- Run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== folio/core rules ===

# Laravel Folio

- Laravel Folio is a powerful page-based router that simplifies routing in Laravel applications.
- Routes are generated automatically by creating Blade templates in `resources/views/pages`.
- IMPORTANT: Activate 'folio-routing' when working with Folio, pages, routes, route parameters, model binding, middleware, or `resources/views/pages`.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `bun run build` or ask the user to run `bun run dev` or `composer run dev`.

=== laravel-octane/core rules ===

# Laravel Octane

This application uses Laravel Octane, a long-running PHP server. The application bootstraps once and handles many requests within the same process.

- Never store request-specific state in singletons or static properties, because it can leak across requests.
- Use `config('octane.server')` to detect the active driver (`swoole`, `roadrunner`, or `frankenphp`).
- Prefer scoped bindings (`$this->app->scoped()`) over singletons for per-request services.

When working on Octane-specific features (concurrency, shared tables, memory, driver configuration, testing), invoke `octane-development` for detailed rules.

=== livewire/core rules ===

# Livewire

- Livewire allows you to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

=== spatie/laravel-activitylog/core rules ===

# spatie/laravel-activitylog

Activity logging package for Laravel. Logs model events and manual activities to a database table.

## Key Concepts

- **Activity**: An Eloquent model (`Spatie\Activitylog\Models\Activity`) storing log entries with subject, causer, event, attribute_changes, and properties.
- **Subject**: The model being acted upon (polymorphic `subject_type`/`subject_id`).
- **Causer**: The model that caused the action, typically the authenticated user (polymorphic `causer_type`/`causer_id`).
- **LogOptions**: Fluent configuration object returned by `getActivitylogOptions()` on models using the `LogsActivity` trait.
- **ActivityEvent**: Enum with cases `Created`, `Updated`, `Deleted`, `Restored`.
- **`attribute_changes`** column: stores `{"attributes": {...}, "old": {...}}` for tracked model changes.
- **`properties`** column: stores custom user data set via `withProperties()`.

## Traits

### `LogsActivity`

Add to models to automatically log create/update/delete events. Optionally implement `getActivitylogOptions()` to configure which attributes to track (defaults to logging events without attribute changes).

```php
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Article extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
```

### `CausesActivity`

Add to user/causer models. Provides `activitiesAsCauser()` relationship.

### `HasActivity`

Combines `LogsActivity` and `CausesActivity`. Provides `activities()`, `activitiesAsSubject()`, and `activitiesAsCauser()`.

## Manual Logging

```php
activity()
    ->performedOn($article)
    ->causedBy($user)
    ->event(ActivityEvent::Updated)
    ->withProperties(['key' => 'value'])
    ->log('Article was updated');
```

## LogOptions Methods

| Method | Description |
|--------|-------------|
| `logFillable()` | Log all fillable attributes |
| `logAll()` | Log all attributes |
| `logOnly(array)` | Log specific attributes |
| `logExcept(array)` | Exclude attributes |
| `logOnlyDirty()` | Only log changed attributes |
| `dontLogEmptyChanges()` | Skip logging when no tracked attributes changed |
| `dontLogIfAttributesChangedOnly(array)` | Ignore updates that only change these attributes |
| `useLogName(string)` | Set custom log name |
| `setDescriptionForEvent(Closure)` | Custom description per event |
| `useAttributeRawValues(array)` | Store raw (uncast) values |

## Querying Activities

```php
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Enums\ActivityEvent;

Activity::forEvent(ActivityEvent::Created)->get();
Activity::causedBy($user)->get();
Activity::forSubject($article)->get();
Activity::inLog('orders')->get();
```

## Setting the causer

Override the causer for a block of code:

```php
use Spatie\Activitylog\Facades\Activity;

Activity::defaultCauser($admin, function () {
    // all activities here are caused by $admin
});

// or set globally for the rest of the request
Activity::defaultCauser($admin);
```

## Disabling Logging

```php
activity()->withoutLogging(function () {
    // no activities logged here
});
```

## Accessing Changes and Properties

```php
$activity = Activity::latest()->first();

// Tracked model changes (set automatically by LogsActivity)
$activity->attribute_changes; // Collection: {"attributes": {...}, "old": {...}}

// Custom user data (set via withProperties)
$activity->properties; // Collection
$activity->getProperty('key'); // single value
```

## Custom Activity Model

Set `activity_model` in `config/activitylog.php` to a class that extends `Model` and implements `Spatie\Activitylog\Contracts\Activity`. Use a custom model for custom table names or database connections.

## Customizing Actions

The package uses action classes (`LogActivityAction`, `CleanActivityLogAction`) that can be extended and swapped via config:

```php
// config/activitylog.php
'actions' => [
    'log_activity' => \App\Actions\CustomLogActivityAction::class,
    'clean_log' => \App\Actions\CustomCleanAction::class,
],
```

Custom action classes must extend the originals. Override protected methods (`save()`, `beforeActivityLogged()`, `resolveDescription()`, etc.) to customize behavior.

## Configuration

Key config options in `config/activitylog.php`:
- `enabled`: Master on/off switch (env: `ACTIVITYLOG_ENABLED`)
- `clean_after_days`: Days to keep records for `activitylog:clean` command
- `default_log_name`: Default log name (string)
- `default_auth_driver`: Auth driver for causer resolution
- `include_soft_deleted_subjects`: Include soft-deleted subjects
- `activity_model`: Custom Activity model class
- `default_except_attributes`: Globally excluded attributes
- `actions.log_activity`: Action class for logging activities
- `actions.clean_log`: Action class for cleaning old activities

=== spatie/laravel-medialibrary/core rules ===

## Media Library

- `spatie/laravel-medialibrary` associates files with Eloquent models, with support for collections, conversions, and responsive images.
- Always activate the `medialibrary-development` skill when working with media uploads, conversions, collections, responsive images, or any code that uses the `HasMedia` interface or `InteractsWithMedia` trait.

</laravel-boost-guidelines>

<!-- lerd:begin -->
## Lerd, a local PHP development environment

This project runs on **lerd**, a Podman-based PHP development environment. It is framework-agnostic: Laravel, Symfony, WordPress, Drupal, Magento, CakePHP and any custom framework are all driven by a framework definition (YAML), never by lerd hardcoding a framework's name. The `lerd` MCP server is available — use it to manage the environment without leaving the chat.

The MCP surface is **twelve grouped tools**, each driven by an `action` argument: `site`, `service`, `db`, `env`, `runtime`, `worker`, `exec`, `framework`, `diag`, `logs`, `worktree`, `workspace`. Always pass `action`. Most actions also accept an optional `path` that defaults to the directory the assistant was opened in (then `LERD_SITE_PATH` if set), so you can usually omit it. Start by calling `site` with `action: "list"` to discover sites.

### Architecture

- PHP runs in Podman containers named `lerd-php<version>-fpm` (e.g. `lerd-php84-fpm`); each container includes composer and node/npm; the PHP version is resolved from `.lerd.yaml` → `.php-version` → `composer.json` `require.php` constraint (matched against installed versions) → global default
- Nginx routes `*.test` domains to the correct PHP-FPM container
- Services (MySQL, Redis, PostgreSQL, etc.) and custom services run as Podman containers via systemd quadlets
- Node.js versions run through a version manager, fnm (bundled, fetched on demand) or user nvm (declining managed Node picks nvm, switched with `node:manager`; nvm keeps PATH, fnm uses shims); per-project version via a `.node-version` file. The **package manager** is the project's, not lerd's: a `packageManager` pin in `package.json` wins, then the lockfile (`pnpm-lock.yaml` → pnpm, `yarn.lock` → yarn, `bun.lock*` → bun, else npm). pnpm and yarn run through corepack, and installs use the manager's frozen-lockfile mode (`pnpm install --frozen-lockfile`, `yarn install --immutable`, `npm ci`). Never assume `npm run dev`/`npm ci` — the worker command and the setup steps follow the detected manager
- Framework workers (queue, schedule, reverb, horizon, messenger, vite, etc.) run as systemd user services named `lerd-<worker>-<sitename>`; commands are defined per-framework in YAML; Horizon is auto-detected from `composer.json` and replaces the queue toggle; Laravel ships with a `vite` host worker that runs the project's dev script on the host for HMR, through whichever package manager the project pins; workers and setup commands support optional `check` (`file` or `composer`) for conditional visibility; workers with `conflicts_with` auto-stop conflicting workers on start. Per-worker flags: `host: true` (runs on the host instead of the FPM container, for HMR-sensitive Node tools), `per_worktree: true` (worker runs independently per worktree under `lerd-<worker>-<site>-<branch>`), `replaces_build: true` (provides the asset manifest while running, so a worktree add skips the static build)
- Custom workers can be added per-project (`.lerd.yaml` `custom_workers`) or globally (`~/.config/lerd/frameworks/<name>.yaml`); use the `worker` tool's `add`/`remove` actions — both survive framework store updates
- Framework setup commands (one-off bootstrap steps like migrations, storage links) are defined in the framework YAML and shown by `framework` `action: "setup"`; Laravel has built-in storage:link/migrate/db:seed; custom frameworks can define their own
- Service version placeholders (`{{mysql_version}}`, `{{postgres_version}}`, `{{redis_version}}`, `{{meilisearch_version}}`) are available in framework env vars and resolved from the service image tag at env-setup time
- **Custom containers**: non-PHP sites (Node.js, Python, Go, etc.) can define a `Containerfile.lerd` and a `container:` section in `.lerd.yaml` with a port; lerd builds a per-project image, runs it as `lerd-custom-<sitename>`, and nginx reverse-proxies to it; the project directory is volume-mounted at its host path with `--workdir` set automatically — do NOT add `WORKDIR` or `COPY` to the Containerfile; workers exec into the custom container; services are accessible by name on the shared `lerd` Podman network; **hot-reload file watchers must use polling on macOS** (inotify does not fire across Podman Machine's virtiofs mount) — nodemon: `--legacy-watch`, Vite: `server.watch.usePolling: true`, webpack: `watchOptions: { poll: 1000 }`
- **Custom-image PHP sites (custom-FPM)**: a PHP project can define a `Containerfile.lerd` (must build `FROM lerd-php<ver>-fpm:local`) plus a `container:` section with **no port**; lerd builds a per-site image (`lerd-custom-<site>:local`), runs a dedicated FPM container `lerd-cfpm-<site>`, and serves it by fastcgi instead of the shared `lerd-php<ver>-fpm`. It is a normal PHP site otherwise, with xdebug, dumps, the profiler, `lerd shell` and every worker running in the per-site container. The PHP version is fixed by the `FROM` line (the UI version selector is read-only); `lerd rebuild` rebuilds the image. Same key as custom containers, the port is the discriminator: with one it is a reverse-proxied non-PHP app, without one a fastcgi PHP image. `runtime` for these reports `fpm-custom`.
- Git worktrees automatically get a `<branch>.<site>.test` subdomain (deep `*.<branch>.<site>.test` wildcard cert + nginx `server_name` on secured sites); `vendor/`, `node_modules/`, `.env` are seeded from the main checkout. `.lerd.yaml` `env_overrides` declares templated env vars (`{{domain}}`, `{{scheme}}`, `{{site}}`) layered on the default `APP_URL` rewrite — for multi-tenant apps (per-branch cookies, signed-URL hosts, tenant routing)

### DNS modes

Lerd has two install-time DNS modes recorded in `~/.config/lerd/config.yaml`:
- **Managed (default)**: `dns.enabled: true`, `dns.tld: test`. Sites at `*.test` via lerd-dns + mkcert; `site` `tls_enable` works.
- **Disabled**: `dns.enabled: false`, `dns.tld: localhost`. Sites at `*.localhost` via RFC 6761; no mkcert CA, TLS toggling unavailable.

Read `diag` `action: "status"` for `dns.tld` and `dns.enabled` instead of assuming `.test`; do not propose `tls_enable` when `dns.enabled` is false.

### MCP tools

Twelve grouped tools, each selecting behaviour via `action`.

#### `site` — sites and their configuration
Actions: `list` (discover sites — CALL FIRST), `link`, `unlink`, `domain_add`, `domain_remove`, `group_assign`, `group_unassign`, `group_label`, `group_db`, `group_list`, `tls_enable`, `tls_disable`, `tls_renew`, `php`, `node`, `pause`, `unpause`, `restart`, `rebuild`, `runtime`, `nginx_read`, `nginx_write`, `nginx_reset`, `park`, `unpark`.
- `link` registers a directory; non-PHP sites need `.lerd.yaml` `container.port` + a Containerfile first, or they register as PHP (wrong)
- `link` runs `lerd link` and returns its output verbatim, so read the reply. It will NOT start a `proxy.command` dev server (`command not approved`); ask the user to run `lerd link --yes`
- `domain_*` take a domain without the `.test` TLD; you can't remove the last domain
- `group_*` nest a secondary site under a main's subdomain (one level deep): they identify the secondary by `path` (defaults to cwd), not by `site`; `group_assign` with `main` + `label` (+ optional `share_db`), `group_db` = share|separate
- a group secondary follows its main's HTTPS: `group_assign` under a secured main secures the secondary, `tls_enable` on a main secures its secondaries too, and `tls_disable` on a secondary is refused while its main is secured (the main's `*.<main>` wildcard would answer the subdomain and serve the main's app). Disable the main's TLS first
- certificates renew themselves before they expire; `tls_renew` forces it by hand for one site
- `php`/`node` take `version`; pass `branch` to pin the override on a worktree's checkout
- `runtime` switches `fpm` ↔ `frankenphp` (`worker: true` enables frankenphp worker mode)
- `nginx_write` saves a custom override (runs `nginx -t`, backs up, reloads); `branch` targets a worktree, `scope` picks the file: `server` (default) sits at the end of the server block, `location` inside the block serving the site, the only place a `fastcgi_param` or `proxy_set_header` override takes effect
- `park` registers a parent dir and auto-registers every PHP project under it; `unpark` reverses it (project files kept)

#### `service` — built-in & custom services
Actions: `start`, `stop`, `restart`, `pin`, `unpin`, `update`, `rollback`, `migrate`, `remove`, `reinstall`, `add`, `expose`, `port`, `env`, `config_read`, `config_write`, `config_restore`, `config_reset`, `config_list_backups`, `preset_list`, `preset_search`, `preset_install`, `check_updates`, `entities`, `entity_action`.
- `update` pulls a newer image (safe, in-strategy); `migrate` dumps + restores across a cross-strategy upgrade; `reinstall` with `reset_data: true` wipes data and reprovisions; `remove` with `remove_data: true` renames the data dir aside. Both wipes snapshot every database first (`pre-remove-<ts>` / `pre-reset-data-<ts>`, restore with `db` `restore` + `all_databases`): the renamed data dir only reads back under the image that wrote it, so the dump is the recovery path. A snapshot that fails stops the wipe; `no_snapshot: true` goes ahead without one
- `preset_install`, `update`, `migrate`, `rollback` and `reinstall` disclose an image they would fetch instead of fetching it: the reply names it and its size, nothing is downloaded, and a repeat with `confirm: true` goes ahead. Relay the size first, it is the user's bandwidth. An image already on the machine is never disclosed
- `stop` marks the service paused — `lerd start` skips it until started again; `pin` keeps it always running
- `add` registers a custom OCI service (`depends_on` wires dependencies, `init: true` for mysql/mariadb); prefer `preset_install` for anything in `preset_list` (phpmyadmin, pgadmin, mongo, mongo-express, selenium, stripe-mock, mysql, mariadb…)
- `preset_list` returns the installable presets with the metadata each one declares: `category` (the discovery heading), `icon`, and `admin_for` — the services this preset's admin UI administers, which is **not** `depends_on`. phpMyAdmin depends on mysql but administers mariadb too, and RedisInsight administers valkey without depending on it. To answer "which dashboard administers this database", read `admin_for`, not `depends_on`. `preset_search` queries the store by `name` for presets that are not bundled locally
- `env` returns the recommended `.env` connection keys; `expose` publishes an extra `host:container` port
- `port` moves the service's primary published host port (`published_port`, or `reset: true` for the default); it stays bound to 127.0.0.1, the container-internal port is unchanged, and a host-proxy site that points at the old port is realigned automatically
- `entities` lists what a service holds that is not a database: the kinds its preset declares (RustFS buckets today, more later), each kind's rows and the actions it supports. Databases have their own tool, so they are not repeated here. `entity_action` runs one of those declared actions (`kind`, `entity`, `entity_action`); export and import stream a file and stay on the CLI and the dashboard
- `config_*` read/write/restore/reset a service's runtime tuning override

#### `db` — databases
Actions: `list`, `set`, `move`, `create`, `export`, `import`, `snapshot`, `snapshots`, `restore`, `snapshot_delete`, `extension_list`, `extension_add`.
- `list` reports an engine's databases with sizes; `service` picks the engine, else it resolves from the project. No introspect command, nothing to report
- `set` picks the project DB (`database`: sqlite, mysql, postgres, or a family alternate like mariadb / postgres-pgvector / postgres-timescaledb / mysql-5-7); persists to `.lerd.yaml`, writes the keys the framework declares for that engine, starts the service, creates the DB + `_testing`. sqlite is a wiring the framework declares, not a service: nothing is installed or started and it is not among the site's services, so never report it as stopped or missing. Moving between engines clears the framework's cache, which otherwise serves errors from definitions built against the old database
- `move` migrates sites between two installed same-family services (`from`/`to`, `sites: [...]` or `all: true`) and repoints each `.env`; source data is left intact
- `create`/`export`/`import` auto-detect service and database; pass `service` to override. `import` drops a hosted provider's ownership/DEFINER statements (which can never apply here) and creates any extension the dump's types need; pass `fresh: true` to empty the database first so a dump replaces what is there instead of colliding with it. What an engine can list and act on is declared in its preset, so this is not a mysql/postgres-only set
- `extension_list`/`extension_add` are postgres-only. An `import` already creates whatever extension the dump's types reach for, so use these to see what the engine offers and what the database has, or to add one (`extension: postgis`) before any dump arrives
- `snapshot`/`snapshots`/`restore`/`snapshot_delete` are named, restorable snapshots (MySQL/MariaDB/PostgreSQL); `restore` is destructive; `all_databases` covers the whole service

#### `env` — the file the framework actually reads
Actions: `setup`, `check`, `override`.
- `setup` configures services, DBs, APP_KEY and APP_URL; on a fresh Laravel clone call `db` `set` first to move off sqlite, then `env setup`, then ALWAYS `framework setup` or migrations never run
- the file and format come from the framework definition, not from an assumption of dotenv: a `.env`, WordPress's `wp-config.php` constants, a returned PHP array (Magento's `env.php`, CakePHP's `app_local.php`) or `$var[...]` assignments (Drupal's `settings.php`). Only changed statements are rewritten, so comments and hand edits survive, and a read-only settings file is unhardened for the write and restored after. Never hand-edit these to wire a service, and never assume Laravel's `DB_CONNECTION`/`DB_DATABASE` mean anything on a project that does not declare them
- `check` compares `.env` against `.env.example`. A key a dotenv file sets twice is a `site_doctor` finding, not an error here: lerd reads the first and Symfony reads the last, so the two disagree silently until someone picks one
- `override` manages the personal, gitignored `.env.lerd_override` (its `set` KEY=VALUE win over lerd defaults; `LERD_EXTERNAL_SERVICES=<svc,svc>` marks vars lerd writes but won't start)

#### `runtime` — PHP/Node versions & extensions
Actions: `versions`, `node_install`, `node_uninstall`, `node_manager`, `php_list`, `ext_list`, `ext_add`, `ext_remove`, `ports_list`, `ports_add`, `ports_remove`, `ini_read`, `ini_write`, `ini_reset`.
- `ext_add`/`ext_remove` change one declared set applying to EVERY PHP version, so a site keeps its extensions across a version change. They rebuild one version's FPM container now (slow); others rebuild on next use. `ext_add` accepts `apk_deps` for extra Alpine build packages
- `ext_list` reports the declared set plus, per version: has it, predates the set (rebuild fixes), or cannot load it (rebuild won't). Never assume a declared ext is present: `mongodb` needs 8.1+, 7.4/8.0 are Alpine 3.16
- `node_manager` with no argument reports the version manager lerd drives, whether nvm is present and whether lerd manages Node at all; with `manager: fnm|nvm` it switches, which also rewrites the PATH shims and regenerates host workers
- `php_list` sets `base_update` when the published base image a version was built from has been republished (an upstream PHP or Alpine fix); `lerd php:rebuild <version>` picks it up. 8.6 is an opt-in prerelease tier, FPM only, fetched explicitly like the 7.4/8.0 legacy one
- `ports_add`/`ports_remove`/`ports_list` publish extra host ports on a PHP version's shell (FPM) container so a process started in `lerd shell` is reachable at `localhost:PORT`. `ports_add` takes `host` and optional `container` (defaults to `host`); a busy host port shifts to the next free one. Per version and independent, loopback-bound (follows `lan:expose`), restarts that version's FPM. Prefer host-proxy or a worker+proxy for a single site. CLI: `lerd php:ports add/remove/list [--php version]`
- `ini_read`/`ini_write`/`ini_reset` edit php.ini: pass `version` for a per-version file, or `shared: true` for the shared file applied to every version. The shared file loads below the per-version one, so a per-version key still wins and an unknown key on some version is ignored (not fatal). Prefer shared for a setting you want everywhere, so a version change never drops it. `ini_write` takes full `content`, backs up, and restarts the affected FPM containers. CLI: `lerd php:ini [version|shared]`
- `ext_add` rebuilds the version's image, so a missing base image is disclosed the same way and waits for `confirm: true`
- **extra Alpine packages**: `lerd php:pkg add/remove/list <packages>` (CLI) bakes runtime apk packages (CLI tools, libs) into every FPM image, saved in config under `php.packages` and re-applied on every rebuild, so they survive `php:rebuild` and base image updates. One declared set applies to every PHP version. Layered onto the shared image, not the published base.
- **Pest browser testing (CLI-only)**: `lerd pest:browser install|doctor|remove [version]` sets up `pestphp/pest-plugin-browser` inside the FPM container by baking Alpine's musl chromium and Xvfb into the images. Chromium only, and current PHP versions only (the 7.4/8.0 legacy tier is rejected). Needs the `playwright` npm package first; re-run install after bumping it. Tests then run through the normal `lerd test`/`lerd pest`; `--headed` works too, on a virtual Xvfb display rather than a visible window.
- **bun**: lerd never installs or version-manages bun. On the host, JS install/dev/build run through bun when the project is a bun project (its lockfile or `bunfig.toml`) or when Node is unmanaged, no system Node exists, and bun is present. CLI-only: `lerd node:manage`/`node:unmanage` opt in or out of lerd-managed Node (unmanage drops fnm versions, never a user's nvm ones), `lerd js:runtime [bun|node|auto]` pins one site's runtime, and `lerd php:bun install|update|version` manages an in-container bun for `lerd shell`. These are host operations, not container exec actions.

#### `worker` — background workers
Actions: `list` (CALL FIRST), `start`, `stop`, `add`, `remove`, `health`, `heal`, `mode_get`, `mode_set`, and the framework workers `queue_start`, `queue_stop`, `horizon_start`, `horizon_stop`, `reverb_start`, `reverb_stop`, `schedule_start`, `schedule_stop`, `stripe_start`, `stripe_stop`, `stripe_config`.
- call `list` to discover a site's workers before `start`; pass `branch` to target a per-worktree unit
- use `horizon_*` instead of `queue_*` when laravel/horizon is installed (mutually exclusive); `queue_start` needs Redis running when `QUEUE_CONNECTION=redis`
- `list` reports each worker's tunable `options` (name, definition default, project value); `start`/`queue_start` take them back as `options: ["name=value"]`, persisted to `.lerd.yaml`, so pass one only to change it; an undeclared name is refused
- `add` saves a custom worker to `.lerd.yaml` (or the user overlay with `global: true`); does not auto-start
- `health` reports unhealthy units (read-only); `heal` resets and restarts them (`unit` for one, omit for all); `mode_get` reports the macOS worker runtime, `mode_set` switches it (`mode`: exec|container)
- a worker's health `state` is one of `failed` (the unit died), `expected-but-stopped` (it should be running and isn't), or `unreachable` — the unit is happily active but the server it publishes no longer accepts connections, a dev server that wedged without exiting. All three are heal-able; `heal` restarts the unit. Per-worktree units (`lerd-<worker>-<site>-<branch>`) are covered by the same pass, so a dead per-worktree Vite heals like any other. A worker with a `schedule` is a oneshot driven by a timer and is idle between ticks by design, which is healthy
- Stripe secret is read from `.env` (STRIPE_SECRET / STRIPE_SECRET_KEY / STRIPE_API_KEY); `stripe_config` sets webhook_path / secret_env_key in `.lerd.yaml`
- **Auto-reload (CLI-only)**: `lerd horizon:reload [on|off]` and `lerd octane:reload [on|off]` (FrankenPHP worker mode) restart workers on file changes; both need the project's `chokidar` npm package
- **Idle-suspend (CLI-only)**: `lerd idle on/off` toggles activity-driven suspension globally; suspended workers stop after the idle timeout (`lerd idle timeout <dur>`) and resume on the next request/CLI/MCP/file-save. `lerd idle pin/unpin <site>` exempts a site; `lerd idle status` reports policy and last-active. A worker shown as suspended is healthy, not failed, so do not `heal` it

#### `exec` — run tooling in the PHP-FPM container
Actions: `artisan` (Laravel), `console` (other frameworks), `composer`, `vendor_bins`, `vendor_run`, `commands_list`, `commands_run`, `command_add`, `command_remove`.
- `artisan`/`console`/`composer` take `args` (array); tinker must use `--execute=<code>` for non-interactive use
- `vendor_run` is the right way to run project tooling (pest, phpunit, pint, phpstan, rector) — call `vendor_bins` first to discover what's installed, then `vendor_run` with `bin` + `args`; prefer it over `composer exec`. `lerd cpx <package>` (CLI-only) runs a Composer package's binary without adding it to the project
- `commands_*`/`command_*` list, run, add and remove the on-demand commands in a site's `.lerd.yaml` `commands:` block; `commands_run` needs `force: true` for confirm-gated commands
- **composer over git SSH (CLI-only)**: when `composer` needs a private repo reachable only over SSH, `lerd auth ssh` starts a shared ssh-agent container and loads the host's `~/.ssh/id_*` (or named keys) so passphrase-protected keys work in the FPM container; `lerd auth ssh --list` shows loaded keys, `--remove` flushes them. Keys live only in agent memory and clear on machine restart

#### `framework` — framework definitions & scaffolding
Actions: `list`, `add`, `remove`, `prune`, `search`, `update`, `project_new`, `setup`.
- `add` with `name: "laravel"` merges custom workers/setup into the built-in framework; a worker or command gated on a composer package is declared once in the store as `packages/<vendor>-<name>.yaml` and merged onto the resolved definition, so it is not always in the framework's own file
- `remove` refuses to drop a definition a linked site still uses (pass `force: true` to override); `prune` removes every definition no site uses
- `search`/`update` use the community store; definitions auto-fetch on link, so `update` is the manual refresh (no `name` refreshes the catalogue and all installed definitions; with `name` it fetches that one, auto-detecting version from `composer.lock`)
- `project_new` scaffolds a new project (absolute `path`, default laravel) from the definition the store publishes today, with `version` for an older major, refused when the store has none; follow with `site` `link` + `env` `setup`. `lerd setup --list-steps` prints a project's setup plan as JSON and `--step` runs the named ones, for driving setup piecemeal
- `setup` runs the framework's post-install steps (migrations, storage:link…) — MANDATORY after `env setup` on new/cloned projects; idempotent

#### `diag` — diagnostics & observability
Actions: `status`, `doctor`, `doctor_fix`, `site_doctor`, `which`, `check`, `dns_diagnose`, `bug_report`, `analyze_queries`, `route_timing`, `optimize_route`, `dumps_recent`, `dumps_status`, `dumps_clear`, `dumps_toggle`, `profiler_toggle`, `profiler_status`, `profiler_clear`, `profiler_report`, `xdebug_on`, `xdebug_off`, `xdebug_status`.
- `status` (DNS/nginx/FPM/watcher/tools health) and `doctor` (JSON findings, each tagged with a fix tier) are the first stops when something is broken; `dns_diagnose` walks the DNS chain
- `doctor_fix` applies the safe (non-heavy, non-sudo) automatic repairs for environment findings; package installs, `lerd install`, and `lerd cleanup` stay manual
- `site_doctor` runs framework-agnostic app-level checks for one site (env file, env drift, app key, composer/node dependency install + lock, `composer audit`/`npm audit`, PHP range, a `slow_routes` warning for routes whose p95 runs well above the site's typical time or over a second, plus the framework's own checks); pass `site` (name or domain) or `path`, defaults to cwd. A failing check carries a `severity` and, when one applies, a `fix` naming the command that resolves it — run that yourself (`exec`, or the named lerd command); site_doctor itself is read-only. Host-side fixes (installing or starting a service the site declares, rewriting a drifted nginx vhost, repointing a project at the database it picked, creating a database its engine lacks, deleting units of workers the site no longer declares) belong to `lerd site:doctor --fix`, which the user runs. `slow_routes` is the exception: read from the watcher's timing snapshot, no command fix, profile the route instead (`profiler_toggle`)
- reading logs lives in the `logs` tool (below), not here
- `which` shows resolved PHP/Node/docroot/nginx for a site; `check` validates `.lerd.yaml`
- debug bridge loop: `dumps_toggle` (enable) → `dumps_clear` → hit the page → `analyze_queries` (N+1 / slow-query report with file:line) or `dumps_recent` (filter by site/branch/ctx/kind/since/limit)
- `route_timing` returns the per-site response-time table: the typical (median) time and the routes whose p95 runs well above it (method, example path, p95, multiplier, samples), read from the watcher's snapshot of real traffic, no capture needed. `site` accepts either the site name or its domain, as do `analyze_queries`, `optimize_route`, and `dumps_recent`
- `optimize_route` is the join: each slow route paired with the N+1 and slow-query findings captured against that same route (with the caller file:line), so you get the symptom and its cause in one call. Needs the query capture on (`dumps_toggle` enable) plus a few real hits. When the SPX profiler was on for the route's traffic, each slow route also carries a `profile` block, the top functions by exclusive wall time from the freshest capture, distilled to a few outliers (not the raw trace), so a CPU-bound route shows where its time went next to its queries
- **optimizing a slow site**: don't read controllers to guess at N+1s, drive it from real traffic. `route_timing` to see which routes are slow → `dumps_toggle` (enable) and `profiler_toggle` (enable) → hit the slow route a few times → `optimize_route` to get its N+1/slow queries with file:line and, from the profiler, the top CPU functions behind it → fix the caller (eager-load, index, cache) or the hot function
- **stay ahead of regressions**: after changing request handling or database code, enable dumps, hit the affected route and run `optimize_route` before moving on, and treat a new N+1 or `slow_routes` warning as work to finish rather than noise. Timing is a live in-memory signal, so a route you fixed clears itself; the durable catch is the `slow_route` push notification. Periodic checking belongs to the user's own scheduler, not to lerd.
- `profiler_*` toggle the global SPX profiler and surface the flame-graph UI; `profiler_report` (site + `args`, e.g. `["artisan","app:heavy-report"]`) runs that command under SPX and returns a text flat profile, the top functions by wall time and call count, the CPU-bound analog of `analyze_queries` for when a slow route's cost is not in its queries (a reproducible CLI command, not a live HTTP request); `xdebug_*` control Xdebug on port 9003 (`mode` defaults to debug)
- `bug_report` writes an anonymised diagnostic report for a GitHub issue
- **disk cleanup (CLI-only)**: `lerd cleanup` reclaims podman disk from orphaned lerd images (`--dry-run` to preview, `--deep` for the aggressive tier); a daily safe-tier sweep plus post-rebuild/service-change reaping runs automatically, toggled with `lerd cleanup auto on|off|status`

#### `logs` — read logs from any source, filtered
Actions: `sources`, `fetch`. Debug without opening files by hand.
- `sources` lists every queryable source for the site plus shared infra: `app:<file>` (framework log files), `fpm`, `worker:<name>` (queue/horizon/schedule/custom), and the globals `nginx`, `dns`, `watcher`, `ui`, services, `php<ver>`. Call it first to learn the names
- `fetch source=<name>` reads one source. Filter with `grep` (regex, falls back to literal substring), `since`/`until` (relative like `15m`/`1h`/`2h30m`, or a timestamp), `level` (app logs only: error/warning/info/debug), and `lines` (default 50)
- streaming is polling: every `fetch` returns an opaque `cursor`; call again with `since=<cursor>` (or `cursor=<cursor>`) to get only the new lines. The cursor format differs per backend, so treat it as opaque and echo it back
- entries come back chronological (oldest first). Raw logs with no timestamps ignore `since`/`level` and just return the last N; a not-running container returns partial output, not an error

#### `worktree` — git worktrees
Actions: `list`, `add`, `remove`, `wait`, `db_isolate`, `db_share`.
- `add` installs deps and offers an asset-worker / build-step prompt; secured sites get `*.<branch>.<site>.test` wildcard cert SANs + nginx `server_name` automatically. It waits for setup and reports `provisioned` (`false` + note means still running, not failed; `timeout_seconds` default 300)
- `wait` is that readiness check alone, for a worktree made with plain `git worktree add`. **Never** judge readiness from the tree: `node_modules/` exists from the first extracted package and composer fills *existing* `vendor/<org>/` dirs, so both read as finished mid-install, and racing the watcher is how `vendor/` ends up with no `autoload.php`
- `db_isolate` gives a worktree its own database (seed via `source`: empty|main|<branch>); `db_share` points it back at the main; `remove` keeps an isolated DB unless `keep_db: false`
- a framework definition can declare what its worktrees need (an isolated database, what it is cloned from, console commands to run once it is in place), so `add` does that work rather than leaving it to be run by hand
- request timing is recorded per worktree; pass `branch` to `route_timing`, `optimize_route` and `dumps_recent` to read one branch's traffic

#### `workspace` — group sites for display
Actions: `list`, `create`, `rename`, `delete`, `assign`, `move`.
- a workspace is a **display-only** bucket of sites, shown in the dashboard sidebar and the TUI. It never touches nginx, domains, certificates or `.env`. This is not the same thing as the `site` tool's `group_*` actions, which nest a real site under another's subdomain and regenerate vhosts and certs — reach for `group_*` when a site should be served at `<label>.<main>.test`, and for `workspace` when the user just wants their site list organised
- `assign` takes `sites` (names or domains) and a `workspace`, creating it if new; `workspace: "none"` ungroups them. `move` reorders a workspace with a zero-based `position`
- `delete` drops the workspace and ungroups its members; no site is touched

### Key conventions

- Pass `action` on every tool; `path` is optional on most and defaults to the directory the assistant was opened in
- Discover before acting: `site` `list` for sites, `worker` `list` for a site's workers, `service` `preset_list` before `preset_install`, `exec` `vendor_bins` before `vendor_run`
- On a fresh Laravel clone (DB_CONNECTION=sqlite), call `db` `set` before `env` `setup` to choose a database deliberately, then run `framework` `setup`
- **Domain conflicts on link**: a link drops a domain another site owns (reported in `warnings`) and registers the survivors, falling back to `<dirname>.<tld>`; `.lerd.yaml` is untouched. `domain_add` still hard-errors
- **Custom APP_URL**: `env` `setup` writes `<scheme>://<primary-domain>`; override via `app_url` in `.lerd.yaml` (committed) or the per-machine `sites.yaml` entry, then re-run `env setup`
- Built-in service hosts follow `lerd-<name>` (e.g. `lerd-mysql`, `lerd-redis`, `lerd-postgres`); default DB credentials are username `root`, password `lerd`
- **Custom container sites** (Node.js, Python, Go, …) — mandatory order: (1) write a Containerfile (default `Containerfile.lerd`); (2) write `.lerd.yaml` with `container: {port: <N>}` (plus optional `domains`, `services`, `secured`); (3) configure the project's `.env` with service hosts (`lerd-mysql`, etc.) and start needed services via `service` `start`; (4) call `site` `link`. Never link before steps 1–3 or the site registers as PHP-FPM; if that happens, `site` `unlink`, write the files, then link again
- Worker unit names follow `lerd-<worker>-<site>` (per-worktree: `lerd-<worker>-<site>-<branch>`)
- **Opening a project (CLI-only)**: `lerd open` sends the site to the browser, `lerd code` the directory to the configured editor; inside a worktree both act on the checkout you are standing in
- **lerd's own lifecycle is CLI-only**: no tool starts, stops or updates lerd; when it is down, hand the user `lerd start`
- **Host tools (CLI-only)**: `diag` `status` reports Composer, fnm and mkcert against the versions lerd pins, and flags any that differ. Applying an update is `lerd tools:update`, and the optional tray applet is `lerd tray off|on`; neither has a tool here, so tell the user to run it
- **Sharing a site is CLI-only and deliberate**: `lerd share` (ngrok, cloudflared, Expose, serveo, localhost.run, Pinggy) and the dashboard's share menu put a site on the public internet, the same menu's public share serves it through the user's own reverse proxy on a base domain they control instead of a tunnel service, and `lerd lan:expose` puts it on the local network. None is exposed here, so never claim you can share a site; hand the user the command and let them decide

<!-- lerd:end -->
