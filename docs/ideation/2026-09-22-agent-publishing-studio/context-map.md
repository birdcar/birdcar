# Context Map: 2026-09-22-agent-publishing-studio

**Phase**: 2
**Gates**: 5/5 ready
**Verdict**: GO

> Extends prior context map. Prior Phase 5 findings are retained below; current Phase 2 focus is the post-commit corrective follow-up for Root Admin invitations after `run-2026-09-23-6.json`.

## Gates

| Gate | Status | Evidence |
| --- | --- | --- |
| Scope clarity | ready | Corrective scope is concrete: update `app/Actions/Admin/InviteAdministrator.php` mailer validation, update `tests/Feature/Auth/AdminInvitationTest.php` for custom/effective transports, URL overrides, malformed email, nondefault Admin port, custom expiry, and deterministic delivery failure; preserve existing invitation files and immutable `docs/ideation/.../run-*` receipts. |
| Pattern familiarity | ready | Read spec pattern files `app/Console/Commands/SyncAuthorization.php`, `app/Authorization/Admin/{Role,Catalog}.php`, `app/Authorization/Publishing/{Role,Catalog}.php`, `app/Models/User.php`, existing auth views/responses/tests, and Laravel `MailManager`/`Mailer` transport APIs. |
| Dependency awareness | ready | Grep mapped consumers: `InviteAdmin` is the console entrypoint for `InviteAdministrator`; `AdminInvitation` is produced by `InviteAdministrator` and asserted by tests; Fortify reset response/view are bound in `FortifyServiceProvider`; no other app consumers found. |
| Edge case coverage | ready | Edge cases are explicit: custom safe/unsafe mailer creators, URL transport override to unsafe/null, unresolved/unsupported mailers, cyclic failover/roundrobin, nested unsafe children, MailManager cache consistency, malformed email no mutation, nondefault Admin port, custom broker expiry, deterministic post-provision delivery failure and retry. |
| Test strategy | ready | Use Pest feature tests with `RefreshDatabase`; inner loop is `php artisan test --compact tests/Feature/Auth/AdminInvitationTest.php`, then spec validation command including `AdminFortifyFlowTest`, Authorization, Pint, PHPStan, and build. |

## Key Patterns

- `app/Console/Commands/SyncAuthorization.php` — Artisan commands use typed `handle(...)`, catch domain exceptions for friendly output, return `Command::SUCCESS`/`FAILURE`, preflight config before mutation, and avoid silent authorization catalog creation.
- `app/Authorization/Admin/Role.php` and `app/Authorization/Publishing/Role.php` — root bootstrap roles are backed enums with string values `admin.access` and `publishing.author`.
- `app/Authorization/Admin/Catalog.php` and `app/Authorization/Publishing/Catalog.php` — role definitions are explicit catalog arrays mapping enum roles to enum permissions; invitation command should require synced roles, not define them.
- `app/Models/User.php` — User is the single identity, has `HasRoles` and `Notifiable`, fillable `name/email/password`, and hashed password cast; invitations should create/reuse Users without type flags.
- `app/Http/Responses/AdminLoginResponse.php` and `AdminLogoutResponse.php` — Admin auth redirects are based on trusted `config('admin.url')`, preserving scheme/host/port and JSON behavior.
- `resources/views/auth/login.blade.php` and `resources/views/auth/reset-password.blade.php` — compact Admin guest pages, testing skips Vite, reset form posts to Fortify `password.update`, avoids referrer leakage, and displays first validation error/status.
- `tests/Feature/Auth/AdminFortifyFlowTest.php` — Pest tests set `admin.url`, run `authorization:sync`, use factories/Hash/Password broker, and assert redirect origins including nondefault ports.
- `tests/Feature/Auth/AdminInvitationTest.php` — current invitation coverage uses `Artisan::call`, `Notification::fake`, real broker tokens, role assertions, no direct permissions, no setup URL in output, and failure preflight assertions.
- `vendor/laravel/framework/src/Illuminate/Mail/MailManager.php:64-148` — public `mailer($name)` resolves and caches named mailers; `build()` constructs a `Mailer` with `createSymfonyTransport()`.
- `vendor/laravel/framework/src/Illuminate/Mail/MailManager.php:167-181` — public `createSymfonyTransport()` honors registered `customCreators` before built-in transport factories, so hardcoded transport-name allowlists miss supported custom creators.
- `vendor/laravel/framework/src/Illuminate/Mail/Mailer.php:632-634` — public `getSymfonyTransport()` exposes the resolved effective transport for a mailer.
- `vendor/laravel/framework/src/Illuminate/Mail/MailManager.php:411-456` — failover/roundrobin are built from configured child mailer names; Symfony aggregate transports do not provide a public child getter, so child configs must be recursively validated by name before resolution.

