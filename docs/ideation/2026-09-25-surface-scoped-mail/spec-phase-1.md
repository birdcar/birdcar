# Implementation Spec: Surface-Scoped Mail - Phase 1

**Contract**: ./contract.md
**Estimated Effort**: L

## Technical Approach

Every email Birdcar sends belongs to a **surface**: admin (admin.birdcar.dev) or marketing (birdcar.dev). The customer surface comes later. A surface is made of three Laravel-native parts:

1. **A concrete mailer entry** in `config/mail.php`. `resend_admin` and `resend_marketing` each carry their own Resend `key`, which Laravel's resend transport reads before falling back to `services.resend.key` (`vendor/laravel/framework/src/Illuminate/Mail/MailManager.php:323`).
2. **A per-surface mailer choice** in `config/admin.php` / `config/marketing.php` under `mail.mailer`, read from `BIRDCAR_{SURFACE}_MAIL_MAILER`. That is `smtp` locally (Herd), `array` in tests, and `resend_admin`/`resend_marketing` in production. Each surface also lists the sender domains its Resend key may use.
3. **A per-surface sender identity** in spatie/laravel-settings: `AdminMailSettings`, `MarketingMailSettings`. It is read on every send so the owner can change it without a deploy.

Mail classes declare their surface by inheritance: `App\Mail\SurfaceMailable` → `App\Mail\Admin\AdminMailable` / `App\Mail\Marketing\MarketingMailable` → concrete Mailables. The base defines a **`final public function build(): void`**. `Mailable::send()` runs `prepareMailableForDelivery()`, which calls `build()` through the container *before* it resolves the mailer (`Mailable.php:202-209`, `:1733-1743`). So `build()` can set `$this->mailer`, `from` and `replyTo` from the surface at a documented seam. Because `build()` runs first, its `from` becomes `$this->from[0]`, the address `buildFrom()` uses (`Mailable.php:460-467`). A subclass's `envelope()` cannot override the sender. Making `build()` `final` stops a subclass from dropping the surface wiring.

Surface mail is sent only through Notifications whose `toMail()` returns a surface Mailable. `MailChannel` then calls `$mailable->send($factory)` (`Notifications/Channels/MailChannel.php:62-64`), the factory path that keeps the Mailable's declared mailer. Two notifications move onto the admin surface:

- the existing `AdminInvitation`, which stays synchronous so `admin:invite` can report whether delivery was accepted;
- a new queued `App\Notifications\Admin\PasswordReset`, sent through the documented `User::sendPasswordResetNotification()` hook. It replaces Fortify's stock `ResetPassword` and builds its link from `config('admin.url')`, because a queue worker has no request host.

The env vars for surface URLs are also renamed to `BIRDCAR_{SURFACE}_*`. Phase 2 adds the runtime guard and arch tests. This phase must leave the suite green without them.

## Decisions Considered and Rejected

_Carried from the contract; consult before making gap decisions._

- **Two resend-transport mailers with per-mailer keys; resend/resend-php installed**. Rejected: Resend SMTP mailers with no new dependency. The native resend transport is the documented driver and returns Resend message IDs; the owner approved the dependency, already committed in 0355f12.
- **Surfaces select their mailer via BIRDCAR_{SURFACE}_MAIL_MAILER; mail classes declare their surface**. Rejected: MAIL_MAILER=resend_admin plus remembering Mail::mailer('resend_marketing'). That is non-obvious "remember to do this" configuration, and Laravel's $mailer overwrite makes mistakes silent.
- **All surface mail is a Notification whose toMail() returns a surface Mailable**. Rejected: direct Mail::mailer(...)->send() with a runtime guard, or overriding Mailable::send()/mailer(). MailChannel keeps the Mailable's declared mailer (MailChannel.php:63); overriding send() replaces framework internals.
- **Surface declared by extending a surface base Mailable; queue placement via native #[Connection]/#[Queue] class attributes**. Rejected: a custom #[Mailer]/#[Surface] attribute. Laravel has none; inventing one breaks the rule of extending only at Laravel's seams.
- **Rename every surface env var to BIRDCAR_{SURFACE}_* now**. Rejected: prefix only the new mail vars. Two naming schemes would coexist; production sets none of the old names (`cloud environment:list`).
- **"Transactional" = queued mail waits for the commit (ShouldQueueAfterCommit) AND marketing stays on its own mailer, key and sender**. The invite stays synchronous for delivery feedback.
- **Settings hold per-surface sender identity; mailer choice and keys stay in env; the Admin page is Phase 3**. Rejected: env/config-only senders. Mailer and queue selection is infrastructure nobody picks at runtime.
- **A MessageSending listener rejects undeclared or misrouted mail in every environment (Phase 2)**. Rejected: reject only outside production, or static checks only.
- **The admin password reset is queued through our own notification via User::sendPasswordResetNotification()**. Rejected: ResetPassword::toMailUsing() (synchronous, cannot queue). The owner chose queued to keep delivery out of the request. Fortify sends outside a transaction, so ShouldQueueAfterCommit comes from the project-wide rule for queued mail.
- **Password resets use the admin surface until customer auth exists, pinned by a test**. Rejected: choose the surface from the request host. Fortify only serves the admin host.
- **Register tests/Arch as an Arch testsuite (critic blocker fix, Phase 2)**. Rejected: running tests/Arch by path. The suite and CI would never run it.

