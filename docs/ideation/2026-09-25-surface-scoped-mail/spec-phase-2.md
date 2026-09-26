# Implementation Spec: Surface-Scoped Mail - Phase 2

**Contract**: ./contract.md
**Estimated Effort**: M

## Technical Approach

Phase 1 made surface mail the easy path. This phase makes every other path fail, both at runtime and in CI, using only Laravel and Pest seams:

1. **Runtime guard.** `App\Listeners\RejectUnscopedMail` handles `Illuminate\Mail\Events\MessageSending`. That event fires inside `Mailer::send()` for every real send, via `events->until()` (`vendor/laravel/framework/src/Illuminate/Mail/Mailer.php:602-611`). Its `$data` carries two keys:
   - `mailer`, the resolved mailer name (`Mailer.php:304-305`);
   - `__laravel_mailable`, the Mailable class (`Mailable.php:397-402`), or `__laravel_notification*` keys for `MailMessage` notifications (`MailChannel.php:153-161`).

   The listener throws a `LogicException` unless the message comes from a `SurfaceMailable` sent on that class's `surfaceMailer()`. There is no environment branch: production behaves exactly like testing. Laravel's default event discovery finds listeners in `app/Listeners` by the `handle()` type-hint, so no manual registration is needed. Create it with `php artisan make:listener`.
2. **Static guard.** Pest arch rules in `tests/Arch/MailTest.php`, registered as a third testsuite in `phpunit.xml`, so `php artisan test` and CI (`composer ci:check`) run them:
   - no Mail facade, mail contracts, `PendingMail`, PHP `mail()` or `MailMessage` in `App`;
   - `MailManager` is allowed only in `InviteAdministrator`, which uses it to validate transports;
   - every class in `App\Mail` extends `SurfaceMailable`;
   - no class in `App\Mail` implements `ShouldQueue`, because `MailChannel` sends a returned Mailable synchronously and queueing belongs on the notification;
   - every queued notification implements `ShouldQueueAfterCommit`.
3. **Records.**
   - A mail rule recorded with Boost's `record-rule`, so the next agent learns the surface pattern before writing mail code. `.ai/rules/index.md` is generated; never hand-edit it.
   - The setup docs' mail guidance rewritten for the surface model. Phase 1 renamed only the env names.

## Decisions Considered and Rejected

_Carried from the contract; consult before making gap decisions._

- **Two resend-transport mailers with per-mailer keys; resend/resend-php installed**. Rejected: Resend SMTP mailers. The native transport is documented and returns message IDs.
- **Surfaces select their mailer via BIRDCAR_{SURFACE}_MAIL_MAILER; mail classes declare their surface**. Rejected: MAIL_MAILER=resend_admin plus remembering Mail::mailer(). Non-obvious, and misroutes are silent.
- **All surface mail is a Notification whose toMail() returns a surface Mailable**. Rejected: direct Mail sends plus a guard, or overriding Mailable::send(). MailChannel keeps the declared mailer; overriding send() replaces framework internals.
- **Surface declared by extending a surface base Mailable; queue placement via native #[Connection]/#[Queue]**. Rejected: a custom #[Mailer]/#[Surface] attribute, which is not native.
- **Rename every surface env var to BIRDCAR_{SURFACE}_* now**. Rejected: prefix only the new vars.
- **"Transactional" = queued mail waits for the commit AND marketing stays on its own mailer, key and sender**.
- **Settings hold sender identity; mailer choice and keys stay in env**. Rejected: env-only senders, or the page in MVP.
- **A MessageSending listener rejects undeclared or misrouted mail in every environment**. Rejected: reject only outside production, or rely on static checks alone. The owner wants direct sends blocked, not logged.
- **Queued admin password reset via User::sendPasswordResetNotification()**. Rejected: ResetPassword::toMailUsing(), which cannot queue.
- **Password resets pinned to the admin surface until customer auth exists**. Rejected: choosing the surface by host.
- **Register tests/Arch as an Arch testsuite and check it with --testsuite=Arch (critic blocker fix)**. Rejected: running `vendor/bin/pest tests/Arch` by path. phpunit.xml registers only Unit and Feature, so the guards would never run in the suite or CI.

## Feedback Strategy