## Dependencies

- `app/Actions/Admin/InviteAdministrator.php:21-51` — consumed by → `app/Console/Commands/InviteAdmin.php:30-55`, `tests/Feature/Auth/AdminInvitationTest.php` command executions.
- `app/Actions/Admin/InviteAdministrator.php:102-162` — mailer preflight currently private but affects every `admin:invite` path; must be updated to use `MailManager::mailer($name)->getSymfonyTransport()` and reject resolved `LogTransport`, `ArrayTransport`, Symfony `NullTransport`, and unverifiable/unsafe aggregates.
- `app/Actions/Admin/InviteAdministrator.php:194-228` — bootstrap role config consumed from `config/admin.php:13-16`; tests delete roles to assert preflight failure.
- `app/Actions/Admin/InviteAdministrator.php:240-280` — user provisioning consumed by invitation command/tests; creates/reuses User, assigns roles with `assignRole`, and must stay before delivery.
- `app/Console/Commands/InviteAdmin.php:18-55` — discovered by Laravel command auto-registration; consumed by tests through `Artisan::call('admin:invite', ...)` and future operator procedure in `spec-phase-6.md`.
- `app/Notifications/AdminInvitation.php:18-53` — produced by `InviteAdministrator`; tests inspect `toMail()` action URL, intro lines, token, and should add expiry/port assertions.
- `app/Http/Responses/AdminPasswordResetResponse.php:11-22` — bound by `app/Providers/FortifyServiceProvider.php:37`; consumed by `AdminFortifyFlowTest.php:84-113` and invitation password setup tests.
- `resources/views/auth/reset-password.blade.php:1-16` — registered by `FortifyServiceProvider.php:52-59`; consumed by GET setup-link tests.
- `resources/views/auth/login.blade.php:7` — status display consumed by `AdminFortifyFlowTest.php:84-99`.
- `config/admin.php:1-16` — consumed by login/logout/reset responses and invitation origin/bootstrap role preflight.
- `docs/ideation/2026-09-22-agent-publishing-studio/run-2026-09-23-6.json` — documents the three corrective findings; preserve all `run-*.json`/`run-*.html` receipts.

## Conventions

- **Naming**: Actions are verb phrases (`InviteAdministrator`), commands are imperative (`InviteAdmin`), notifications name the domain event (`AdminInvitation`), tests use behavior-style Pest `test(...)` names.
- **Imports**: PHP classes use explicit `use` imports; views use Blade helpers directly; tests import facades/models/enums at top.
- **Error handling**: Domain failures throw `AdminInvitationException` subclasses; command catches and emits sanitized messages; delivery/broker failures honestly warn that account/roles may already be provisioned.
- **Types**: Methods use explicit parameter/return types; result DTO is `final readonly`; PHPDocs document list/array shapes; sensitive reset token parameter uses `#[\SensitiveParameter]`.
- **Testing**: `tests/Pest.php` applies `Tests\TestCase` and `RefreshDatabase` to Feature tests; `phpunit.xml` uses SQLite in-memory, array mail by default, sync queue, disabled PostHog/Nightwatch. Use `Notification::fake()`, `Artisan::call()`, factories, broker tokens, and `travel()` for throttle/expiry.
- **Rules read**: `.ai/rules/index.md`, `general.md`, `authorization.md`, `resources.md`, `services.md`; `CLAUDE.md`/`AGENTS.md` reinforce Laravel/Pest/Pint and authorization constraints.

## Risks

- Current `InviteAdministrator::effectiveTransportFor()` hard-codes accepted transport names at `app/Actions/Admin/InviteAdministrator.php:158`, matching the strict-review finding and missing installed custom creators/effective URL resolution.
- Current delivery-failure regression uses a real localhost SMTP connection on port 65000 (`tests/Feature/Auth/AdminInvitationTest.php:194-230`); spec requires deterministic fake failure at notification/mail boundary with no network.
- `Notification::fake()` prevents actual MailManager transport use, so new tests for effective transport validation need deliberate configuration/custom transport setup without accidentally sending live mail.
- MailManager caches resolved mailers (`vendor/laravel/framework/src/Illuminate/Mail/MailManager.php:64-91`); validation and send path should use the same safe effective mailer or purge/rebuild deliberately in tests.
- Aggregate transports lack public child getters; prevalidation must inspect configured child names recursively and fail closed rather than reflect into Symfony transports.
- `AdminInvitationTest.php` currently lacks malformed-email coverage and lacks nondefault Admin port/custom expiry assertions noted in `run-2026-09-23-6.json`.
- Preserve prior phase artifacts: do not edit/delete `docs/ideation/2026-09-22-agent-publishing-studio/run-*.json` or `run-*.html`.

