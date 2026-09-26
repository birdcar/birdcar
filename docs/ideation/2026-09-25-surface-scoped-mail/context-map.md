# Context Map: Surface-Scoped Mail

**Phase**: 3
**Gates**: 5/5 ready
**Verdict**: GO

## Gates

### Phase 3 (current)

| Gate | Status | Evidence |
| --- | --- | --- |
| Scope clarity | ready | Every file in spec-phase-3.md is named with a concrete change, and I found each target. **New:** `app/Authorization/Mail/{Permission,Role,Catalog}.php` (the directory doesn't exist yet), `resources/views/components/admin/mail/⚡settings.blade.php` (a new `mail/` directory beside `publishing/`), and `tests/Feature/Mail/AdminMailSettingsPageTest.php`. **Modified:** `config/authorization.php:3-14`, `config/admin.php:3-16`, `routes/admin.php:1-8`, `resources/views/layouts/admin.blade.php:1-3,27-29`, `resources/views/mail/admin/invitation.blade.php:4`, `tests/Feature/Auth/AdminInvitationTest.php:53,62,126,428`, `tests/Feature/Authorization/AuthorizationSyncCommandTest.php:16-54` and `.ai/rules/authorization.md:9`. The spec misses four stale records, each with a concrete fix (see Risks, Phase 3): `docs/development-setup.md:137` and `docs/production-setup.md:91` both name the two-role bundle; `layouts/admin.blade.php:47` would label the Mail page's header "Home"; and `DESIGN.md:395` says only Home and Publishing appear in the sidebar. |
| Pattern familiarity | ready | I read the domain pattern (`app/Authorization/Publishing/*`, `Admin/*`, `Contracts/AuthorizationCatalog.php:7-21`). I read the whole Publishing settings SFC (`⚡settings.blade.php:1-305`) and its tests (`PublishingAgentSettingsTest.php:23-57` for setup and helpers, `:239-480` for page tests). I read the Phase 1 settings API (`SurfaceMailSettings.php:31-88`), the seeds (`2026_09_25_120000_create_surface_mail_settings.php:9-14`) and the sidebar item (`layouts/admin.blade.php:27-29`). Vendor behaviour is confirmed for Livewire 4.4.4 `validate()`, the TrimStrings skip, Laravel `email` (RFC by default), spatie/laravel-permission 8.3 `PermissionMiddleware::using(BackedEnum)`, spatie/laravel-settings 3.9 `refresh()`, Boost 2.7.1 `appendEntry()` and the Flux 2.19 `envelope` icon. |
| Dependency awareness | ready | Blast radius: the new catalog is picked up by `authorization:sync` (`app/Console/Commands/SyncAuthorization.php`), which runs in every relevant test `beforeEach`, the Cloud deploy and `docs/production-setup.md:78`. `bootstrap_roles` is read by `InviteAdministrator.php:37-38,255-290,333` and printed at `InviteAdmin.php:55`. `routes/admin.php` is mounted by `routes/web.php:19-25`. The layout wraps every Admin page. The invitation view is consumed by `InvitationMail.php:26` and asserted at `AdminInvitationTest.php:53`. A settings save is read by `AdminMailable.php:18`, `MarketingMailable.php:18` and `InviteAdministrator.php:129`. The arch rules (`tests/Arch/MailTest.php:25-33`) match the `App\Mail` name prefix, so `App\Authorization\Mail\*` isn't swept in. No existing test counts sidebar items or real-config roles. |
| Edge case coverage | ready | The concrete list is under Risks (Phase 3). It covers: a null key misreported as configured; a real local Resend key in tests; RFC `email` versus the settings class's `filter_var`; CR/LF in `from_name`; Livewire skipping TrimStrings; blank reply-to becoming null; `resetErrorBag()` clearing both surfaces; subtree-only validation and tampered payloads; the ordering of authorization and the unknown-surface check; a corrupt stored payload on mount; `record-rule` appending instead of replacing; the pinned catalog list in the sync test; global test-helper name collisions; and the local `admin:invite` re-run sending real mail. |
| Test strategy | ready | Inner loop: `vendor/bin/pest tests/Feature/Mail/AdminMailSettingsPageTest.php`. Then `vendor/bin/pest tests/Feature/Auth/AdminInvitationTest.php tests/Feature/Authorization`. Then `php artisan authorization:sync --no-interaction` plus `php artisan route:list --name=admin.mail`, which should show `admin.birdcar.test/mail` as `admin.mail.settings`. Then `vendor/bin/pint --dirty --format agent` and `vendor/bin/phpstan analyse --no-progress --memory-limit=1G`, which covers app/, config/ and routes/ but not the SFC. Then the full `php artisan test --compact` (Unit, Feature, Arch), and the contract env grep (`contract.md:34`) again after the rule and doc edits. Finally `bun run build` and a Herd check at the URL from Boost `get-absolute-url`, in light and dark. |

### Phase 2 (prior, for reference)

| Gate | Status | Evidence |
| --- | --- | --- |
| Scope clarity | ready | Every file in spec-phase-2.md is named, with a concrete change for each, and I found each target. **New:** `app/Listeners/RejectUnscopedMail.php` (`app/Listeners` doesn't exist yet), `tests/Feature/Mail/SurfaceMailGuardTest.php`, `tests/Arch/MailTest.php` (`tests/Arch` doesn't exist yet), and the Boost rule file. **Modified:** `phpunit.xml:8-15` (add the Arch suite after Feature), `docs/production-setup.md:58` (the single MAIL_* bullet), `docs/development-setup.md:112-125`. Two small gaps have concrete fixes. (1) Guard cases (b) and (f) need fixtures the New Files table omits: a `MailMessage` notification and a plain non-surface `Mailable`. Add them to `tests/Fixtures/Mail/`, which arch never scans. (2) With the spec's brace glob, Boost `record-rule` writes `.ai/rules/listeners.md`, not `mail.md`; see Risks. |
| Pattern familiarity | ready | I read the Phase 1 code the guard keys on: `SurfaceMailable.php:19,26-39,44-59`, `AdminMailable.php:11-14`, `MarketingMailable.php:11-14`. I read the fixtures (`ExampleAdminMail`, `ExampleMarketingMail`, `ExampleSurfaceNotification`, `RecordingTransport`) and the Phase 1 test idioms (`SurfaceSenderSettingsTest.php:17-40`, `AdminPasswordResetTest.php:36-56`). I read the pest-plugin-arch 5.0.0 source for `not->toBeUsed`, `toOnlyBeUsedIn`, `toExtend`, `not->toImplement`, `classes()` and `implementing()`, the Boost `RecordRule`/`RuleRepository` source, and both doc blocks. Every vendor seam the spec cites matches Laravel v13.30.1. |
| Dependency awareness | ready | The guard sits in front of every real send. Current senders: `InviteAdministrator.php:39-55`, which Throwable-wraps into `AdminInvitationDeliveryException` (caught at `InviteAdmin.php:37-41`), and `User.php:81` → queued `PasswordReset`, which becomes a failed job. The other `MessageSending` listener is Nightwatch (`NightwatchServiceProvider.php:359`); it returns void, so `until()` isn't short-circuited. Existing real-send tests all send surface mail on the matching mailer, so they stay green. `phpunit.xml` is consumed by `composer test`/`ci:check` and `.github/workflows/tests.yml`. No framework or package mail path is active today (see Dependencies). |
| Edge case coverage | ready | The concrete list is under Risks (Phase 2). It covers `Mail::to()->send()` using the default mailer instance, vacuous passes when default and surface mailer names match, redeclared global Pest helpers, the `\Mail::` alias the arch rules can't see, non-PSR-4 code (routes, config, Blade, Livewire SFCs) the arch rules skip, the anonymous-class NUL byte in messages, the rule-file name, the same-address envelope-name gap to document, and the make:listener stub's empty constructor. |
| Test strategy | ready | Inner loop: `vendor/bin/pest tests/Feature/Mail/SurfaceMailGuardTest.php`. Arch: `php artisan test --compact --testsuite=Arch` (`failOnEmptyTestSuite="true"` at `phpunit.xml:6` fails an empty or unregistered suite). Discovery: `php artisan event:list --event='Illuminate\Mail\Events\MessageSending'`, which today lists only the Nightwatch closure. Then `vendor/bin/pint --dirty --format agent`, `vendor/bin/phpstan analyse --no-progress --memory-limit=1G` (app/ is in `phpstan.neon` paths; tests are not), and the full `php artisan test --compact`. The contract env grep passes today; re-run it after the doc and rule edits. CI is still red at composer install (Phase 1 note), so local runs are the only signal. |

### Phase 1 (prior, for reference)

| Gate | Status | Evidence |
| --- | --- | --- |
| Scope clarity | ready | Every new and modified file in spec-phase-1.md is named with a concrete change, and I found each edit target: `config/admin.php:6`, `config/marketing.php:4-5`, `config/services.php:21-23`, `config/mail.php:64-66`, `InviteAdministrator.php:108-117`, `AdminInvitation.php:25-55`, `.env.example:72,74,76`, `docs/production-setup.md:41-43`, `docs/development-setup.md:47-49,59,298`, `.ai/rules/general.md:21`. |
| Pattern familiarity | ready | I read the settings pattern (`PublishingAgentSettings.php`, its settings migration, and the `DB::table('settings')` + `forgetScopedInstances()` idiom in `PublishingAgentSettingsTest.php`), the config pattern (`config/admin.php`), and the notification and test patterns (`AdminInvitation.php`, `AdminInvitationTest.php`). Every vendor seam the spec cites matches Laravel v13.30.1. |
| Dependency awareness | ready | The blast radius is mapped: InviteAdministrator ← `InviteAdmin` command; AdminInvitation ← InviteAdministrator:42 and about 12 assertions in AdminInvitationTest; the `admin.*`/`marketing.*` config keys are unchanged (only their env sources move); the stock `resend` mailer and `services.resend` have no consumers; local tests depend on the `.env` admin host. |
| Edge case coverage | ready | The concrete list under Risks includes: envelope `from` with the same address can override the name; the arrow-fn capture bug in the spec's recording snippet; `MissingSettings` isn't caught by the invite command; `User` override typing vs phpstan; the shared `array` transport across surfaces; quoted-printable URLs in raw bodies; `.env` rename ordering. |
| Test strategy | ready | Inner loop: `vendor/bin/pest tests/Feature/Mail tests/Feature/Auth/AdminInvitationTest.php`. Then `vendor/bin/pint --dirty --format agent`, `vendor/bin/phpstan analyse --no-progress --memory-limit=1G`, `php artisan test --compact`, and the contract env grep. Settings migrations run under RefreshDatabase (Publishing test asserts seeded rows). The testing transaction manager runs after-commit callbacks at level 1, so a `ShouldQueueAfterCommit` notification on the sync queue delivers at once. CI is red at "Setup Application" (composer install) before tests run, so local runs are the only signal. |

## Key Patterns

### Phase 3

- **Authorization domain: `app/Authorization/Publishing/{Permission,Role,Catalog}.php`, plus the single-permission `Admin/*`.**
  - The enums are string-backed and named `Permission` and `Role` in `App\Authorization\{Domain}`, with values `{domain}.{kebab-action}` and `{domain}.{role}`. Existing examples: `publishing.configure-agents` (`Publishing/Permission.php:12`), `publishing.author` and `admin.access`.
  - `final class Catalog implements AuthorizationCatalog` has `permissions(): array`, which returns a list of cases, and `roles(): array`, which returns `[['role' => Role::X, 'permissions' => [...]]]`. The methods carry no PHPDoc; the interface holds the shapes (`AuthorizationCatalog.php:9-20`).
  - `Admin/Catalog.php:9-26` is the exact one-permission, one-role shape Mail needs.
- **Config wiring.**
  - `config/authorization.php:3-5` has aliased `use …\Catalog as {Domain}Catalog;` imports, and `:10-14` lists them alphabetically (Admin, Organizations, Publishing). Insert `MailCatalog` between Admin and Organizations in both places.
  - `config/admin.php:3-4` has `use …\Role as {Domain}Role;`, and `:13-16` lists `->value` entries. Append `MailRole::Operator->value`.
- **Route: `routes/admin.php:8,16-18`.**
  - Pattern: `Route::livewire('/path', 'dotted.component')->name('x')->middleware(PermissionMiddleware::using(Enum))`. `using()` accepts `array|string|BackedEnum` (`PermissionMiddleware.php:48`).
  - `routes/web.php:19-25` wraps the file with the admin domain, `auth` and `PermissionMiddleware::using(AdminPermission::View)`, plus the name prefix `admin.`. So `->name('mail.settings')` becomes `admin.mail.settings`.
  - Put `/mail` at the top level, not inside the `publishing` group.
  - Livewire 4.4.4 has no `config/livewire.php`, so the default component location resolves `components/admin/mail/⚡settings.blade.php` as `admin.mail.settings`, the same way `admin.publishing.settings` resolves.
- **Settings SFC: `resources/views/components/admin/publishing/⚡settings.blade.php`.**
  - `:15`: `new #[Layout('layouts.admin'), Title('…')] class extends Component`. PHP code sits above `?>`, then the markup.
  - `:17-28`: public state is scalars and arrays only, plus `?string $feedback` and `?string $saveError`. The PHPDoc sits on the array property.
  - `:30-34`: `mount()` calls `authorizeConfiguration()`, then `fillFromSaved(app(Settings::class))`.
  - `:36-78`: `save()` authorizes, clears feedback and saveError, runs `$this->validate(rules, messages)`, then does `try { app(X)->refresh(); mutate; ->save(); } catch (Throwable $e) { report($e); $this->saveError = 'Reload the page to see the saved settings, then try again.'; return; }`, then refills from the saved values and sets feedback.
  - `:80-96`: an action taking an untrusted string argument authorizes first, then `throw ValidationException::withMessages([...])` on an unknown value.
  - `:98-125`: `with()` resolves settings fresh on each render. The key boolean is `(string) config('ai.providers.openrouter.key', '') !== ''`.
  - `:127-131`: `Gate::authorize(AdminPermission::View->value); Gate::authorize(DomainPermission::X->value);`.
  - Markup:
    - `<section data-publishing-settings class="space-y-8">` with a `flux:heading level="1" size="xl"` and a `flux:text` intro.
    - Each section is `<section aria-labelledby=… class="max-w-3xl border-t border-zinc-200 pt-6 dark:border-white/10">`.
    - The key status shows a `flux:icon.check-circle` line (`data-credentials-status="configured"`) or a `flux:callout variant="warning"` (`data-credentials-status="missing"`) that names the env var and says keys are never entered or shown (`:222-234`).
    - Fields use `flux:field` / `flux:label` / `flux:error name=…`, and a `wire:dirty` "Not saved yet" hint.
    - The footer has the submit button with `wire:loading.attr="disabled"`, a `role="status"` feedback line, a `role="alert"` "Nothing was saved" line, and a `flux:callout variant="danger"` for `saveError` (`:289-303`).
- **Phase 1 settings API the page must reuse (`app/Settings/SurfaceMailSettings.php`).**
  - Public props `from_name`, `from_address` and `?reply_to` (`:15-19`).
  - `static allowsSenderAddress(string): bool` (`:31-38`) combines `filter_var` with an exact, lowercased domain match.
  - `static allowedSenderDomains(): list<string>` is per surface (`AdminMailSettings.php:15-18` reads `admin.mail.sender_domains` = `['admin.birdcar.dev','birdcar.dev']`; `MarketingMailSettings.php:15-18` reads `['birdcar.dev']`).
  - `updateSender($name, $address, ?$replyTo): static` (`:70-88`) trims all three and maps a blank reply-to to null. It throws `InvalidArgumentException` for an empty or CR/LF name (`:114-119`), an off-domain or malformed address, or a reply-to that fails `filter_var` (`:128-133`).
  - `sender()` and `replyToAddress()` re-validate and throw on a bad stored payload, so don't call them in `mount`.
  - Groups are `admin_mail` and `marketing_mail`. Seeds: `Birdcar` / `noreply@admin.birdcar.dev` / null and `Birdcar` / `hello@birdcar.dev` / null.
  - `Settings::refresh()` returns `self` (`vendor/spatie/laravel-settings/src/Settings.php:257`).
- **Page tests: `tests/Feature/Publishing/PublishingAgentSettingsTest.php`.**
  - `beforeEach` (`:23-28`): `forgetCachedPermissions()`, then `artisan('authorization:sync')->assertSuccessful()`, then pin the key config.
  - Helpers (`:41-57`): an owner gets `assignRole([AdminRole::Access->value, DomainRole::X->value])`, and stored payloads are read with `DB::table('settings')->where('group', …)->pluck('payload','name')->map(json_decode)`.
  - Guest (`:239-241`): `get('http://admin.birdcar.test/publishing/settings')->assertRedirect('/login')`.
  - Forbidden dataset (`:243-258`): HTTP `assertForbidden()` plus `Livewire::actingAs($u)->test('admin.publishing.settings')->assertForbidden()`, run for "admin access only" and "has another domain's capabilities but not this one".
  - Current navigation via DOMXPath (`:260-273`).
  - Tampered-input dataset (`:314-329`): `assertHasErrors(key)`, `assertSee(message)`, and the stored value unchanged.
  - Revoked role (`:399-417`): build the page, `removeRole()`, `forgetCachedPermissions()`, `call()->assertForbidden()`, run for both the domain role and `admin.access`.
  - Forged action (`:419-442`): build as the owner, switch with `Livewire::actingAs($intruder)`, then `call()->assertForbidden()`.
  - Secret hygiene (`:444-467`): HTTP `assertDontSee`, plus `json_encode([$page->snapshot, $page->effects])` not containing the secret.
  - Missing-key notice (`:469-480`).
  - Reload between steps with `app()->forgetScopedInstances()`.
- **Sidebar: `resources/views/layouts/admin.blade.php:1-3` and `:27-29`.**
  - The `@php use App\Authorization\Publishing\Permission as PublishingPermission; @endphp` block sits at the top.
  - The item is `@can(PublishingPermission::View->value) <flux:sidebar.item :href="route('admin.publishing.dashboard')" :current="request()->routeIs('admin.publishing.*')" :aria-current="request()->routeIs('admin.publishing.*') ? 'page' : null" aria-label="Publishing" icon="document-text">Publishing</flux:sidebar.item> @endcan`.
  - Add the Mail import to the same `@php` block, and put the Mail item after Publishing. `flux/icon/envelope.blade.php` exists in Flux 2.19.
- **Invitation copy.** `resources/views/mail/admin/invitation.blade.php:4` is one Markdown sentence, and `AdminInvitationTest.php:53` asserts it word for word through `assertSeeInText`.
- **Boost `record-rule` can't replace an entry.** `RuleRepository::write()` (`:116-135`) always calls `appendEntry()` (`:307-312`), which appends `## {title}` plus the note. Recording with the same title would create a duplicate `## Root Admin bootstrap roles`, so use the spec's fallback: edit `.ai/rules/authorization.md:9` in place and leave `index.md` alone (the glob doesn't change).

### Phase 2

- **Runtime seam: `Illuminate\Mail\Mailer` (v13.30.1).**
  - `send()` sets `$data['mailer'] = $this->name` at `Mailer.php:304`.
  - It calls `shouldSendMessage()` at `:331`, before `sendSymfonyMessage()`.
  - `shouldSendMessage()` at `:602-611` returns `events->until(new MessageSending($message, $data)) !== false`.
  - A throwing listener aborts before the transport. A listener that returns `false` cancels silently, so the guard must return `void`.
  - `raw()`/`html()`/`plain()` (`:210-238`) all go through `send()`, with no `__laravel_mailable` in `$data`.
- **`Mailable::send()` at `Mailable.php:202-209`.**
  - It calls `prepareMailableForDelivery()`, which runs the final `build()` and sets `$this->mailer = surfaceMailer()`.
  - It passes that name on only if `$mailer instanceof MailFactory`.
  - When it is handed a `Mailer` instance, it uses that instance as-is. `Mailer::sendMailable()` (`:350-355`) and `PendingMail` do exactly that.
  - So `Mail::to()->send(new ExampleAdminMail)` runs on the default mailer, and `Mail::mailer('x')->send(...)` runs on `x`. That is precisely the misroute the guard catches (cases d and e).
  - `additionalMessageData()` (`:397-402`) adds `__laravel_mailable => get_class($this)`.
- **`MailChannel` (`Notifications/Channels/MailChannel.php`).**
  - `:62-64`: a returned Mailable is sent with `$message->send($this->mailer)`, where `$this->mailer` is the MailManager factory. The surface mailer is kept, so the guard passes.
  - `:66-70`: a `MailMessage` goes through `mailer($message->mailer ?? null)->send(...)`, and `:153-161` adds `__laravel_notification_id`, `__laravel_notification` and `__laravel_notification_queued`. There is no `__laravel_mailable`, so the guard rejects it. Use `__laravel_notification` to name the class in the message.
- **Exceptions propagate.** `NotificationSender::sendToNotifiable()` (`:168-184`) dispatches `NotificationFailed`, then rethrows. The guard's `LogicException` reaches the caller or the queue job.
- **Event discovery is on by default.**
  - `Application::configure()` calls `->withEvents()` (`Application.php:248-252`).
  - `EventServiceProvider::discoverEventsWithin()` defaults to `app/Listeners` (`:166-171`).
  - `bootstrap/app.php` doesn't override it, and `bootstrap/cache` holds no `events.php`.
  - Scaffold with `php artisan make:listener RejectUnscopedMail --event='Illuminate\Mail\Events\MessageSending' --no-interaction`.
- **Other `MessageSending` listener.** `vendor/laravel/nightwatch/src/Hooks/MailListener.php:26` returns `void` and swallows its own errors, so it doesn't stop `until()` from reaching the guard.
- **Guard contract from Phase 1.**
  - `SurfaceMailable::surfaceMailer()` is abstract and static (`SurfaceMailable.php:19`).
  - The concrete surfaces call `configuredMailer('admin.mail.mailer' | 'marketing.mail.mailer')` (`AdminMailable.php:13`, `MarketingMailable.php:13`), which trims and throws `LogicException` for a blank or undefined mailer (`SurfaceMailable.php:44-59`). That exception must propagate through the guard unchanged.
  - Compare the event's `mailer` with `$mailable::surfaceMailer()` using `!==`.
- **Phase 1 test idioms to copy.**
  - `SurfaceSenderSettingsTest.php:17-24` sets distinct `mail.mailers.array_admin` / `array_marketing` in `beforeEach`.
  - `:29-35` reads a transport with `app(MailManager::class)->mailer($name)->getSymfonyTransport()->messages()->map(fn (SentMessage $s): Email => $s->getOriginalMessage())`.
  - `:37-40` does positive sends with `Notification::route('mail', 'owner@example.com')->notify(new ExampleSurfaceNotification($mailable))`.
  - `:163-171` asserts the throw plus empty transports with `expect(fn () => …)->toThrow(LogicException::class, '…')->and(...)->toBeEmpty()` and a `->with([...])` dataset.
  - Settings rows are seeded by RefreshDatabase (`tests/Pest.php:18-20` binds only `Feature`).
- **Arch plugin semantics (pestphp/pest-plugin-arch 5.0.0, Pest 5.1.4)**, which settles the spec's open "check the exact semantics" item:
  - `not->toBeUsed()` is `ToBeUsedInNothing`, which is `ToOnlyBeUsedIn` with `[]` (`OppositeExpectation.php:566-572`, `Expectations/ToBeUsedInNothing.php:20-23`). It iterates only `Composer::userNamespaces()` (`Blueprint.php:187-213`). That is non-vendor PSR-4 minus anything under `tests/` (`Support/Composer.php:30-39`), which here means `App`, `Database\Factories` and `Database\Seeders`. It doesn't reach into vendor, and tests and fixtures are excluded, so tests may keep using `Mail::`. The `->not->toBeUsedIn('App')` fallback isn't needed.
  - Uses are matched by exact FQCN (`ta-tikoma/.../DependenciesAsserts.php:78`).
  - A target layer is built from the file(s) its name maps to, then filtered by name prefix (`Factories/LayerFactory.php:63-65`, `Repositories/ObjectsRepository.php:144-178`). `Illuminate\Mail\Mailer` therefore doesn't sweep in `Mailable` or `Mailables\*`.
  - `'mail'` resolves as a native function (`ObjectsRepository.php:79-83`).
  - `toOnlyBeUsedIn(InviteAdministrator::class)` excludes objects whose names start with that FQCN (`Blueprint.php:194-196`). The co-located `AdminInvitation*Exception` classes (`InviteAdministrator.php:366-376`) have different names and don't use MailManager.
  - `toExtend($c)` passes when the class *is* `$c` or a subclass (`Expectation.php:696-704`), so `->ignoring(SurfaceMailable::class)` is redundant but harmless. The abstract `AdminMailable` and `MarketingMailable` pass.
  - `not->toImplement()` uses `implementsInterface` (`OppositeExpectation.php:497-510`), which is transitive, so `ShouldQueueAfterCommit` (it extends `ShouldQueue`) is also caught.
  - `classes()` (`PendingArchExpectation.php:39-44`) and `implementing($iface)` (`:89-94`) both exist. A native alternative to the spec's Finder walk is `arch()->expect('App\Notifications')->classes()->implementing(ShouldQueue::class)->toImplement(ShouldQueueAfterCommit::class)`. Put `classes()` first, because exclude callbacks apply in order (`LayerFactory.php:71-73`) and that drops the trait `Admin\BuildsAdminPasswordLinks`. Violations report the file path. Either approach meets the spec; if you keep the Finder walk, use `__DIR__.'/../../app/Notifications'` and skip traits and abstract classes.
- **Boost `record-rule`** (`vendor/laravel/boost/src/Mcp/Tools/RecordRule.php`, `src/Rules/RuleRepository.php`).
  - `write()` (`:116-135`) resolves the target file, creates it with `paths:` frontmatter plus a `# Heading`, appends `## {title}` + note, then `writeIndex()` (`:137-159`) regenerates `index.md`.
  - For a new area, the file name is the slug of the *last* glob segment that contains no `*` or `.` (`meaningfulSegments()` `:257-265`, `uniqueFilePath()` `:209-250`). Existing examples: `services.md` from `{routes/**,…,app/Services/MarketingSite.php,…}` and `models.md` from `app/Models/{…}.php`.
  - Existing rule files use `paths:` YAML frontmatter plus `## Title` entries (`.ai/rules/general.md:1-4`, `settings.md:1-9`).
- **Doc blocks to rewrite.**
  - `docs/production-setup.md:58`: one bullet in "## 2. Configure production environment", after the dotenv block at `:36-50`. That block already lists `BIRDCAR_ADMIN_URL` etc. Keep the bullet style.
  - `docs/development-setup.md:112-125`: an intro paragraph plus a dotenv block. `:128` (catcher caveats) and `:299` (troubleshooting: "use the direct local SMTP catcher, not `log` or `failover`…") sit next to it and still describe invite safety. Retarget `:299` to `BIRDCAR_ADMIN_MAIL_MAILER` if it reads stale.
  - Env names to cite, from `config/mail.php:64-72`, `config/admin.php:17-20`, `config/marketing.php:9-12` and `.env.example:79-85`: `resend_admin` / `resend_marketing`, `BIRDCAR_{ADMIN,MARKETING}_MAIL_MAILER` (default `log`) and `BIRDCAR_{ADMIN,MARKETING}_RESEND_API_KEY`.
  - Seeded senders: `noreply@admin.birdcar.dev` and `hello@birdcar.dev` (`SurfaceSenderSettingsTest.php:57-60`).

### Phase 1

- `config/admin.php:6-8` — reads env into a local, checks `is_string && !== ''`, falls back to `https://admin.birdcar.dev`, derives `host` with `parse_url`, and returns `'url' => rtrim($url, '/')`. The rename changes only `env('ADMIN_URL', …)` → `env('BIRDCAR_ADMIN_URL', …)`. The new `'mail' => [...]` key goes beside `bootstrap_roles`.
- `config/marketing.php:4-5` — `env('MARKETING_URL', env('APP_URL', …))` and `env('MARKETING_INDEXABLE', env('APP_ENV') === 'production')`. Keep the nested fallbacks and change only the outer names. `.ai/rules/services.md` covers this file: canonical URLs use `marketing.url`, and indexing is on by default only in production. Keep those semantics.
- `app/Settings/PublishingAgentSettings.php` — `class X extends Spatie\LaravelSettings\Settings`, typed public props, `public static function group(): string`, fluent mutators that validate and `return $this` (the caller saves), `InvalidArgumentException` on a bad write, and `:68-72` re-validation of stored payloads ("Stored payloads are not type-checked on load"). Use PHPDoc blocks, not inline comments.
- `database/settings/2026_09_24_230000_create_publishing_agent_settings.php` — `return new class extends SettingsMigration`, `$this->migrator->add('group.key', value)` in `up()`, `$this->migrator->deleteIfExists('group.key')` in `down()`. The new file `2026_09_25_120000_create_surface_mail_settings.php` sorts after it.
- `app/Notifications/AdminInvitation.php:37-55` — `resetUrl()` is `rtrim($adminUrl, '/').route('password.reset', ['token' => …, 'email' => $notifiable->getEmailForPasswordReset()], false)`, guarded by `method_exists($notifiable, 'getEmailForPasswordReset')`. `expiresInMinutes()` reads `config("auth.passwords.{fortify.passwords}.expire")` with a fallback of 60. The constructor marks the token `#[\SensitiveParameter]` and keeps `public string $token`, which tests read at `AdminInvitationTest.php:543,552,594`, so keep it public. `PasswordReset` needs the same URL and expiry logic, so extracting one shared helper beats duplicating it.
- `tests/Feature/Auth/AdminInvitationTest.php:23-35` — `beforeEach` does `PermissionRegistrar::forgetCachedPermissions()` and `artisan('authorization:sync')`, then `config([...])`. Tests call `Artisan::call('admin:invite', [..., '--no-interaction' => true])` and assert the exit code, that no `User` exists, and `Artisan::output()` contents. The loop-style case lists at :160-332 are the mailer-safety tests to retarget.
- `tests/Feature/Publishing/PublishingAgentSettingsTest.php:106-107` — corrupt a stored payload with `DB::table('settings')->where('group', …)->where('name', …)->update(['payload' => json_encode(…)])`, then `app()->forgetScopedInstances()`. `SurfaceSenderSettingsTest` should use the same idiom for the "off-domain on load" case.
- Laravel `Mailable` (no in-repo precedent; `app/Mail/` doesn't exist yet). Scaffold with `php artisan make:mail Admin/InvitationMail --markdown=mail.admin.invitation --no-interaction` (writes `resources/views/mail/admin/invitation.blade.php`), then change `extends Mailable` to `extends AdminMailable`. Create the abstract bases with `php artisan make:class`. `resources/views/vendor/mail` isn't published, so `<x-mail::message>` / `<x-mail::button>` use the framework defaults.
- Vendor seams (all confirmed at v13.30.1):
  - `Mailable::send()` at `Mailable.php:202-209` calls `prepareMailableForDelivery()` before `$mailer->mailer($this->mailer)`.
  - `prepareMailableForDelivery()` at `:1733-1743` calls `build()` through `Container::call` when `method_exists`. The base `Mailable` declares no `build()`, so a `final public function build(): void` has no signature conflict.
  - `buildFrom()` at `:460-467` uses `$this->from[0]`.
  - `ensureEnvelopeIsHydrated()` at `:1778-1811` appends the envelope `from` through `setAddress()` (`:760-783`, reverse→unique('address')→reverse).
  - `MailChannel::send()` at `:62-64` calls `$message->send($this->mailer)` for a Mailable.
  - `MailManager::createResendTransport()` at `:321-326` reads `$config['key'] ?? services.resend.key`.
  - `DiscoverSettings.php:71` requires `isInstantiable()`.
  - Settings are bound `scoped()` (`SettingsContainer.php:29`).

## Dependencies

### Phase 3

- **`app/Authorization/Mail/*` (new) and `config/authorization.php:10-14`.**
  - `authorization:sync` (`app/Console/Commands/SyncAuthorization.php`) reads the catalog list. It runs in the `beforeEach` of `AdminInvitationTest.php:26`, `PublishingAgentSettingsTest.php:25`, `AdminAuthorizationTest.php:11`, `AdminPublishingWorkspaceTest.php:22` and others, in the Cloud deploy, and in `docs/production-setup.md:78`.
  - Adding a catalog adds one permission and one role, and no test counts the real-config totals.
  - `AuthorizationSyncCommandTest.php:17-21` pins its own catalog list, so it passes unchanged. The update is additive (see Risks).
  - The collision checks (`:98-121`) require that `mail.*` names don't clash; nothing else uses the `mail.` prefix.
- **`config/admin.php:13-16` (`bootstrap_roles`).**
  - `InviteAdministrator.php:37-38,255-290` validates the list and asserts that every role exists. A missing `mail.operator` row makes it fail with "run [php artisan authorization:sync …]", so sync must run before any invite.
  - `InviteAdministrator.php:333` calls `assignRole($roles)`, which is additive and keeps unrelated roles.
  - `InviteAdmin.php:55` prints "Assigned bootstrap roles: …". No test asserts that line.
  - `AdminInvitationTest.php:62,126,428` asserts `hasRole` for each bundle role; add `MailRole::Operator` beside `PublishingRole::Author`. `:129-143` deletes `admin.access` to test missing roles. It's optional to add a dataset row for a missing `mail.operator`.
- **`routes/admin.php`** is mounted only through `routes/web.php:19-25` (the admin host plus `auth` plus `admin.view`). `bootstrap/cache` holds only `packages.php` and `services.php`, with no route or config cache, so the new route is live at once.
- **`resources/views/layouts/admin.blade.php`** is the `#[Layout]` of every Admin page (`⚡index`, publishing `⚡dashboard`, `⚡published`, `⚡settings`, `⚡article-workspace`, and the new mail page).
  - Shell tests `AdminPublishingWorkspaceTest.php:98-143` assert the `Admin modules` nav exists but count no items.
  - The users in those tests lack `mail.operator`, so the new `@can` item is invisible there and the tests stay green.
  - `<x-admin.navigation />` (`components/admin/navigation.blade.php:4`) renders only on `admin.publishing.*`, so it's unaffected.
- **`resources/views/mail/admin/invitation.blade.php:4`** ← `InvitationMail.php:26` ← `AdminInvitation::toMail()` ← the exact-sentence assertion at `AdminInvitationTest.php:53`. No other test matches that copy.
- **Settings the page writes (`admin_mail.*`, `marketing_mail.*`).**
  - They're read at send time by `AdminMailable.php:18` and `MarketingMailable.php:18` (inside the final `build()`), and in the invite pre-flight at `InviteAdministrator.php:129`.
  - Settings are bound `scoped()`, so the next request or job sees a save without a restart.
  - Queued `PasswordReset` resolves the settings on the worker at send time.
- **`.ai/rules/authorization.md:9`** has index row `.ai/rules/index.md:9`, which doesn't change. `AuthorizationGuidelineTest.php:5-27` composes `.ai/guidelines/authorization.md` through Boost `GuidelineComposer`, not the rule file, so the rule edit can't break it.
- **Setup docs** have no code consumers; only the contract env grep (`contract.md:34`) reads them: `docs/development-setup.md:137` and `docs/production-setup.md:91`.
- **Arch rules** (`tests/Arch/MailTest.php:25-33`) target the `App\Mail` name prefix, and `App\Authorization\Mail\*` doesn't start with it. The SFC isn't PSR-4 and sends no mail.

### Phase 2

- **`app/Listeners/RejectUnscopedMail.php` (new) applies to every non-faked send in every process** (web, queue worker, console, tests). Current producers:
  - `app/Actions/Admin/InviteAdministrator.php:42-46` → `AdminInvitation` → `InvitationMail` on `admin.mail.mailer`. It passes the guard. Any guard throw is wrapped by the `catch (Throwable)` at `:50-55` into `AdminInvitationDeliveryException`, which `app/Console/Commands/InviteAdmin.php:37-41` reports, exiting with FAILURE.
  - `app/Models/User.php:81` → `App\Notifications\Admin\PasswordReset` (`ShouldQueueAfterCommit`) → `PasswordResetMail`. It passes. On a worker, a throw fails the `SendQueuedNotifications` job.
  - Nothing in `app/`, `config/`, `routes/`, `database/` or `bootstrap/` uses `Mail::`, `MailMessage`, `PendingMail`, `mail()` or the mail contracts (grep is clean). The only `MailManager` use is `InviteAdministrator.php:10,120,140,191`, which the spec allows.
- **Dormant framework and package mail paths the guard would block if enabled.** None is active now, which confirms the failure mode's "future" premise.
  - Email verification is off (`config/fortify.php:167` is commented out; `MustVerifyEmail` is commented out at `User.php:5`).
  - Horizon mail notifications are commented out (`app/Providers/HorizonServiceProvider.php:19`).
  - spatie/laravel-backup and spatie/laravel-health are installed but unpublished (no `config/backup.php` or `config/health.php`) and unscheduled (`routes/console.php:11-12` schedules only the publishing commands).
  - Stock `Illuminate\Auth\Notifications\ResetPassword` is already replaced by `User::sendPasswordResetNotification`. No app code sets `ResetPassword::toMailUsing`/`createUrlUsing`, so case (c) exercises the stock `MailMessage`.
- **Existing tests that really send** (not faked). All are surface-correct, so the full suite should stay green:
  - `SurfaceSenderSettingsTest.php` (array_admin / array_marketing through `ExampleSurfaceNotification`);
  - `AdminPasswordResetTest.php:36-56` (array_admin);
  - `AdminInvitationTest.php:336-357` (`recording_admin`, with `mail.default=log`).
  - The other `AdminInvitationTest` cases and `tests/Manual/PublishingModelTrialTest.php:114-115` use `Notification::fake()` / `Mail::fake()`, which never fire `MessageSending`. `tests/Manual` isn't in any testsuite.
- **`tests/Fixtures/Mail/*` (reused).**
  - `ExampleAdminMail(?Address $envelopeFrom = null)` and `ExampleMarketingMail` are the surface fixtures.
  - `ExampleSurfaceNotification(SurfaceMailable $mailable)` sends the given Mailable `to` `routeNotificationFor('mail')`.
  - Add, as new fixtures (not in the spec's New Files table): a `MailMessage` notification for case (b) and a plain `Illuminate\Mail\Mailable` subclass for case (f). Fixtures under `tests/` are outside the arch scan, so both are legal there.
- **`phpunit.xml:8-15` (Arch suite added).**
  - Consumers: `composer test` → `php artisan test` (`composer.json` scripts), `composer ci:check`, and `.github/workflows/tests.yml` "Run CI Checks". A default run executes every registered suite, so Arch then runs everywhere.
  - `tests/Pest.php:18-20` binds `TestCase` + `RefreshDatabase` to `Feature` only. `tests/Arch` gets plain `PHPUnit\Framework\TestCase`: no container, no `app_path()`.
- **`app/Mail/**` and `app/Notifications/**` are what the arch rules protect.**
  - Today: `SurfaceMailable`, `Admin\{AdminMailable,InvitationMail,PasswordResetMail}` and `Marketing\MarketingMailable` all extend SurfaceMailable, and none implements ShouldQueue.
  - `Notifications`: `AdminInvitation` (not queued), `Admin\PasswordReset` (`ShouldQueueAfterCommit`), and the trait `Admin\BuildsAdminPasswordLinks`. All rules should pass on first run.
  - Phase 3 (`spec-phase-3.md`) adds a Livewire SFC and an authorization catalog but no mail classes or `MailManager` use, so the rules don't constrain it.
- **`.ai/rules/**` (the new rule file).** `index.md` is regenerated by Boost and must not be hand-edited. Both `.ai/rules` and the two docs are inside the contract's env grep scope (`contract.md:34`).
- **Docs.** `docs/production-setup.md:58` and `docs/development-setup.md:112-125` have no code consumers, only the contract grep. The `:137` line ("No queue worker is needed for this synchronous invitation") stays true: `AdminInvitation` isn't queued.

### Phase 1

- `config/admin.php` — the `admin.url` / `admin.host` / `admin.bootstrap_roles` keys don't change. Consumers: `config/fortify.php:76` (`home`) and `:91` (Fortify route `domain`), `routes/web.php:19` (`Route::domain(config('admin.host'))`), `app/Http/Responses/AdminLoginResponse.php:38,43`, `AdminLogoutResponse.php:19`, `AdminPasswordResetResponse.php:19`, `app/Actions/Admin/InviteAdministrator.php:74,237`. The new `admin.mail.mailer` / `admin.mail.sender_domains` keys will be read by `AdminMailable`, `AdminMailSettings` and `InviteAdministrator::assertSafeMailer()`.
- `config/marketing.php` — the `marketing.url` / `marketing.indexable` keys don't change. Consumers: `app/Services/MarketingSite.php:22,27,38`; the booking keys in `resources/views/components/marketing/fit-card.blade.php:35-36` are untouched.
- `config/mail.php:64-66` (the stock `resend` mailer) — no consumers. Nothing in app/, config/, routes/, tests/ or resources/ names the mailer `resend`.
- `config/services.php:21-23` (`services.resend.key`) — its only consumer is the vendor fallback at `MailManager.php:324`. It is safe to remove once both `resend_*` mailers carry `key`.
- `config/settings.php:17-19` — `settings` list (currently `PublishingAgentSettings::class`). `auto_discover_settings` also scans `app/Settings`; the abstract `SurfaceMailSettings` is skipped by `isInstantiable()`.
- `app/Actions/Admin/InviteAdministrator.php:108-117` (`assertSafeMailer`) — consumed by → `app/Console/Commands/InviteAdmin.php:30-51`, which catches only `AdminInvitationDeliveryException`, `AdminInvitationBrokerException` and `AdminInvitationException`. Any other exception from the new pre-flight escapes as an uncaught error. `assertSafeMailerNamed()` (`:122-153`) stays unchanged; its `catch (Throwable)` at :148 already turns `Resend::client(null)` (a TypeError: `Resend::client(string $apiKey)`) into "could not be resolved for invitation delivery".
- `app/Notifications/AdminInvitation.php` — consumed by → `InviteAdministrator.php:42` (inside the broker `sendResetLink` callback at :39-46, which Throwable-wraps into `AdminInvitationDeliveryException`) and `tests/Feature/Auth/AdminInvitationTest.php`: `->toMail($user)->actionUrl` at :50, :85, :435; `->introLines` at :52; `->outroLines` at :87; `->token` at :543, :552, :594. After the change, `toMail()` returns `InvitationMail`, so every `actionUrl`/`introLines`/`outroLines` use must move to `$mail->setupUrl`, `$mail->expiresInMinutes` and `$mail->assertSeeInText(...)`.
- `app/Models/User.php` (new `sendPasswordResetNotification`) — called by `PasswordBroker::sendResetLink()` only when no callback is passed, which is Fortify's `POST admin.birdcar.test/forgot-password` (`password.email`). `InviteAdministrator` passes a callback (:41-45), so invitations do **not** go through the override. `.ai/rules/models.md` covers this file (deletion rules only, not affected).
- `phpunit.xml:21-39` — its `<php><env>` block feeds every test. The existing `MAIL_MAILER=array` stays, and the two `BIRDCAR_*_MAIL_MAILER=array` lines go beside it. phpunit env vars are set before Laravel's immutable Dotenv loads, so they win over `.env` / CI's copied `.env.example`.
- Local `.env` (gitignored, `.gitignore:10`) — key names present: `ADMIN_URL`, `MAIL_*`, `BIRDCAR_ADMIN_RESEND_API_KEY`, `BIRDCAR_MARKETING_RESEND_API_KEY`. `MARKETING_URL` and `MARKETING_INDEXABLE` are **absent**. `php artisan route:list --name=password` shows Fortify on `admin.birdcar.test`, so local tests that post to `http://admin.birdcar.test/...` depend on this `.env` value, because `admin.host` is fixed when config loads and a later `config()` call can't move routes.
- Docs and rules (the contract grep only): `docs/production-setup.md:41-43`, `docs/development-setup.md:47-49,59,298`, `.ai/rules/general.md:21`, `.env.example:72,74,76`. Other `ADMIN_URL` hits live only in `.impeccable/**/*.log`, which is outside the grep scope. Leave them alone.

## Conventions

### Phase 3 additions

- **Naming**:
  - `enum Permission: string { case ConfigureSenders = 'mail.configure-senders'; }` and `enum Role: string { case Operator = 'mail.operator'; }` in `App\Authorization\Mail`.
  - Import them aliased as `MailPermission`, `MailRole` and `MailCatalog`, matching `AdminPermission` and `PublishingRole` elsewhere.
  - The route name is `admin.mail.settings` and the component name is `admin.mail.settings`; the same string for both is fine, just as with publishing.
  - Use `data-*` hooks as test anchors, for example `data-mail-settings` on the root section and a per-surface `data-key-status` with the values "configured" and "missing", mirroring `data-credentials-status`.
- **Global test-helper names are shared across the suite** (Pest loads every file into one process). Names already taken: `settingsPageOwner`, `storedAgentSettings`, `settingsAttempt`, `interviewResponse`, `surfaceMessagesOn`, `sendSurfaceMail`, `surfaceSettingsGroups`, `guardedTransportCounts`, `adminHomeUser`, `publishingUser`, `permissionNamesForRole`, `cachedRoleNamesForPermission`. Use distinct names such as `mailSettingsOperator()` and `storedMailSender(string $group)`.
- **Imports**: fully qualified `use` lines at the top of the SFC (`App\Authorization\Admin\Permission as AdminPermission`, `App\Authorization\Mail\Permission as MailPermission`, `App\Settings\{AdminMailSettings,MarketingMailSettings}`, `Illuminate\Support\Facades\Gate`, `Illuminate\Validation\ValidationException`, and `Livewire\Attributes\{Layout,Title}`). Blade layouts import through a leading `@php use … @endphp` block.
- **Error handling**:
  - Call `Gate::authorize(...->value)` first in `mount` and `save`. Throw `ValidationException::withMessages(['senders' => '…'])` for an unknown surface.
  - Wrap the save in `try { … } catch (Throwable $exception) { report($exception); $this->saveError = 'Reload the page to see the saved settings, then try again.'; return; }`.
  - Always use curly braces. Prefer PHPDoc blocks over inline comments.
- **Types**:
  - phpstan level 7 covers `app/` (the new enums and catalog), `config/` and `routes/`, but not `resources/views`, so the SFC isn't analysed. Still type everything explicitly: `save(string $surface): void`, `with(): array`, and an array-shape PHPDoc on `$senders`.
  - `Settings::refresh()` is declared `: self`. That's fine at runtime; call `updateSender()` on the result.
- **Copy**:
  - Plain and operator-facing (spec example: "Admin mail is sent from this address. It must be at admin.birdcar.dev or birdcar.dev.").
  - The missing-key notice names `BIRDCAR_ADMIN_RESEND_API_KEY` or `BIRDCAR_MARKETING_RESEND_API_KEY` and says keys are never entered or shown here, mirroring `⚡settings.blade.php:229-232`.
  - Show the configured mailer name (`config('admin.mail.mailer')` / `config('marketing.mail.mailer')`) as text only. The page never edits it.
- **Testing**:
  - Use sentence names with `test('…')` and `->with([...])` datasets for the forbidden users and the invalid inputs.
  - HTTP requests go to `http://admin.birdcar.test/mail`, and Livewire tests use `Livewire::actingAs($u)->test('admin.mail.settings')`. Check sidebar `aria-current` with DOMXPath.
  - Read `.claude/skills/testing-best-practices/SKILL.md`. Activate the `livewire-development`, `fluxui-development` and `laravel-permission-development` skills.
- **Rules and docs**: hand-edit `.ai/rules/authorization.md:9` in place (see Risks) and never edit `index.md`. Any `.ai/rules` or docs text must keep the `BIRDCAR_` prefix to pass the contract env grep.
- **Formatting**: `vendor/bin/pint --dirty --format agent`.

### Phase 2 additions

- **Naming**:
  - The listener class is `App\Listeners\RejectUnscopedMail` with `handle(MessageSending $event): void`. Discovery keys on the `handle` type-hint.
  - The `make:listener` stub includes an empty `public function __construct() {}`. Delete it (Boost PHP rule: no empty zero-parameter constructors).
  - Test files live in `tests/Feature/Mail`, use `test('…')` with sentence names (e.g. `'clean installs seed a sender…'`), and use `->with([...])` datasets for case lists. The rejected-path cases (a)–(f) fit one dataset of closures.
  - Arch rules use `arch('…')->expect(...)`.
- **Imports**: fully qualified `use` lines. Tests may `use Illuminate\Support\Facades\Mail` freely, because tests aren't scanned by arch.
- **Error handling**:
  - Throw a plain `LogicException` (as `SurfaceMailable::configuredMailer()` does). Bracket the class and config names in the message: `[{$mailable}] must be sent on mailer [{$expected}], not [{$mailer}].`
  - Name the subject: the `__laravel_mailable` class, else the `__laravel_notification` class, else "a raw message".
  - Never return `false`.
  - Use PHPDoc blocks, not inline comments. The spec's inline comment inside `handle()` is a note for the builder, not code to keep.
- **Types**: phpstan level 7 covers `app/`. `$event->data` is `array`, so narrow with `is_string()` before `is_subclass_of()`, then call `$mailable::surfaceMailer()`. phpstan may need a `/** @var class-string<SurfaceMailable> $mailable */` after the narrowing.
- **Testing**:
  - Assert "threw and every transport empty" across `array_default`, `array_admin` and `array_marketing`.
  - Positive cases assert exactly one message on the surface's own transport and none on the other two.
  - Force production with `app()->detectEnvironment(fn (): string => 'production')` (`Application.php:793`; the argv carries no `--env`, so the callback wins). Each test gets a fresh app, so this doesn't leak.
  - Read `.claude/skills/testing-best-practices/SKILL.md` ("Judge an architecture test by the convention it protects") and `rules/naming.md`.
- **Rules**: record via Boost `record-rule`. The fallback is a hand-written `.ai/rules/<name>.md` with `paths:` frontmatter plus `## Title` entries, then `php artisan boost:update`, then confirm `index.md` gained the row. Never hand-edit `index.md`.
- **Formatting**: `vendor/bin/pint --dirty --format agent` after the PHP edits. CI runs `pint --parallel --test`.

### Phase 1

- **Naming**: PSR-4 `App\` → `app/`, `Tests\` → `tests/` (autoload-dev), so the fixtures are `Tests\Fixtures\Mail\RecordingTransport` etc. `tests/Fixtures/` has only data (`Publishing/archive`) today, so these are the first PHP fixtures. Settings groups use snake_case (`publishing_agents` → `admin_mail`, `marketing_mail`). Test names are sentence-style `test('...')`.
- **Imports**: fully qualified `use` statements, one per line, no barrels. PHP attributes are used heavily (`#[Fillable]`, `#[Hidden]`, `#[\SensitiveParameter]`).
- **Error handling**: domain exceptions are small classes co-located in the action file (`InviteAdministrator.php:348-361`). Messages cite config keys in brackets (`Configuration [mail.default] must …`), so the new text should say `[admin.mail.mailer]`. Settings throw `InvalidArgumentException` on bad values. Curly braces are always used, and methods have explicit return types.
- **Types**: phpstan level 7 over app/, config/, database/, routes/ and bootstrap/app.php (not tests), so `config()` values need `is_string` / `is_array` narrowing. Iterable values get PHPDoc shapes (`@return list<string>`). Constructor promotion is used.
- **Testing**: Pest 5.1.4. `tests/Pest.php` applies `TestCase` + `RefreshDatabase` to `Feature`. Settings migrations run during the refresh (the seeded rows are asserted in `PublishingAgentSettingsTest.php:68-74`). Expectations use `expect()->and()` chains. Cases are loops or `->with()` datasets. There's a `forgetScopedInstances()` idiom for settings reloads. Read the `testing-best-practices` skill before writing tests (`.claude/skills/testing-best-practices`); `fortify-development` is relevant for the reset flow.
- **Formatting**: `vendor/bin/pint --dirty --format agent`. CI runs `pint --parallel --test`.
- **Rules**: `app/Settings/**` falls under `.ai/rules/settings.md` (settings are scoped; resolve them per request or job, never cache the object). So resolve settings inside `build()` and never store them on the Mailable. Leave `.ai/rules/index.md` untouched; it's Boost-generated.

## Risks

### Phase 3

- **A null key reads as "configured".** `config/mail.php:30-38` defines `resend_admin.key` and `resend_marketing.key` as `env('BIRDCAR_*_RESEND_API_KEY')` with no default, so an unset key is `null`. The spec's literal `mail.mailers.resend_{surface}.key !== ''` gives `true` for `null`. Use the Publishing cast, `(string) config("mail.mailers.resend_{$surface}.key", '') !== ''` (`⚡settings.blade.php:121`), and cover both `null` and `''` in the missing-key test.
- **Local tests see a real Resend key.**
  - The local `.env` has non-empty `BIRDCAR_ADMIN_RESEND_API_KEY` and `BIRDCAR_MARKETING_RESEND_API_KEY`. There's no `.env.testing`, and phpunit.xml doesn't override them. CI copies `.env.example`, where they're empty.
  - So in `beforeEach`, pin both keys explicitly (e.g. `config(['mail.mailers.resend_admin.key' => '', 'mail.mailers.resend_marketing.key' => ''])`), and set the fake `re_test_secret` inside the hygiene test.
  - Never print or `cat` `.env` values; list key names only.
- **The `email` rule and the settings class disagree.**
  - Bare `email` is `RFCValidation` (`ValidatesAttributes.php:991-999`). It accepts `user@localhost` and `"a b"@birdcar.dev`, which `filter_var` rejects. I checked this locally.
  - A reply-to like that would pass the page's validation, then `updateSender()` would throw `InvalidArgumentException` (`SurfaceMailSettings.php:128-133`). That lands in the catch branch: a spurious `report()` and the misleading "Reload the page…" instead of a field error.
  - Use `email:filter` (FilterEmailValidation, i.e. `filter_var`) for both `from_address` and `reply_to`. The `allowsSenderAddress()` closure still enforces the domain.
  - Add `string` to `reply_to` so an array payload fails cleanly.
- **A CR/LF sender name passes `required|string|max:100`.** `updateSender()` rejects `\r`/`\n` (`:114-119`), which leads to the same misleading catch path. Add `not_regex:/[\r\n]/` with a field message. A whitespace-only name is already caught, because `required` trims.
- **Livewire skips TrimStrings and ConvertEmptyStringsToNull** for update requests (`HandleRequests.php:84-88`), so values arrive raw.
  - `updateSender()` trims them. After a successful save, refill `senders.{surface}` from the saved settings so the form shows what was stored.
  - A reply-to of `''` or whitespace passes `nullable|email:filter`, because non-implicit rules skip blank strings (`Validator.php:844-852`). It's then stored as `null` through `?: null` and `updateSender()`'s own blank check. This covers the spec experiment "reply-to `''` stores `null`".
- **The error bag is shared by both Save buttons.** On success, `validate()` calls `resetErrorBag()` (`HandlesValidation.php:275`), which clears the other surface's errors too; a failure replaces the whole bag.
  - Validate only the saved surface's subtree (`senders.{$surface}.from_name`, `.from_address`, `.reply_to`), never `senders` as a whole. Otherwise an invalid marketing value blocks an admin save, contradicting the spec's key decision.
  - Scope the "Nothing was saved" alert and the success feedback to each surface (`$errors->has('senders.admin.*')` works, because MessageBag supports wildcard keys).
  - Read input from `$validated['senders'][$surface]`, not from raw `$this->senders`, so tampered extra keys are ignored.
- **Order of checks in `save()`.** Call `authorizeConfiguration()` first, so a forged `save('customer')` gets 403, not 422. Then check the surface against a strict map `['admin' => AdminMailSettings::class, 'marketing' => MarketingMailSettings::class]` and throw a `ValidationException` on `senders` for anything else. Only then validate.
- **`mount()` must not call `sender()` or `replyToAddress()`.** They throw on a corrupt stored payload, which would 500 the page the owner needs to fix it. Fill from the raw props (`from_name`, `from_address`, `reply_to ?? ''`). A missing settings row still throws `MissingSettings`. Publishing has the same exposure, so that's acceptable.
- **The header label goes stale.** `layouts/admin.blade.php:47` renders `request()->routeIs('admin.publishing.*') ? 'Publishing' : 'Home'`, so `/mail` would show "Home". Extend it so `admin.mail.*` shows "Mail", and keep the `max-sm:sr-only` condition consistent.
- **Stale records the spec doesn't list.** The contract's recorded learning (`contract.md:52`) is to reconcile the authoritative records a change touches.
  - `docs/development-setup.md:137` ("grants the configured root bundle (`admin.access` and `publishing.author`)") and `docs/production-setup.md:91` ("assigns `admin.access` and `publishing.author`") need `mail.operator` added. Both are in the contract env grep scope; the edit adds no env names.
  - `DESIGN.md:395` ("Only Home and authorized Publishing are present") needs "and authorized Mail".
  - `.impeccable/surfaces/resources-views-layouts-admin-blade-php.md` Boundaries ("Initially show only Home and authorized Publishing") is a design-review record. Leave it, or add a one-line note; don't regenerate it.
- **`record-rule` can't replace an entry** (`RuleRepository.php:116-135,307-312`). Using it with the same title would append a duplicate `## Root Admin bootstrap roles`. Edit `.ai/rules/authorization.md:9` in place ("admin.access, publishing.author and mail.operator currently") and leave `index.md` untouched. Keep the rest of the sentence: no Teams, no direct permissions, no `Gate::before` bypass, and no bypass of tenant checks.
- **The sync test update is additive.** `AuthorizationSyncCommandTest.php:17-21` pins Admin, Organizations and Publishing, so it passes unchanged and wouldn't catch a broken Mail catalog. Add `MailCatalog::class` to that list, `assertDatabaseHas` for the permission `mail.configure-senders` and the role `mail.operator`, and `permissionNamesForRole('mail.operator') === ['mail.configure-senders']`.
- **`AuthorizationGuidelineTest` reads `.ai/guidelines/authorization.md`**, not `.ai/rules/authorization.md`. Don't edit the guideline file for this phase; the test stays green.
- **The local account step sends real mail.**
  - Re-running `php artisan admin:invite <email>` sends a real invitation through the local admin mailer. The pre-flight rejects `log`, `array` and `failover`, so it must be the SMTP catcher. It also issues a new reset token, and the broker throttles repeats for 60s.
  - The no-mail alternative is to assign `mail.operator` in tinker.
  - Either way, run `php artisan authorization:sync --no-interaction` first, or the invite fails on the missing role.
- **Existing admins lack the new role.** The spec's failure mode covers this. "Production has no admins yet" can't be checked from the repo; the Phase 4 rollout should confirm it.
- **A Mail-only operator sees an ambiguous Home.** With `admin.access` + `mail.operator` but no publishing role, Home shows "You have Admin access. Work appears here when you have access to a business module." (`⚡index.blade.php:49-53`), which is ambiguous but harmless. It's out of scope; the spec doesn't ask for it.
- **An empty sender-domain list** would render "Use an address at ." The domains are hard-coded in config, so the risk is low; a guarded fallback message is cheap.
- **Octane.** Keep only scalars and arrays in public state (`$senders`, `$feedback`, `$saveError`). Resolve settings with `app(...)` inside each method, and never store the Settings object or mailer config on the component (`.ai/rules/settings.md:9`).
- **Decision log vs reality.** No contradictions.
  - The settings hold exactly `from_name`, `from_address` and `reply_to` (`SurfaceMailSettings.php:15-19`).
  - Mailer choice and keys live only in env and config (`config/admin.php:17-20`, `config/marketing.php:9-12`, `config/mail.php:30-38`), so the page must show the mailer name and a key boolean only, never edit them.
  - "Rejected: an Admin settings page in the MVP" (`contract.md:77`) is consistent with this phase being the Full tier.
  - The guard and the arch rules don't constrain a page that sends no mail.
- **Baseline not re-run by the scout.** I ran nothing that writes. Phase 2 committed as `a2d3dce` with review PASS. Run the full suite once after the bundle change, because every `admin:invite` test now assigns three roles.

### Phase 2

- **Global Pest helpers collide.** `tests/Feature/Mail/SurfaceSenderSettingsTest.php:29,37,45` declares global `surfaceMessagesOn()`, `sendSurfaceMail()` and `surfaceSettingsGroups()`, and `tests/Pest.php:48` declares `setPublishingAgentsPaused()`. Pest loads every test file into one process.
  - Redeclaring any of these in `SurfaceMailGuardTest.php` is a fatal error in the full suite, even though running the file alone passes.
  - Calling them from the new file breaks the single-file inner loop, because they're undefined there.
  - Use distinct names (e.g. `guardMessagesOn()`) or closures.
- **Vacuous misroute tests.** `phpunit.xml:30-32` pins `MAIL_MAILER`, `BIRDCAR_ADMIN_MAIL_MAILER` and `BIRDCAR_MARKETING_MAIL_MAILER` all to `array`, so "default mailer" and "surface mailer" share one name and one transport. `beforeEach` must set `mail.default=array_default`, `admin.mail.mailer=array_admin` and `marketing.mail.mailer=array_marketing`, with all three defined in `mail.mailers`, before anything resolves a mailer. Otherwise case (d) passes the guard: name `array` === expected `array`.
- **Case (d) and (e) mechanics.** `Mail::to()->send($surfaceMailable)` and `Mail::mailer('x')->send(...)` pass a `Mailer` instance into `Mailable::send()`, so the `build()`-chosen mailer is ignored (`Mailable.php:202-209`, `Mailer.php:350-355`). The guard sees the calling mailer's name. That is the intended catch; don't "fix" it in the base class.
- **The Mail alias escapes the arch rules.** A global `\Mail::raw()` through Laravel's default class alias records the use as `Mail`, not `Illuminate\Support\Facades\Mail`. The arch rule (exact-FQCN match) misses it, and adding `'Mail'` as a target yields an empty layer (no PSR-4 prefix). Only the runtime guard catches it. State this in the rule note.
- **The arch rules skip non-PSR-4 PHP.** `routes/*.php`, `config/*.php`, `bootstrap/app.php`, Blade and Folio pages, and Livewire single-file components under `resources/views` aren't in `App`, `Database\Factories` or `Database\Seeders`. The Phase 3 Admin page is an SFC. The runtime guard is the backstop there. Grep shows no mail use in those paths today.
- **Rule file name.** With the spec's glob `{app/Mail/**,app/Notifications/**,app/Settings/*MailSettings.php,app/Listeners/RejectUnscopedMail.php,config/mail.php}`, Boost's segment logic (`RuleRepository.php:209-265`) picks the last `*`/`.`-free segment, `Listeners`, and writes `.ai/rules/listeners.md`. The spec's New Files row says `.ai/rules/mail.md`. To get `mail.md`, reorder the brace list so `app/Mail/**` comes last (e.g. `{app/Listeners/RejectUnscopedMail.php,app/Notifications/**,app/Settings/*MailSettings.php,config/mail.php,app/Mail/**}`, whose last clean segment is `Mail`). Alternatively accept `listeners.md` and note the deviation. No contract check depends on the file name.
- **The rule note and docs must pass the contract env grep** (`contract.md:34`). `.ai/rules` and both docs are in scope, so never write a bare `ADMIN_URL`, `MARKETING_URL` or `MARKETING_INDEXABLE`; always use the `BIRDCAR_` prefix. The grep passes today; re-run it after editing.
- **Carry the Phase 1 gap into the rule note** (`implementation-notes-phase-1.html`, "build() clears earlier From entries"). An `envelope()` `from` with the *same* address and a different name replaces the display name, because `setAddress()` de-duplicates by address. The domain can't change. The note should say "never set `from` in `envelope()`" and mention this.
- **Anonymous-class fixtures.** If cases (b) or (f) use `new class extends …`, `get_class()` returns a name containing a NUL byte, and the guard message embeds it. Prefer named fixtures in `tests/Fixtures/Mail/`, and assert on a message substring such as `'surface Mailable'`.
- **Stock ResetPassword case (c)** needs a persisted notifiable: `User::factory()->create()` then `$user->notify(new ResetPassword('t'))`. Its `toMail()` calls `url(route('password.reset', …, false))`, and that route exists (Fortify, admin host). Don't call `sendPasswordResetNotification()` for this case; that goes through the surface `PasswordReset`.
- **Failure-message coverage for arch.** Spec step 3 says to break each rule once, then revert. Leave nothing behind. Check `git status` shows only the intended files before committing, because a stray `use Illuminate\Support\Facades\Mail;` in `AppServiceProvider` or a `Stray` class in `app/Mail` would ship.
- **Queued-notification rule scope.** The spec walks only `app/Notifications`. A `Notification` subclass placed elsewhere in `App` would escape. `expect('App')->classes()->extending(Notification::class)->implementing(ShouldQueue::class)->toImplement(ShouldQueueAfterCommit::class)` is a broader native alternative. It's optional; follow the spec unless you choose to widen it.
- **Event cache in production.** If a deploy runs `event:cache` / `optimize` from a stale build, the listener could be missing. Cloud rebuilds the caches on each deploy, and there's no local `bootstrap/cache/events.php`. The guard tests catch absence, because case (d) would send. The `event:list` manual check covers local.
- **Octane.** The listener must stay stateless: no static caches of the resolved mailer. `surfaceMailer()` reads config per call, which is correct.
- **Optional doc gap.** `PasswordReset` is now queued, and local `QUEUE_CONNECTION=database` (`docs/development-setup.md:55`) means forgot-password emails need a running worker. The spec doesn't ask for this; mention it only if it fits the rewritten block naturally.
- **Decision log vs reality.** No contradictions.
  - `phpunit.xml:8-15` registers only Unit and Feature, which matches the Arch-suite decision's premise.
  - `MessageSending` fires through `until()` for every real send (`Mailer.php:331,602-611`), and the only other listener (Nightwatch) returns void, so the guard decision's premise holds.
  - `MailChannel` keeps the Mailable's mailer (`:62-64`).
  - The failure mode's "a *future* health or backup alert" holds: spatie/laravel-backup and spatie/laravel-health are installed but unpublished and unscheduled, and Horizon mail routing is commented out. Enabling any of them later will throw by design, and the rule note should say to wrap such alerts in a surface notification.
- **Baseline not re-run by the scout.** Tests weren't executed (read-only). Phase 1 committed with review PASS; the builder should run the full suite once after adding the listener, because the spec's step 3 says a newly failing test reveals a missed send path.

### Phase 1

- **`.env` rename ordering breaks local tests.** Once `config/admin.php` reads `BIRDCAR_ADMIN_URL`, an un-renamed `.env` sends `admin.host` back to `admin.birdcar.dev`, and every test that posts to `http://admin.birdcar.test/...` fails (Fortify and `routes/web.php:19` bind the domain at boot). Rename the `.env` key in the same step as the config edit, then run `php artisan config:clear`. Use key-anchored `sed` only. Never `cat`, echo or print `.env` values; list key names only with `grep -oE '^[A-Z_]+=' .env`.
- **Arrow-function capture bug in the spec's recording test.** `extend('recording', fn () => $transport = new RecordingTransport)` assigns to a by-value copy, so the outer `$transport` stays unset. Create `$transport = new RecordingTransport;` first, then `extend('recording', fn (): RecordingTransport => $transport)`. `assertSafeMailerNamed()` calls `$manager->purge()` and resolves again (`:142-145`), so the closure must return the same instance every time.
- **The invite pre-flight must catch more than `InvalidArgumentException`.** A missing settings row throws `Spatie\LaravelSettings\Exceptions\MissingSettings`, which `InviteAdmin.php:37-51` doesn't catch. The spec's Failure Modes row ("the invite pre-flight fails before provisioning") holds only if the builder also converts `MissingSettings` (or wraps any `Throwable` from `sender()`) into `AdminInvitationException`.
- **Envelope `from` with the same address keeps its own name.** `setAddress()` de-duplicates by address and keeps the *last* entry. An envelope `from` with a different address lands at `from[1]`, so `from[0]` (settings) wins as the spec says. An envelope `from` with the *same* address but a different name replaces the settings name. The spoof test only covers a different address. Either accept this or add a same-address case and document it in the Phase 2 rule.
- **`render()` / `assertSeeInText()` run `build()` too** (`Mailable.php:311-319`, `:1699-1706`). Mailable assertions in `AdminInvitationTest` under `Notification::fake()` therefore resolve `AdminMailSettings` and validate `admin.mail.mailer`. `beforeEach` must set `admin.mail.mailer` to a mailer defined in `mail.mailers` (`smtp`), or every assertion throws `LogicException`. Public property checks (`setupUrl`, `expiresInMinutes`) don't trigger `build()`.
- **The `User::sendPasswordResetNotification` signature must stay compatible with the contract.** `Illuminate\Contracts\Auth\CanResetPassword::sendPasswordResetNotification($token)` is untyped, so a native `string $token` narrows it and causes a fatal error. At phpstan level 7, an untyped param reports "no type specified". Keep the native param untyped with `#[\SensitiveParameter]` and add a `@param string $token` PHPDoc.
- **Both surfaces share one `array` mailer in tests.** phpunit pins both surface mailers to `array`, so both surfaces write into the same `ArrayTransport` and a "lands on its own mailer" assertion can't tell them apart. Where routing matters, configure distinct test mailers (e.g. `mail.mailers.array_admin` / `array_marketing`, both with `transport: array`) and point each `*.mail.mailer` at one.
- **Raw body assertions can miss URLs.** `toString()` on a sent message is quoted-printable encoded and wraps long URLs with `=`, and the HTML body escapes `&` as `&amp;`. Assert against `$sent->getOriginalMessage()->getTextBody()`, or parse the Symfony `Email`, when checking that the body contains the setup link.
- **The reset token sits in the queue payload.** Queuing `PasswordReset` serializes the token into the queue store (database locally, Cloud's managed queue in production). Laravel natively encrypts it if the notification implements `Illuminate\Contracts\Queue\ShouldBeEncrypted` (`SendQueuedNotifications.php:111`, `Queue.php:293-299`). The spec doesn't ask for this; treat it as optional hardening and raise it with the owner rather than adding it silently.
- **The reset link must not use `url()`.** Build it from `config('admin.url')` plus `route('password.reset', [...], false)`, as `AdminInvitation.php:43-46` does; on a worker, `url()` resolves against `APP_URL` (the marketing host). `route(..., false)` strips the Fortify domain, so the relative path is portable.
- **A sender name with CR/LF.** `sender()` validates the domain; also reject an empty `from_name` and names containing `\r` / `\n` so a stored payload can't inject headers. Lowercase the domain before the exact-match check (`HELLO@BIRDCAR.DEV` should pass for marketing; `news@admin.birdcar.dev` and `x@birdcar.dev.evil.com` must fail).
- **CI is red on main before this phase.** The last five runs failed at "Setup Application" (composer install, likely Flux credentials; out of scope per the contract), so CI gives no green baseline. Verify locally with the full `php artisan test --compact`.
- **`.ai/rules/general.md` is hand-edited.** Change only `ADMIN_URL` → `BIRDCAR_ADMIN_URL` on line 21. New rules go through Boost `record-rule` (Phase 2), and `index.md` must stay untouched.
- **Decision log vs reality.** No contradictions. The logged premises hold: `resend/resend-php ^1.16` is in `composer.json` `require` and committed in 0355f12; MailChannel keeps the Mailable's mailer (`MailChannel.php:62-64`); Fortify serves only the admin host (`config/fortify.php:91`); `toMailUsing()` can't queue. One minor inconsistency sits outside the decision log: the contract's Problem Statement says the SDK "exists only in the uncommitted working tree", which is stale. The claim that production sets none of the old names (`cloud environment:list`) can't be checked from the repo; the Phase 4 gate re-checks it.