## Feedback Strategy

**Inner-loop command**: `vendor/bin/pest tests/Feature/Mail`

**Playground**: The Pest test suite. `phpunit.xml` pins both surface mailers to `array`, so every send can be inspected through `app(MailManager::class)->mailer('array')->getSymfonyTransport()->messages()` without network access.

**Why this approach**: Every component is config, settings or Mailable/notification wiring, and its correct behavior shows up in the message a transport receives. Feature tests against the array transport are the tightest loop and need no browser.

## File Changes

### New Files

| File Path | Purpose |
| --- | --- |
| `app/Mail/SurfaceMailable.php` | Abstract base. `final build()` sets the surface mailer, From and reply-to on every send; abstract `surfaceMailer()` / `senderSettings()`. |
| `app/Mail/Admin/AdminMailable.php` | Abstract admin-surface base. The mailer comes from `config('admin.mail.mailer')` and the sender from `AdminMailSettings`. |
| `app/Mail/Admin/InvitationMail.php` | Admin invitation Mailable (`envelope()` subject and markdown `content()`), with public `setupUrl` and `expiresInMinutes`. |
| `app/Mail/Admin/PasswordResetMail.php` | Admin password reset Mailable, with public `resetUrl` and `expiresInMinutes`. |
| `app/Mail/Marketing/MarketingMailable.php` | Abstract marketing-surface base. The mailer comes from `config('marketing.mail.mailer')` and the sender from `MarketingMailSettings`. |
| `app/Settings/SurfaceMailSettings.php` | Abstract spatie Settings base: `from_name`, `from_address`, `reply_to`, domain validation, `sender()`, `replyToAddress()`, `updateSender()`. |
| `app/Settings/AdminMailSettings.php` | Group `admin_mail`; allowed domains from `config('admin.mail.sender_domains')`. |
| `app/Settings/MarketingMailSettings.php` | Group `marketing_mail`; allowed domains from `config('marketing.mail.sender_domains')`. |
| `database/settings/2026_09_25_120000_create_surface_mail_settings.php` | Seeds both groups (defaults below). |
| `app/Notifications/Admin/PasswordReset.php` | Queued (`ShouldQueueAfterCommit`) notification whose `toMail()` returns `PasswordResetMail`. |
| `resources/views/mail/admin/invitation.blade.php` | Markdown mail view (`<x-mail::message>`) with the invitation copy, moved unchanged from the MailMessage. |
| `resources/views/mail/admin/password-reset.blade.php` | Markdown mail view for the reset email. |
| `tests/Fixtures/Mail/RecordingTransport.php` | Symfony `AbstractTransport` that stores sent messages. It is not an `ArrayTransport`, so `InviteAdministrator` accepts it. |
| `tests/Fixtures/Mail/ExampleAdminMail.php`, `ExampleMarketingMail.php`, `ExampleSurfaceNotification.php` | Test-only concrete surface Mailables and an on-demand notification for settings tests. Phase 2 guard tests reuse them. |
| `tests/Feature/Mail/SurfaceSenderSettingsTest.php` | Sender identity read per send; off-domain rejected on save and on load, for each surface. |
| `tests/Feature/Mail/AdminPasswordResetTest.php` | The reset is queued, delivered on the admin mailer with an admin-origin link, and pinned to the admin surface. |

### Modified Files