## Current Phase Builder Notes

- Likely primary code change: replace `InviteAdministrator::effectiveTransportFor()`/`parsedMailerConfig()` hardcoded whitelist with a validator that first recursively prevalidates aggregate child names/cycles from config, then resolves named mailers through the installed `Illuminate\Mail\MailManager::mailer($name)->getSymfonyTransport()` public API.
- Reject resolved instances of `Illuminate\Mail\Transport\LogTransport`, `Illuminate\Mail\Transport\ArrayTransport`, and `Symfony\Component\Mailer\Transport\NullTransport`; reject unsupported/unresolvable mailers and unverifiable aggregates with sanitized messages.
- Cover custom creators by registering test mail transports through MailManager’s `extend()` API; include both safe custom transport and unsafe alias returning log/array/null.
- Cover URL overrides because MailManager parses `url` effective config; a `MAIL_URL`/mailer `url` that resolves to log/array/null must be rejected before provisioning.
- Replace the port-65000 test with a deterministic exception from notification/mail boundary while still asserting account/roles were committed, output hides token/URL/provider details, and retry later succeeds.
- Add malformed email command test asserting failure, no User, no role pivot mutation, and no notification.
- Add nondefault Admin URL port + custom `auth.passwords.users.expire` test asserting the action URL preserves the port and the email text contains the configured expiry.

## Validation Commands

```bash
php artisan test --compact tests/Feature/Auth/AdminInvitationTest.php
php artisan test --compact tests/Feature/Auth/AdminInvitationTest.php tests/Feature/Auth/AdminFortifyFlowTest.php tests/Feature/Authorization tests/Feature/Publishing/PublishingAuthorizationTest.php
vendor/bin/pint --dirty --format agent
composer types:check
bun run build
```

## Prior Phase 5 Context Retained

### Prior Key Patterns

- `tests/Feature/MarketingDiagramTest.php` — Pest feature tests use expressive `test(...)` blocks, direct service calls, host/route assertions, and config toggles.
- `app/Services/MarketingSite.php` — Small config-driven service with explicit typed methods and fail-fast `RuntimeException` for invalid config.
- `app/Actions/Publishing/ManageArticleRelease.php` — Domain action pattern: authorize first, mutate under `DB::transaction`, lock rows consistently, keep release packages immutable, and throw user-actionable exceptions.
- `app/Services/Publishing/ReleaseFreshnessManifest.php` — Deterministic manifest source of truth for freshness and release hashes.
- `app/Services/Publishing/PublishingFingerprint.php` — Canonical SHA-256 JSON hashing with sorted associative keys and preserved list order.

### Prior Dependencies

- `app/Actions/Publishing/ManageArticleRelease.php` — consumed by publishing tests, Livewire workspace, scheduler command, and public writing tests.
- `app/Actions/Publishing/CheckArticleRelease.php` — consumed by `ManageArticleRelease` readiness checks and release-readiness tests.
- `app/Actions/Publishing/ReadPublishedWriting.php` — consumed by routes, writing Folio pages, public-writing tests, and marketing/discovery tests.
- `config/publishing.php` — `publishing.public_reader` consumed by publishing reader/delivery code and tests.

### Prior Risks

- Scheduled release intent validation was tightened in Phase 5; fixtures using only a UTC instant needed explicit timezone/wall-time intent.
- Release package immutability means fixes must happen before create; do not mutate historical artifacts.
- SQLite tests cover behavior but not full row-lock concurrency.
- Controller artifacts under `docs/ideation/2026-09-22-agent-publishing-studio/run-*.json` and `run-*.html` are immutable receipts.

### Prior Phase 5 Validation Commands

```bash
php artisan test --compact tests/Feature/Publishing/ReleaseIntegrityTest.php
php artisan test --compact tests/Feature/Publishing/ReleaseReadinessTest.php tests/Feature/Publishing/ReleaseIntegrityTest.php tests/Feature/Publishing/PublicWritingTest.php tests/Feature/Publishing/ArchiveMigrationTest.php
php artisan test --compact tests/Feature/MarketingSiteTest.php tests/Feature/MarketingDiagramTest.php tests/Feature/MarketingDiscoveryTest.php
vendor/bin/pest --compact --fail-on-skipped --fail-on-incomplete tests/Feature/Publishing
vendor/bin/pint --dirty --format agent
composer types:check
bun run build
git diff --exit-code 72f7d8ad8521573cb224022c902447f9ca4c4351 -- resources/writing
```