**Inner-loop command**: `vendor/bin/pest tests/Feature/Mail/SurfaceMailGuardTest.php`

**Playground**: Pest. Each guard case gets its own mailer name backed by an array transport, so a misroute is distinguishable from a correct send even though `phpunit.xml` pins both surfaces to `array`.

**Why this approach**: The guard is event logic whose only observable outputs are "threw" or "the transport received a message". Feature tests measure exactly that in milliseconds. Arch rules have their own quick check: `php artisan test --compact --testsuite=Arch`.

## File Changes

### New Files

| File Path | Purpose |
| --- | --- |
| `app/Listeners/RejectUnscopedMail.php` | `MessageSending` listener that throws on undeclared or misrouted mail. |
| `tests/Feature/Mail/SurfaceMailGuardTest.php` | Every rejected path throws and leaves its transport empty; correct surface sends pass; forced-production case. |
| `tests/Arch/MailTest.php` | Pest arch rules plus the queued-notification after-commit rule. |
| `.ai/rules/mail.md` | Created through Boost `record-rule` (not by hand); `index.md` is regenerated by Boost. |

### Modified Files

| File Path | Changes |
| --- | --- |
| `phpunit.xml` | Add `<testsuite name="Arch"><directory>tests/Arch</directory></testsuite>` after Feature. |
| `docs/production-setup.md` | Replace the :58 bullet ("Configure a real delivery-capable `MAIL_MAILER` … `MAIL_FROM_ADDRESS`/`MAIL_FROM_NAME`") with the surface model. Set `BIRDCAR_ADMIN_MAIL_MAILER=resend_admin` and `BIRDCAR_ADMIN_RESEND_API_KEY`; the sender comes from admin mail settings (seeded `noreply@admin.birdcar.dev`); verify the sending domain in Resend first. Marketing keys are optional until marketing mail exists. |
| `docs/development-setup.md` | Rewrite the :112-125 block. Set `BIRDCAR_ADMIN_MAIL_MAILER=smtp` (plus the existing `MAIL_HOST`/`MAIL_PORT` for the `smtp` mailer). `MAIL_FROM_*` no longer sets the sender of surface mail; admin mail settings do. Invites still reject log, array, null and unsafe aggregate transports. |

### Deleted Files

None.

## Implementation Details

### Runtime guard

**Pattern to follow**: Laravel event listener discovery (`php artisan make:listener RejectUnscopedMail --event='Illuminate\Mail\Events\MessageSending'`).

```php
namespace App\Listeners;

use App\Mail\SurfaceMailable;
use Illuminate\Mail\Events\MessageSending;
use LogicException;

class RejectUnscopedMail
{
    public function handle(MessageSending $event): void
    {
        $mailable = $event->data['__laravel_mailable'] ?? null;
        $mailer = $event->data['mailer'] ?? null;

        if (! is_string($mailable) || ! is_subclass_of($mailable, SurfaceMailable::class)) {
            // Name what was sent: the mailable class, the __laravel_notification class, or "a raw message".
            throw new LogicException('...must be a surface Mailable returned from a notification...');
        }

        $expected = $mailable::surfaceMailer();

        if ($mailer !== $expected) {
            throw new LogicException("[{$mailable}] must be sent on mailer [{$expected}], not [{$mailer}].");
        }
    }
}
```

**Key decisions**:

- Return `void`, never `false`. Returning `false` from `until()` cancels silently, which is the failure this project exists to prevent.
- Use a plain `LogicException` rather than a new exception class. `app/Exceptions` doesn't exist, and a class in `App\Mail` would break the extends rule.
- The guard doesn't inspect From. `SurfaceMailable::build()` is `final` and its `from[0]` always wins (Phase 1 test).

**Implementation steps**:

1. Write `SurfaceMailGuardTest.php` first. `beforeEach` sets:
   - `mail.mailers.array_default`, `array_admin` and `array_marketing` (all `['transport' => 'array']`);
   - `mail.default = array_default`;
   - `admin.mail.mailer = array_admin`;
   - `marketing.mail.mailer = array_marketing`.

   Reuse the Phase 1 fixtures from `tests/Fixtures/Mail`.