| File Path | Changes |
| --- | --- |
| `config/mail.php` | Replace the stock `resend` mailer with `resend_admin` (`key` ← `BIRDCAR_ADMIN_RESEND_API_KEY`) and `resend_marketing` (`key` ← `BIRDCAR_MARKETING_RESEND_API_KEY`). Leave the global `from` alone. |
| `config/services.php` | Remove the `resend` entry; nothing reads it now. |
| `config/admin.php` | `env('ADMIN_URL', …)` → `env('BIRDCAR_ADMIN_URL', …)`. Add `'mail' => ['mailer' => env('BIRDCAR_ADMIN_MAIL_MAILER', 'log'), 'sender_domains' => ['admin.birdcar.dev', 'birdcar.dev']]`. |
| `config/marketing.php` | `MARKETING_URL` → `BIRDCAR_MARKETING_URL`, `MARKETING_INDEXABLE` → `BIRDCAR_MARKETING_INDEXABLE`. Add `'mail' => ['mailer' => env('BIRDCAR_MARKETING_MAIL_MAILER', 'log'), 'sender_domains' => ['birdcar.dev']]`. |
| `config/settings.php` | Add `AdminMailSettings::class` and `MarketingMailSettings::class` to `settings`. |
| `phpunit.xml` | Add `<env name="BIRDCAR_ADMIN_MAIL_MAILER" value="array"/>` and `<env name="BIRDCAR_MARKETING_MAIL_MAILER" value="array"/>`. |
| `.env.example` | Rename to `BIRDCAR_ADMIN_URL` / commented `BIRDCAR_MARKETING_URL` / `BIRDCAR_MARKETING_INDEXABLE`. Add `BIRDCAR_ADMIN_MAIL_MAILER=log`, `BIRDCAR_ADMIN_RESEND_API_KEY=`, `BIRDCAR_MARKETING_MAIL_MAILER=log`, `BIRDCAR_MARKETING_RESEND_API_KEY=`, with a one-line comment each. |
| `.env` (local, untracked) | Rename keys only: `ADMIN_URL`→`BIRDCAR_ADMIN_URL`, `MARKETING_URL`→`BIRDCAR_MARKETING_URL`, `MARKETING_INDEXABLE`→`BIRDCAR_MARKETING_INDEXABLE` where present. Add `BIRDCAR_ADMIN_MAIL_MAILER=smtp` and `BIRDCAR_MARKETING_MAIL_MAILER=smtp`. **Never print, change or move any value**, including the two `BIRDCAR_*_RESEND_API_KEY` lines already there. |
| `docs/production-setup.md` | Rename the three env names at :41-43 (full mail guidance is rewritten in Phase 2). |
| `docs/development-setup.md` | Rename the env names at :47-49, :59, :298. |
| `.ai/rules/general.md` | ":21 set the nonsecret ADMIN_URL" → `BIRDCAR_ADMIN_URL`. Edit only the name, and keep `.ai/rules/index.md` untouched. |
| `app/Notifications/AdminInvitation.php` | `toMail()` returns `InvitationMail` addressed to `$notifiable->routeNotificationFor('mail', $this)`. Move `resetUrl()` / `expiresInMinutes()` logic into the Mailable's construction (or keep it here and pass values in). |
| `app/Actions/Admin/InviteAdministrator.php` | `assertSafeMailer()` reads `config('admin.mail.mailer')` instead of `mail.default`; messages say `[admin.mail.mailer]`. Before provisioning, also call `app(AdminMailSettings::class)->sender()` and convert an `InvalidArgumentException` into `AdminInvitationException('Admin mail sender settings are invalid; fix them before inviting.')`. |
| `app/Models/User.php` | Add `sendPasswordResetNotification(#[\SensitiveParameter] $token): void` → `$this->notify(new PasswordReset($token));`. |
| `tests/Feature/Auth/AdminInvitationTest.php` | Change `beforeEach` from `mail.default`/`mail.mailers.smtp.*` to `admin.mail.mailer`. Change every mailer-safety test to set `admin.mail.mailer`. Replace `$message->actionUrl` / `introLines` / `outroLines` with `InvitationMail` properties and `assertSeeInText()`. Add the recording-transport delivery test (below). |

### Deleted Files

None.

## Implementation Details

### Config and env rename

**Pattern to follow**: `config/admin.php` (validated URL with fallback).

