# Implementation Spec: Surface-Scoped Mail - Phase 3

**Contract**: ./contract.md
**Estimated Effort**: M

## Technical Approach

This phase adds a gated Admin page where the owner edits each surface's sender identity (From name, From address, reply-to) without tinker. It follows the existing Publishing settings page exactly. The page is a Livewire single-file component under `resources/views/components/admin/`, reached through an explicit Admin route with `PermissionMiddleware`. It authorizes in `mount` and in every action (`Livewire::test` skips route middleware), keeps only scalars in public state, and resolves settings fresh per request.

Authorization follows `.ai/rules/authorization.md`:
- a new **Mail** domain in `app/Authorization/Mail/{Permission,Role,Catalog}.php`, with one capability `mail.configure-senders` granted to one role `mail.operator`;
- the catalog registered in `config/authorization.php` and synced by `authorization:sync`;
- `admin.view` only admits the Admin surface; the page checks the domain capability.

The root Admin bootstrap bundle in `config/admin.php` gains `mail.operator` as a deliberate extension. The authorization rule anticipates that ("Future Admin modules extend that bundle deliberately"), and its text must be reconciled.

Validation reuses Phase 1's `SurfaceMailSettings::allowsSenderAddress()` and `updateSender()`, so the page and the settings class cannot disagree about allowed domains.

## Decisions Considered and Rejected

_Carried from the contract; consult before making gap decisions._

- **Settings hold per-surface sender identity (from_name, from_address, reply_to); mailer choice and API keys stay in env; the Admin editing page is Full tier**. Rejected: env/config-only senders. Mailer and queue selection is infrastructure nobody picks at runtime, so the page edits senders only and never mailers or keys.
- **Two resend-transport mailers with per-mailer keys**. Rejected: Resend SMTP. The page may show whether each key is configured, as a boolean only.
- **Surfaces select their mailer via BIRDCAR_{SURFACE}_MAIL_MAILER; mail classes declare their surface**. Rejected: global default plus remembering Mail::mailer().
- **All surface mail is a Notification returning a surface Mailable**. Rejected: direct sends, or overriding send().
- **Surface declared by base-class inheritance; queue placement via native attributes**. Rejected: a custom attribute.
- **Rename every surface env var to BIRDCAR_{SURFACE}_***. Rejected: new vars only.
- **"Transactional" = after commit AND separate marketing streams**.
- **The MessageSending guard rejects undeclared or misrouted mail everywhere**. Rejected: production-only relaxations.
- **Queued admin password reset via User::sendPasswordResetNotification()**. Rejected: toMailUsing().
- **Password resets pinned to the admin surface**. Rejected: host detection.
- **Arch testsuite registered in phpunit.xml (critic blocker fix)**. Rejected: running tests/Arch by path.

## Feedback Strategy

**Inner-loop command**: `vendor/bin/pest tests/Feature/Mail/AdminMailSettingsPageTest.php`

**Playground**: Pest with `Livewire::test()` for component state and actions, plus HTTP `get()` on the admin host for route middleware. For the visual check, use the running Herd site `admin.birdcar.test/mail`; find the URL with Boost `get-absolute-url` and build assets with `bun run build` if the page looks unstyled.

**Why this approach**: Authorization and validation are the risky parts, and `Livewire::test` covers them in seconds. The layout is conventional Flux, verified once in the browser.

## File Changes

### New Files

| File Path | Purpose |
| --- | --- |
| `app/Authorization/Mail/Permission.php` | `enum Permission: string { case ConfigureSenders = 'mail.configure-senders'; }` |
| `app/Authorization/Mail/Role.php` | `enum Role: string { case Operator = 'mail.operator'; }` |
| `app/Authorization/Mail/Catalog.php` | `AuthorizationCatalog`: permissions `[ConfigureSenders]`, role `Operator` → `[ConfigureSenders]`. |
| `resources/views/components/admin/mail/⚡settings.blade.php` | Livewire SFC: an Admin section and a Marketing section, each with From name, From address and Reply-to; one Save per surface. |
| `tests/Feature/Mail/AdminMailSettingsPageTest.php` | Page authorization, validation, saving, and secret hygiene. |