2. Generate the listener. Confirm discovery with `php artisan event:list --event='Illuminate\Mail\Events\MessageSending'`.
3. Implement until green, then run the whole suite. A newly failing test reveals an undeclared send path that Phase 1 missed.

**Feedback loop**:

- **Playground**: `tests/Feature/Mail/SurfaceMailGuardTest.php`.
- **Experiment**: For each case, assert the exception and that every array transport's `messages()` count is 0:
  - (a) `Mail::raw('x', fn ($m) => $m->to('a@example.com'))`;
  - (b) an on-demand notification whose `toMail()` returns a `MailMessage`;
  - (c) a stock framework notification, `new Illuminate\Auth\Notifications\ResetPassword('t')` sent to a user, which proves unmapped framework mail is blocked;
  - (d) `Mail::to('a@example.com')->send(new ExampleAdminMail)`, which runs on `array_default`;
  - (e) `Mail::mailer('array_marketing')->send(new ExampleAdminMail)`;
  - (f) a plain non-surface `Mailable` subclass fixture.

  Positive cases: the `ExampleSurfaceNotification` fixture for admin and for marketing each lands exactly one message on its own transport. Forced production: `app()->detectEnvironment(fn (): string => 'production')` then case (d), which still throws with an empty transport.
- **Check command**: `vendor/bin/pest tests/Feature/Mail/SurfaceMailGuardTest.php`

### Arch rules and suite registration