**Overview**: This is config only; phpstan and the grep criterion verify it. No feedback loop is needed.

```php
// config/mail.php (mailers)
'resend_admin' => [
    'transport' => 'resend',
    'key' => env('BIRDCAR_ADMIN_RESEND_API_KEY'),
],
'resend_marketing' => [
    'transport' => 'resend',
    'key' => env('BIRDCAR_MARKETING_RESEND_API_KEY'),
],

// config/admin.php
'mail' => [
    'mailer' => env('BIRDCAR_ADMIN_MAIL_MAILER', 'log'),
    'sender_domains' => ['admin.birdcar.dev', 'birdcar.dev'],
],
```

**Key decisions**:

- The `log` default is deliberately unsafe: `admin:invite` refuses it, and the production gate sets `resend_admin`.
- Sender domains are hard-coded config, not env, because they are facts about which domains each Resend key may use, and they don't change per environment. The admin key may use every configured domain; the marketing key only `birdcar.dev` (exact match, no subdomains).

**Implementation steps**:

1. Edit the config files and `phpunit.xml`.
2. Edit `.env.example`, docs and `.ai/rules/general.md` names.
3. Rename the local `.env` keys with a key-anchored `sed` (e.g. `sed -i '' -E 's/^ADMIN_URL=/BIRDCAR_ADMIN_URL=/' .env`), then append the two `*_MAIL_MAILER=smtp` lines if missing.
4. Run `php artisan config:clear`.
5. Run the contract's env grep: `! grep -rqE "env\('(ADMIN|MARKETING|RESEND)_" config && ! grep -rqE "(^|[^_A-Z])(ADMIN_URL|MARKETING_URL|MARKETING_INDEXABLE)" .env.example .ai/rules docs/production-setup.md docs/development-setup.md`. The positive half passes once all seven BIRDCAR names are read.

### Surface sender settings

**Pattern to follow**: `app/Settings/PublishingAgentSettings.php` (re-validate stored payloads) and `database/settings/2026_09_24_230000_create_publishing_agent_settings.php`.

**Overview**: This holds sender identity per surface. It is read per send because spatie registers settings as `scoped()`, so there is no stale sender in a warm worker.

```php
abstract class SurfaceMailSettings extends Settings
{
    public string $from_name;
    public string $from_address;
    public ?string $reply_to;

    /** @return list<string> Lowercased domains this surface's Resend key may send from. */
    abstract public static function allowedSenderDomains(): array;

    public static function allowsSenderAddress(string $address): bool; // valid email AND exact domain in allowedSenderDomains()

    /** @throws InvalidArgumentException when the stored name/address is empty or off-domain. */
    public function sender(): Address;          // Illuminate\Mail\Mailables\Address

    public function replyToAddress(): ?Address; // null when reply_to is null/''; throws when not a valid email

    /** Validates, then assigns; the caller saves. */
    public function updateSender(string $name, string $address, ?string $replyTo): static;
}
```

Seeded defaults (settings migration):

| Key | Value |
| --- | --- |
| `admin_mail.from_name` | `Birdcar` |
| `admin_mail.from_address` | `noreply@admin.birdcar.dev` |
| `admin_mail.reply_to` | `null` |
| `marketing_mail.from_name` | `Birdcar` |
| `marketing_mail.from_address` | `hello@birdcar.dev` |
| `marketing_mail.reply_to` | `null` |

**Key decisions**:

- `SurfaceMailSettings` is abstract. spatie's discovery only registers instantiable classes (`vendor/spatie/laravel-settings/src/Support/DiscoverSettings.php:71`), and the two concrete classes are also listed in `config/settings.php`.
- Validation runs both on `updateSender()` (write) and in `sender()` (read), because stored payloads aren't type-checked on load. This matches the PublishingAgentSettings comment at :68.

**Implementation steps**:

1. Create `tests/Feature/Mail/SurfaceSenderSettingsTest.php` with one smoke test asserting the seeded admin sender.
2. `php artisan make:settings`-equivalent: write the three classes by hand, following the existing class, then the migration.
3. Register the classes in `config/settings.php`.

**Feedback loop**:

- **Playground**: `tests/Feature/Mail/SurfaceSenderSettingsTest.php`.
- **Experiment**: Datasets per surface:
  - `noreply@admin.birdcar.dev` and `ops@birdcar.dev` are accepted for admin;
  - `hello@birdcar.dev` is accepted for marketing;
  - `news@admin.birdcar.dev` is rejected for marketing (subdomain);
  - `x@birdcar.dev.evil.com`, `x@example.com`, `not-an-email` and `''` are rejected for both;
  - an off-domain payload written with `DB::table('settings')->update(...)` makes `sender()` throw after `app()->forgetScopedInstances()`.
- **Check command**: `vendor/bin/pest tests/Feature/Mail/SurfaceSenderSettingsTest.php`

### Surface Mailables

**Pattern to follow**: Laravel's `Mailable` with `envelope()`/`content()` (see https://laravel.com/docs/13.x/mail). There is no in-repo precedent; this is the first `app/Mail` class. Create each class with `php artisan make:mail`.

**Overview**: The surface is declared by the parent class, and the sender and mailer are set in one `final` hook.

```php
abstract class SurfaceMailable extends Mailable
{
    /** Mailer name for this surface, from config; throws LogicException when blank or undefined in mail.mailers. */
    abstract public static function surfaceMailer(): string;

    abstract protected function senderSettings(): SurfaceMailSettings;

    final public function build(): void
    {
        $settings = $this->senderSettings();
        $sender = $settings->sender();

        $this->mailer(static::surfaceMailer());
        $this->from($sender->address, $sender->name);

        if (($replyTo = $settings->replyToAddress()) !== null) {
            $this->replyTo($replyTo->address, $replyTo->name);
        }
    }
}

abstract class AdminMailable extends SurfaceMailable
{
    public static function surfaceMailer(): string; // config('admin.mail.mailer'), validated
    protected function senderSettings(): SurfaceMailSettings { return app(AdminMailSettings::class); }
}
```

`InvitationMail` / `PasswordResetMail` implement `envelope()` (subject only, **never `from`**) and `content()` (markdown view), and expose the URL and expiry as public readonly properties. Mark the URL constructor parameters `#[\SensitiveParameter]`, like `AdminInvitation`. Their subjects and copy are the current `AdminInvitation` lines, unchanged:
- Invitation subject: "Set up your Birdcar Admin access".
- Reset subject: "Reset your Birdcar Admin password". The reset copy is a short version of Laravel's stock reset lines, with the expiry line.

**Key decisions**:

- `build()` resolves settings, which makes it the single point that fails loudly on a bad sender. An exception there surfaces as `AdminInvitationDeliveryException` for invites and as a failed job for queued mail.
- `surfaceMailer()` is `static` so Phase 2's guard can call it from the class name in `MessageSending` data (`__laravel_mailable`).

**Implementation steps**:

1. Write `ExampleAdminMail` and `ExampleMarketingMail` fixtures and an `ExampleSurfaceNotification`. The notification is `via(['mail'])` and `toMail()` returns the fixture Mailable `->to(...)`.
2. Add a failing test that sends the fixture through `Notification::route('mail', 'owner@example.com')->notify(...)` and asserts the array transport received one message from the seeded sender.
3. Implement the bases and make the test pass.
4. Add the same-process change test: send, `app(AdminMailSettings::class)->updateSender(...)->save()`, `app()->forgetScopedInstances()`, send again, and assert the second From changed.

**Feedback loop**:

- **Playground**: `SurfaceSenderSettingsTest.php` with the fixtures.
- **Experiment**:
  - admin and marketing fixtures each land on the array transport with their own sender;
  - `reply_to` null vs `ops@birdcar.dev` gives no Reply-To header vs one;
  - a fixture whose `envelope()` sets `from: new Address('spoof@example.com')` still sends from the settings sender;
  - `admin.mail.mailer` set to `''` or to `'missing'` makes the send throw `LogicException`.
- **Check command**: `vendor/bin/pest tests/Feature/Mail/SurfaceSenderSettingsTest.php`

### Admin invitation on the admin surface

**Pattern to follow**: The existing `app/Notifications/AdminInvitation.php` and `tests/Feature/Auth/AdminInvitationTest.php`.

**Overview**: This is the same flow and the same synchronous delivery, now routed by the admin surface.

**Implementation steps**:

1. In `InviteAdministrator::assertSafeMailer()`, replace `config('mail.default')` with `config('admin.mail.mailer')` and update the message text. `assertSafeMailerNamed()` is unchanged.
2. Add the sender pre-flight after the mailer check and before `provisionUser()`.
3. `AdminInvitation::toMail()` returns `(new InvitationMail($setupUrl, $expires))->to($notifiable->routeNotificationFor('mail', $this))`, with return type `InvitationMail`.
4. Rewrite `AdminInvitationTest`: `beforeEach` sets `admin.mail.mailer` → `smtp` (plus the smtp host and port as today). Every test that set `mail.default` now sets `admin.mail.mailer`. Assertions on `actionUrl`/`introLines`/`outroLines` become `$mail->setupUrl`, `$mail->expiresInMinutes` and `$mail->assertSeeInText('An operator invited this account…')`.
5. Add the test `invitations are delivered on the admin surface mailer from the admin sender`:
   - `app(MailManager::class)->extend('recording', fn () => $transport = new RecordingTransport)`;
   - `config(['mail.mailers.recording_admin' => ['transport' => 'recording'], 'admin.mail.mailer' => 'recording_admin'])`;
   - no `Notification::fake()`;
   - run `admin:invite`;
   - assert exit 0, one recorded message, From `noreply@admin.birdcar.dev`, and a body containing the setup link.
6. Add the test `invalid admin sender settings fail before provisioning`: corrupt `admin_mail.from_address` through the DB, then assert exit 1 and that no user was created.

**Feedback loop**:

- **Playground**: `tests/Feature/Auth/AdminInvitationTest.php`.
- **Experiment**:
  - `admin.mail.mailer` ∈ {`array`, `log`, a failover that includes `log`} is rejected before provisioning;
  - `smtp` and the recording transport are accepted;
  - with `mail.default` set to `log` and `admin.mail.mailer` to the recording mailer, the invite succeeds, which proves `mail.default` is no longer read.
- **Check command**: `vendor/bin/pest tests/Feature/Auth/AdminInvitationTest.php`

### Queued admin password reset

**Pattern to follow**: `AdminInvitation::resetUrl()` builds the link from the admin origin plus a relative route (`app/Notifications/AdminInvitation.php:37-47`).

```php
class PasswordReset extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(#[\SensitiveParameter] public string $token) {}

    /** @return list<string> */
    public function via(object $notifiable): array { return ['mail']; }

    public function toMail(object $notifiable): PasswordResetMail
    {
        // rtrim(config('admin.url'), '/').route('password.reset', ['token' => …, 'email' => $notifiable->getEmailForPasswordReset()], false)
    }
}
```

**Key decisions**:

- The link must not use `url()`. On a queue worker it resolves against `APP_URL`, the marketing host, while Fortify's routes live on `config('admin.host')` (`config/fortify.php:91`).
- There's no `#[Queue]` attribute; it uses the default connection and queue, which is Cloud's managed `default` queue in production.

**Implementation steps**:

1. Create `tests/Feature/Mail/AdminPasswordResetTest.php` with the cases below. Make them fail first.
2. Add the notification, Mailable and view, and override `User::sendPasswordResetNotification()`.

**Feedback loop**:

- **Playground**: `AdminPasswordResetTest.php`.
- **Experiment**:
  - (a) POST to Fortify's `password.email` route on the admin host with `Notification::fake()` → `PasswordReset` is sent to the user, and stock `ResetPassword` is not;
  - (b) `Queue::fake()` + `$user->sendPasswordResetNotification('t')` → `SendQueuedNotifications` is pushed wrapping `PasswordReset`;
  - (c) with the sync queue, `config(['app.url' => 'https://birdcar.test', 'admin.url' => 'https://admin.birdcar.test'])`, and no request → the array transport's message links to `https://admin.birdcar.test/reset-password/…` and is From `noreply@admin.birdcar.dev`;
  - (d) the pin test: `(new PasswordReset('t'))->toMail($user)` is an `AdminMailable`. Its failure message names this contract's Future item (surface-aware resets once customer auth exists).
- **Check command**: `vendor/bin/pest tests/Feature/Mail/AdminPasswordResetTest.php`

## Testing Requirements

### Feature Tests

| Test File | Coverage |
| --- | --- |
| `tests/Feature/Mail/SurfaceSenderSettingsTest.php` | Seeded senders, per-send reads, off-domain rejection on save and load, reply-to, envelope `from` can't override, blank or undefined surface mailer throws. |
| `tests/Feature/Mail/AdminPasswordResetTest.php` | Fortify reset uses PasswordReset; it is queued; the link host is the admin origin outside a request; delivered on the admin mailer; pinned to the admin surface. |
| `tests/Feature/Auth/AdminInvitationTest.php` | Existing coverage retargeted to `admin.mail.mailer`; recording-transport delivery; sender pre-flight. |