### Modified Files

| File Path | Changes |
| --- | --- |
| `config/authorization.php` | Add `MailCatalog::class` to `catalogs`. |
| `config/admin.php` | Append `MailRole::Operator->value` to `bootstrap_roles`. |
| `routes/admin.php` | `Route::livewire('/mail', 'admin.mail.settings')->name('mail.settings')->middleware(PermissionMiddleware::using(MailPermission::ConfigureSenders));` |
| `resources/views/layouts/admin.blade.php` | Sidebar item "Mail" (`icon="envelope"`) inside `@can(MailPermission::ConfigureSenders->value)`, `:current="request()->routeIs('admin.mail.*')"`, mirroring the Publishing item at :28. |
| `app/Mail/Admin/InvitationMail.php` view copy (`resources/views/mail/admin/invitation.blade.php`) | The bundle sentence now reads "…including Admin access, publishing author, and mail sender configuration capabilities." |
| `tests/Feature/Auth/AdminInvitationTest.php` | Role assertions include `mail.operator`; update the copy assertion. |
| `tests/Feature/Authorization/AuthorizationSyncCommandTest.php` | Update any catalog, role or permission counts or lists that now include the Mail domain. |
| `.ai/rules/authorization.md` | Reconcile "Root Admin bootstrap roles" so the bundle names `admin.access`, `publishing.author` and `mail.operator`. Use Boost `record-rule` with the same glob `app/Authorization/**` and title; if it can't replace an entry, edit that sentence in place and leave `index.md` alone. |

### Deleted Files

None.

## Implementation Details

### Mail authorization domain

**Pattern to follow**: `app/Authorization/Publishing/{Permission,Role,Catalog}.php`.

**Overview**: This is enum and config wiring; `authorization:sync` and the page tests verify it.

**Implementation steps**:

1. Create the three files and register the catalog.
2. Add the role to `bootstrap_roles`.
3. Run `php artisan authorization:sync --no-interaction` locally.
4. Your own local account lacks the role. Re-run `php artisan admin:invite <your email>`, which preserves credentials and adds the roles, or assign the role in tinker.

### Mail settings page

**Pattern to follow**: `resources/views/components/admin/publishing/⚡settings.blade.php` (authorization, `refresh()` → mutate → `save()` in try/catch with `report()`, fresh `with()` reads, a `credentialsConfigured` boolean), and `tests/Feature/Publishing/PublishingAgentSettingsTest.php:239-444` for tests.

```php
new #[Layout('layouts.admin'), Title('Mail settings')] class extends Component
{
    /** @var array{admin: array{from_name: string, from_address: string, reply_to: string}, marketing: array{...}} */
    public array $senders = [];

    public ?string $feedback = null;
    public ?string $saveError = null;

    public function mount(): void;              // authorize, fill from AdminMailSettings / MarketingMailSettings
    public function save(string $surface): void; // authorize; $surface ∈ ['admin','marketing'] else ValidationException
    public function with(): array;               // allowed domains per surface, mailer name per surface,
                                                 // keyConfigured booleans (mail.mailers.resend_{surface}.key !== '')
    private function authorizeConfiguration(): void; // Gate::authorize(AdminPermission::View), Gate::authorize(MailPermission::ConfigureSenders)
}
```

Validation for `senders.{surface}`:
- `from_name`: `required|string|max:100`.
- `from_address`: `required|email`, plus a closure rule calling `SurfaceMailSettings::allowsSenderAddress()` on the matching class, with the message "Use an address at {domains}.".
- `reply_to`: `nullable|email|max:254`.

Save with `->refresh()->updateSender($name, $address, $replyTo ?: null)->save()`.

**Key decisions**:

- Each surface has its own Save, so an invalid marketing address can't block an admin change.
- Show the configured mailer name (not secret) and whether each Resend key is configured (a boolean, per `.ai/rules/settings.md`). Never render key values.
- Copy is plain and operator-facing. Example: "Admin mail is sent from this address. It must be at admin.birdcar.dev or birdcar.dev."