**Pattern to follow**: Pest arch expectations (https://pestphp.com/docs/arch-testing). This is the repo's first arch test.

```php
use App\Actions\Admin\InviteAdministrator;
use App\Mail\SurfaceMailable;
use Illuminate\Contracts\Queue\ShouldQueue;

arch('app code never sends mail outside surface notifications')
    ->expect([
        'Illuminate\Support\Facades\Mail',
        'Illuminate\Contracts\Mail\Mailer',
        'Illuminate\Contracts\Mail\Factory',
        'Illuminate\Mail\Mailer',
        'Illuminate\Mail\PendingMail',
        'Illuminate\Notifications\Messages\MailMessage',
        'mail',
    ])
    ->not->toBeUsed();

arch('only the invite action touches the mail manager')
    ->expect('Illuminate\Mail\MailManager')
    ->toOnlyBeUsedIn(InviteAdministrator::class);

arch('every mail class declares a surface')
    ->expect('App\Mail')
    ->classes()
    ->toExtend(SurfaceMailable::class)
    ->ignoring(SurfaceMailable::class);

arch('mailables are never queued directly')
    ->expect('App\Mail')
    ->not->toImplement(ShouldQueue::class);

test('queued notifications wait for the database commit', function (): void {
    // Walk app/Notifications with Symfony Finder, map paths to App\ class names (PSR-4),
    // and assert every ShouldQueue implementer also implements ShouldQueueAfterCommit.
    // Name each offending class in the failure message.
});
```

**Key decisions**:

- `tests/Arch` isn't bound to Laravel's `TestCase` (`tests/Pest.php` binds only `Feature`). The after-commit test must use filesystem paths such as `__DIR__.'/../../app/Notifications'`, not `app_path()`.
- Check the exact `not->toBeUsed()` / `toOnlyBeUsedIn()` semantics against the installed pest-plugin-arch. If `not->toBeUsed()` over-reaches into `vendor/`, scope it with `->not->toBeUsedIn('App')`.

**Implementation steps**:

1. Add the Arch testsuite to `phpunit.xml`.
2. Write `tests/Arch/MailTest.php`.
3. Make each rule fail once on purpose, then revert the temporary violation: add `use Illuminate\Support\Facades\Mail;` to any `App` class and watch the rule fail. This proves the rules are live.

**Feedback loop**:

- **Playground**: `tests/Arch/MailTest.php`.
- **Experiment**: Break the rules temporarily, one at a time:
  - (1) a `use Illuminate\Support\Facades\Mail;` in `app/Providers/AppServiceProvider.php`;
  - (2) a `class Stray extends Mailable` in `app/Mail`;
  - (3) `implements ShouldQueue` without `AfterCommit` on `PasswordReset`.

  Each makes the suite fail; revert each change.
- **Check command**: `php artisan test --compact --testsuite=Arch`

### Mail rule and doc reconciliation

**Overview**: This is records only; no feedback loop is needed.

**Implementation steps**:

1. Call Boost `record-rule` with:
   - glob `{app/Mail/**,app/Notifications/**,app/Settings/*MailSettings.php,app/Listeners/RejectUnscopedMail.php,config/mail.php}`;
   - title "Surface-scoped mail";
   - a note stating: every email is a Notification whose `toMail()` returns a class extending the surface's Mailable (`AdminMailable`/`MarketingMailable`); never use Mail::, MailMessage or `mail()`; never set `from` in `envelope()`, because the sender comes from `{Surface}MailSettings`; queued notifications implement `ShouldQueueAfterCommit` and use `#[Queue]`/`#[Connection]` for placement; a new surface needs a mailer, `BIRDCAR_{SURFACE}_MAIL_MAILER`, sender domains, a settings class and a base Mailable; `RejectUnscopedMail` throws on anything else.

   If `record-rule` is unavailable in the session, write `.ai/rules/mail.md` with `paths:` frontmatter and run `php artisan boost:update`, then confirm `index.md` gained the row.
2. Rewrite the two doc blocks listed under Modified Files.

## Testing Requirements

### Feature and Arch Tests

| Test File | Coverage |
| --- | --- |
| `tests/Feature/Mail/SurfaceMailGuardTest.php` | Six rejected paths, two accepted surface paths, forced-production rejection, empty transports on rejection. |
| `tests/Arch/MailTest.php` | Direct-mail ban, MailManager allowlist, extends rule, no queued Mailables, queued notifications after commit. |

**Key test cases**:

- A stock `ResetPassword` notification is rejected, which proves unmapped framework mail is blocked.
- `Mail::mailer(<other surface>)` is rejected even when the class is a surface Mailable.
- In the forced `production` environment the guard behaves identically.

### Manual Testing

- [ ] `php artisan event:list --event='Illuminate\Mail\Events\MessageSending'` lists `App\Listeners\RejectUnscopedMail`.
- [ ] Locally, `php artisan tinker --execute 'Mail::raw("x", fn ($m) => $m->to("a@example.com"));'` throws the guard's `LogicException`.

## Error Handling

| Error Scenario | Handling Strategy |
| --- | --- |
| Undeclared or misrouted send | `LogicException` thrown from `MessageSending`. A synchronous caller sees it (the invite wraps it as `AdminInvitationDeliveryException`); a queued job fails and is visible in failed jobs. |
| `surfaceMailer()` misconfigured | The Phase 1 `LogicException` propagates through the guard unchanged. |

## Failure Modes

| Component | Failure Mode | Trigger | Impact | Mitigation |
| --- | --- | --- | --- | --- |
| Guard | Listener not discovered | Event discovery disabled or cached stale | Guard silently absent | `event:list` manual check; guard tests fail if the listener isn't registered, because case (d) would send |
| Guard | Blocks a legitimate package mail | A package sends its own notification (e.g. a future health or backup alert) | That alert throws | Intended: wrap it in a surface notification. The rule note says so |
| Guard | Identical mailer names hide misroutes | Both surfaces pinned to `array` in phpunit.xml | Misroute tests pass vacuously | Guard tests use distinct `array_admin`/`array_marketing`/`array_default` mailers |
| Arch rules | Rules never run | Suite not registered | Guards absent from CI | `phpunit.xml` Arch suite + `failOnEmptyTestSuite`; criterion checks `--testsuite=Arch` |
| Arch rules | Over-broad ban | `not->toBeUsed()` scanning vendor | False failures | Scope to `App` if needed (see Key decisions) |

## Validation Commands

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse --no-progress --memory-limit=1G
vendor/bin/pest tests/Feature/Mail/SurfaceMailGuardTest.php
grep -q 'tests/Arch' phpunit.xml && php artisan test --compact --testsuite=Arch
php artisan test --compact
```

## Rollout Considerations

- **Feature flag**: none. The guard is active everywhere on deploy. Before this phase, the only production mail path is the invite, which Phase 1 moved onto the admin surface.
- **Rollback plan**: Revert the phase commit. The guard is one listener file.

## Open Items

None.

---

_This spec is ready for implementation. Follow the patterns and validate at each step._