**Key test cases**:

- Off-domain senders are rejected for marketing: `news@admin.birdcar.dev` and `x@birdcar.dev.evil.com`.
- A sender changed in the same process shows up on the next send.
- The invite succeeds when `mail.default=log`, which proves the global default is ignored.
- The queued reset link host comes from `admin.url`, not `app.url`.

### Manual Testing

- [ ] Locally, with Herd mail running and `BIRDCAR_ADMIN_MAIL_MAILER=smtp`, run `php artisan admin:invite you@example.com`. Herd's mail UI shows From `noreply@admin.birdcar.dev` and a working `admin.birdcar.test` link.

## Error Handling

| Error Scenario | Handling Strategy |
| --- | --- |
| Surface mailer config blank or not defined in `mail.mailers` | `surfaceMailer()` throws `LogicException` naming the config key. |
| Stored sender off-domain or malformed | `sender()` throws `InvalidArgumentException`. The invite converts it to `AdminInvitationException` before provisioning. |
| Resend key env missing in production | `Resend::client(null)` fails in `MailManager`. The invite's existing `assertSafeMailerNamed()` reports "could not be resolved for invitation delivery". |
| Queued reset fails on the worker | The job fails with the exception and the reset link isn't sent. The user can request again; Fortify's throttle applies. |

## Failure Modes

| Component | Failure Mode | Trigger | Impact | Mitigation |
| --- | --- | --- | --- | --- |
| Env rename | Old name still set in an environment | A Cloud env that sets `ADMIN_URL` | The fallback `https://admin.birdcar.dev` is used; the value is silently ignored | The gate re-checks Cloud env (already verified empty); docs updated in this phase |
| Surface Mailable | Subclass sets `from` in `envelope()` | An agent copies a stock Mailable | Ignored: base `from[0]` wins | Test asserts it; Phase 2 rule documents it |
| Surface Mailable | Subclass defines its own `build()` | An agent adds legacy `build()` | PHP fatal: `build()` is final | Fails at class load in tests |
| Sender settings | Stale sender in a warm worker | Settings singleton cached | Wrong From after a change | spatie `scoped()` binding; same-process test with `forgetScopedInstances()` |
| Sender settings | Data shadow: settings row missing | Settings migration not run | `MissingSettings` exception on send | Cloud deploy runs `migrate --force`; the invite pre-flight fails before provisioning |
| Password reset | Marketing-host link | `url()` on a worker | Broken reset link | Link built from `config('admin.url')`; test (c) |
| Local `.env` edit | Secret values disturbed | A careless rewrite | Local Resend keys lost or leaked | Key-anchored `sed` renames only; never `cat` or echo `.env` |

## Validation Commands

```bash
# Formatting
vendor/bin/pint --dirty --format agent

# Static analysis
vendor/bin/phpstan analyse --no-progress --memory-limit=1G

# Phase tests
vendor/bin/pest tests/Feature/Mail tests/Feature/Auth/AdminInvitationTest.php

# Full suite
php artisan test --compact

# Env names (contract criterion)
! grep -rqE "env\('(ADMIN|MARKETING|RESEND)_" config && ! grep -rqE "(^|[^_A-Z])(ADMIN_URL|MARKETING_URL|MARKETING_INDEXABLE)" .env.example .ai/rules docs/production-setup.md docs/development-setup.md && for v in ADMIN_URL MARKETING_URL MARKETING_INDEXABLE ADMIN_MAIL_MAILER MARKETING_MAIL_MAILER ADMIN_RESEND_API_KEY MARKETING_RESEND_API_KEY; do grep -rq "env('BIRDCAR_$v'" config || exit 1; done
```

## Rollout Considerations

- **Feature flag**: none. Production behavior doesn't change until the gate sets `BIRDCAR_ADMIN_MAIL_MAILER`, because the default `log` makes invites refuse to run.
- **Rollback plan**: Revert the phase commit. No schema changes, only settings rows, which `down()` removes.

## Open Items

None.

---

_This spec is ready for implementation. Follow the patterns and validate at each step._