**Implementation steps**:

1. Write the page test file with the guest and forbidden cases first.
2. Add the route, component and sidebar item.
3. Add validation and save, then the remaining tests.
4. Run `bun run build`, open the Herd URL, and check the page in light and dark themes.

**Feedback loop**:

- **Playground**: `tests/Feature/Mail/AdminMailSettingsPageTest.php`, then the Herd admin site.
- **Experiment**:
  - an operator saves admin `ops@birdcar.dev` → stored, and after `app()->forgetScopedInstances()` `app(AdminMailSettings::class)->sender()->address === 'ops@birdcar.dev'`;
  - marketing `news@admin.birdcar.dev` gives an error on `senders.marketing.from_address` and leaves the stored value unchanged;
  - `save('customer')` gives a validation error;
  - reply-to `''` stores `null`.
- **Check command**: `vendor/bin/pest tests/Feature/Mail/AdminMailSettingsPageTest.php`

## Testing Requirements

### Feature Tests

| Test File | Coverage |
| --- | --- |
| `tests/Feature/Mail/AdminMailSettingsPageTest.php` | Guest redirect; admin-access-only forbidden (HTTP + `Livewire::test`); operator view and save per surface; off-domain and malformed input rejected with settings unchanged; unknown surface rejected; revoked role can't save; key values never rendered. |
| `tests/Feature/Auth/AdminInvitationTest.php` | The bootstrap bundle includes `mail.operator`; updated copy. |

**Key test cases**:

- Revoke `mail.operator` mid-session, `forgetCachedPermissions()`, then `save('admin')` → forbidden. This mirrors the publishing revoked-role test.
- `config(['mail.mailers.resend_admin.key' => 're_test_secret'])`, then the page `assertDontSee('re_test_secret')`.
- A forged `save('admin')` from a user without the capability is forbidden.

### Manual Testing

- [ ] On `admin.birdcar.test/mail`, both sections render with the seeded values; saving a valid value shows the success feedback; an off-domain value shows the field error.
- [ ] The sidebar shows "Mail" only for accounts with `mail.configure-senders`.

## Error Handling

| Error Scenario | Handling Strategy |
| --- | --- |
| Validation failure | Field-level messages; nothing saved. |
| Save throws (DB, or a stored payload made invalid concurrently) | `report()`, then set `saveError` to "Reload the page to see the saved settings, then try again.", following the Publishing pattern. |
| Unauthorized action | `Gate::authorize` throws 403 in `mount`/`save`. |

## Failure Modes

| Component | Failure Mode | Trigger | Impact | Mitigation |
| --- | --- | --- | --- | --- |
| Page | Middleware-only authorization | Action called via `Livewire::test` or a forged request | Unauthorized save | Authorize in `mount` and `save`; tests cover both |
| Page | Stale state after save | Settings object kept in public state | Wrong values shown | Scalars only; `with()` resolves fresh |
| Page | Secret exposure | Rendering the mailer config | API key leaked in HTML | Booleans only; `assertDontSee` test |
| Bootstrap bundle | Existing admins lack the new role | Admins invited before this phase | Page hidden from the owner | Re-run `admin:invite` for existing accounts (preserves credentials); production has no admins yet |

## Validation Commands

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse --no-progress --memory-limit=1G
php artisan authorization:sync --no-interaction
vendor/bin/pest tests/Feature/Mail/AdminMailSettingsPageTest.php tests/Feature/Auth/AdminInvitationTest.php tests/Feature/Authorization
php artisan test --compact
bun run build
```

## Rollout Considerations

- **Feature flag**: none. The page is visible only to `mail.configure-senders` holders, and Cloud's deploy command already runs `authorization:sync`.
- **Rollback plan**: Revert the phase commit, then run `authorization:sync` (normal sync reports the stale `mail.*` definitions; prune only once no assignments remain).

## Open Items

None.

---

_This spec is ready for implementation. Follow the patterns and validate at each step._
