# Surface-Scoped Mail Contract

**Created**: 2026-09-25
**Readiness**: All 5 gates ready
**Status**: Approved
**Approval**: Express — single consolidated confirmation, no per-artifact review
**Supersedes**: None

## Problem Statement

Birdcar sends mail from separate surfaces: admin.birdcar.dev (transactional Admin mail), birdcar.dev (marketing), and eventually tenant customer apps that will send and receive mail. Resend issues one API key per surface. The admin key can send from every configured domain; the marketing key only from birdcar.dev. Today the app has one stock `resend` mailer reading services.resend.key and a single global From address. The Resend SDK exists only in the uncommitted working tree. Production cannot deliver the Admin invitation that bootstraps the first administrator.

The obvious fix is MAIL_MAILER=resend_admin plus remembering to call Mail::mailer('resend_marketing') for marketing. That is configuration an owner or coding agent has to remember, and it fails silently. Laravel replaces a Mailable's own $mailer with whichever mailer the call went through, so a marketing email could go out with the admin key and sender without any error. Nothing in a mail class says which surface it belongs to, and no test would notice a misroute.

## Goals

1. `php artisan admin:invite` on Cloud production exits 0, and the invitation arrives from noreply@admin.birdcar.dev, sent with the admin Resend key.
2. Every outgoing message is a surface Mailable sent on its surface's mailer. Undeclared sends (Mail::raw, MailMessage notifications, unmapped framework notifications) and misrouted sends throw before reaching the transport, in every environment including production.
3. Static guards run in the registered Arch testsuite, so they run under `php artisan test` and in CI. They fail when app/ uses the Mail facade or mail contracts directly, when a class in App\Mail doesn't extend SurfaceMailable, or when a queued notification doesn't implement ShouldQueueAfterCommit.
4. Each surface's sender identity (From name, From address, reply-to) comes from spatie/laravel-settings and is read per send. A saved change applies to the next email in the same process, and a From address outside the surface's allowed sending domains is rejected.
5. The admin password reset is queued, goes out on the admin surface mailer, and links to the admin origin (config('admin.url')) even when sent from a queue worker with no request host.
6. Every surface-specific env var uses the BIRDCAR_{SURFACE}_* prefix: BIRDCAR_ADMIN_URL, BIRDCAR_MARKETING_URL, BIRDCAR_MARKETING_INDEXABLE, BIRDCAR_{ADMIN,MARKETING}_MAIL_MAILER and BIRDCAR_{ADMIN,MARKETING}_RESEND_API_KEY. No config, .env.example, rule or setup doc still uses the old names.
7. The owner can edit each surface's sender identity on a gated Admin page. Accounts without the mail-configuration permission are denied in mount and in every action, and an off-domain From address is refused with a validation error.
8. The full test suite and phpstan level 7 pass after the change.

## Success Criteria

- [ ] An admin invitation sent on Cloud production is delivered from noreply@admin.birdcar.dev over the admin Resend key. — judgment call: After the production gate, the owner runs `php artisan admin:invite` through `cloud command:run`, sees exit 0, and confirms the email in their inbox and in Resend's log under the admin key.
- [ ] These sends each throw and leave the surface's array transport empty: Mail::raw, a MailMessage notification, an unmapped framework notification, and a surface Mailable on the wrong mailer. One case forces the production environment and asserts the same throw. — check: `vendor/bin/pest tests/Feature/Mail/SurfaceMailGuardTest.php` → exits 0
- [ ] Within one process: send a surface email, save a new from_address, send again, and the second message's From has changed. Saving or loading an off-domain from_address throws for each surface. — check: `vendor/bin/pest tests/Feature/Mail/SurfaceSenderSettingsTest.php` → exits 0
- [ ] The password reset goes onto the queue and is delivered on the admin surface mailer. Its link's host comes from config('admin.url') when sent outside a request. A test pins resets to the admin surface. — check: `vendor/bin/pest tests/Feature/Mail/AdminPasswordResetTest.php` → exits 0
- [ ] InviteAdministrator no longer reads mail.default. One invitation test sends through a recording transport fixture bound as the admin surface mailer and asserts it received one message from noreply@admin.birdcar.dev. The fixture is needed because the invite rejects ArrayTransport as unsafe. — check: `! grep -q "mail.default" app/Actions/Admin/InviteAdministrator.php && grep -q "admin.mail.mailer" tests/Feature/Auth/AdminInvitationTest.php && vendor/bin/pest tests/Feature/Auth/AdminInvitationTest.php` → exits 0
- [ ] phpunit.xml registers the Arch testsuite, and its rules pass: no Mail facade or mail contracts in app/; every class in App\Mail extends SurfaceMailable; every queued notification implements ShouldQueueAfterCommit. — check: `grep -q 'tests/Arch' phpunit.xml && php artisan test --compact --testsuite=Arch` → exits 0 (failOnEmptyTestSuite makes an unregistered or empty suite fail)
- [ ] Config reads only BIRDCAR_-prefixed surface env vars and no longer reads RESEND_ ones. .env.example, .ai/rules and the setup docs drop the old names. Config reads all seven BIRDCAR_ names. — check: `! grep -rqE "env\('(ADMIN|MARKETING|RESEND)_" config && ! grep -rqE "(^|[^_A-Z])(ADMIN_URL|MARKETING_URL|MARKETING_INDEXABLE)" .env.example .ai/rules docs/production-setup.md docs/development-setup.md && for v in ADMIN_URL MARKETING_URL MARKETING_INDEXABLE ADMIN_MAIL_MAILER MARKETING_MAIL_MAILER ADMIN_RESEND_API_KEY MARKETING_RESEND_API_KEY; do grep -rq "env('BIRDCAR_$v'" config || exit 1; done` → exits 0
- [ ] The Admin mail settings page saves valid senders per surface, rejects off-domain From addresses, redirects guests, forbids accounts without the permission over HTTP and Livewire::test, and denies save after the role is revoked. — check: `vendor/bin/pest tests/Feature/Mail/AdminMailSettingsPageTest.php` → exits 0
- [ ] The full test suite passes after the change. — check: `php artisan test --compact` → exits 0
- [ ] Static analysis passes at level 7 or higher with no baseline. — check: `grep -qE '^ +level: ([7-9]|10|max)$' phpstan.neon && ! grep -q baseline phpstan.neon && vendor/bin/phpstan analyse --no-progress --memory-limit=1G` → exits 0

## Scope Boundaries

### In Scope

- config/mail.php: resend_admin and resend_marketing mailers, each with its own BIRDCAR_{SURFACE}_RESEND_API_KEY; remove the stock `resend` mailer and services.resend. The resend/resend-php SDK is already committed on main (0355f12) — Laravel's resend transport reads a per-mailer `key` before falling back to services.resend.key (MailManager.php:323). Cloud's `composer install --no-dev` installs from the committed lock, so the SDK must be committed.
- config/admin.php and config/marketing.php: mail.mailer from BIRDCAR_{ADMIN,MARKETING}_MAIL_MAILER, plus each surface's allowed sender domains; phpunit.xml pins both surface mailers to `array` — Which mailer a surface uses is per-environment infrastructure: Herd SMTP locally, Resend in production. Nobody chooses it at runtime. Pinning `array` keeps tests independent of a developer's .env.
- Rename env vars: ADMIN_URL → BIRDCAR_ADMIN_URL, MARKETING_URL → BIRDCAR_MARKETING_URL, MARKETING_INDEXABLE → BIRDCAR_MARKETING_INDEXABLE. Also update .env.example, the keys in local .env, docs/production-setup.md, docs/development-setup.md and .ai/rules/general.md — One naming scheme. `cloud environment:list` shows production sets only APP_KEY, SESSION_DRIVER, POSTHOG_* and NIGHTWATCH_REQUEST_SAMPLE_RATE, none of the old names. The gate re-checks this before deploying.
- AdminMailSettings and MarketingMailSettings (spatie/laravel-settings) with from_name, from_address and reply_to, validated on load. Settings migrations seed admin: 'Birdcar' / noreply@admin.birdcar.dev / null reply_to; marketing: 'Birdcar' / hello@birdcar.dev / null reply_to — Sender identity is owner-editable and not secret. The existing PublishingAgentSettings sets the pattern: resolved per request or job, and stored payloads re-checked when read. The seeds are the production sender until the Full-tier page exists.
- Abstract App\Mail\SurfaceMailable, with App\Mail\Admin\AdminMailable and App\Mail\Marketing\MarketingMailable, which set the surface mailer and the settings-backed sender on every send — Inheriting from a surface base makes the surface visible in the class declaration. Mailable::send() runs prepareMailableForDelivery(), which calls build(), before it resolves the mailer, so a base can set both at a native seam.
- AdminInvitation returns an admin InvitationMail from toMail(). The Mailable exposes the setup URL as a public property, and AdminInvitationTest is rewritten from MailMessage fields to Mailable assertions. InviteAdministrator validates the admin surface's mailer (not mail.default) and its sender settings before provisioning — The invite is the immediate production need. The existing validation of unsafe transports and failover chains moves over unchanged. A missing sender now fails before an account is created, not after.
- App\Notifications\Admin\PasswordReset (ShouldQueueAfterCommit), sent through User::sendPasswordResetNotification(). Its toMail() returns an admin Mailable whose link is built from config('admin.url'), like AdminInvitation does — The owner chose a queued reset, which keeps delivery out of the request. On a worker, url() resolves against APP_URL (the marketing host), so the link must come from the admin origin.
- A MessageSending listener rejects any message whose __laravel_mailable is not a SurfaceMailable, or whose resolved mailer differs from that surface's — It blocks direct Mail:: sends and MailMessage notifications at runtime, in every environment, using a native framework event.
- Arch tests in tests/Arch, registered as an Arch testsuite in phpunit.xml, excluding SurfaceMailable itself from the extends rule; feature tests for the guard, sender settings, password reset and invitation — Tests catch owner or agent mistakes as regressions, and only a registered suite runs under `php artisan test` and CI.
- A mail rule recorded with the Boost record-rule tool; reconcile docs/production-setup.md and docs/development-setup.md mail guidance (MAIL_MAILER/MAIL_FROM_* for invites) with the surface model — .ai/rules/index.md is regenerated by Boost, so rules must go through record-rule. Recorded learning: reconcile the authoritative records a change touches.
- Production gate. Check Cloud env for old names. Verify admin.birdcar.dev in Resend (needs DNS access). Set BIRDCAR_ADMIN_MAIL_MAILER=resend_admin and the admin key. Deploy (the Cloud deploy command runs migrate --force, which applies the settings migrations). Run admin:invite — This is the immediate goal. It needs the owner's Cloud, Resend and DNS access, so it is a human checkpoint. The marketing mailer and key can be set at the same time, but the invite doesn't need them.
- Admin mail settings page (Livewire single-file component) at admin.mail.settings. It is gated by a new mail-configuration permission in a Mail authorization domain, granted to a role in the Admin bootstrap bundle and authorized in mount and in every action — Without it, settings can only be edited through tinker. It follows the Publishing settings page pattern and .ai/rules/authorization.md: a domain capability, not admin.view.

### Out of Scope

- The hard-coded 'admin.birdcar.dev' in routes/ai.php and the '{org:slug}.birdcar.dev' customer hosts — These are routing concerns unrelated to mail, and no env var backs them today.
- Enabling email verification (MustVerifyEmail, Fortify emailVerification) — It's switched off today and nobody asked for it. When enabled, it must use a surface notification, and the guard will enforce that.
- Moving the Flux Composer credentials out of the Cloud build command — A security issue found during research and flagged to the owner; it's not mail work.

### Future Considerations

- Customer surface: tenant-specific sender, outbound CustomerMailable, and inbound mail through Resend inbound webhooks received by spatie/laravel-webhook-client (installed, not yet configured).
- Actual marketing emails as MarketingMailable notifications, and Resend Broadcasts for bulk sends.
- A password reset that knows its surface once customer auth exists; the admin-surface pin test will fail and force the decision.
- Mapping Mailable tag() and metadata() headers to Resend tags; ResendTransport passes them as plain headers today.

## Decisions Considered and Rejected

- **Use two resend-transport mailers (resend_admin, resend_marketing), each with a per-mailer key; install resend/resend-php** — rejected: Resend SMTP mailers (smtp.resend.com) with no new dependency. Laravel's native resend transport is the documented driver, and it gets Resend message IDs back. The owner approved the dependency.
- **Surfaces select their mailer through BIRDCAR_{SURFACE}_MAIL_MAILER, and mail classes declare their surface** — rejected: Make the admin mailer the global default (MAIL_MAILER=resend_admin) and call Mail::mailer('resend_marketing') for marketing. That is non-obvious 'remember to do this' configuration. Laravel's overwrite of $mailer also means a mistake sends silently on the wrong key.
- **Send all surface mail as a Notification whose toMail() returns a surface Mailable** — rejected: Direct Mail::mailer(...)->send() calls guarded at runtime, or overriding Mailable::send()/mailer() in the base class. MailChannel sends a returned Mailable through the factory, which keeps its declared mailer (MailChannel.php:63). Direct sends require remembering the mailer, and overriding send() replaces framework internals rather than extending a seam.
- **Declare the surface by extending a surface base Mailable; set queue placement with the native #[Connection]/#[Queue] class attributes** — rejected: A custom #[Mailer] or #[Surface] attribute. Laravel has no native mailer attribute; inventing one breaks the rule of extending only at Laravel's seams. Notifications and Mailables already read the queue attributes (NotificationSender.php:243-261).
- **Rename every surface env var to BIRDCAR_{SURFACE}_* in this project** — rejected: Prefix only the new mail variables. Two naming schemes would coexist. Production sets none of the old names, so the rename is cheap.
- **'Transactional' means both: queued mail waits for the commit (ShouldQueueAfterCommit), and marketing mail stays on its own mailer, key and sender, apart from transactional streams** — The owner answered 'Both'. The invite stays synchronous because admin:invite reports whether delivery was accepted, and it already sends after the provisioning transaction commits.
- **Settings hold per-surface sender identity (from_name, from_address, reply_to); mailer choice and API keys stay in env; the Admin editing page is Full tier** — rejected: An Admin settings page in the MVP, or env/config-only senders. Settings wherever possible, but mailer and queue selection is infrastructure nobody picks at runtime. The page isn't needed to invite an admin.
- **A MessageSending listener rejects undeclared or misrouted mail in every environment** — rejected: Reject only outside production, or rely on static checks alone. The owner wants direct sends blocked, not merely logged. A production misroute would otherwise go out with the wrong key and sender.
- **Queue the admin password reset through our own notification via User::sendPasswordResetNotification()** — rejected: ResetPassword::toMailUsing() returning an admin Mailable, synchronously. The owner chose a queued reset so delivery stays out of the request (best-practice rule: queue slow notifications). toMailUsing() cannot queue. Fortify sends the reset outside a transaction, so ShouldQueueAfterCommit comes from the project-wide rule for queued mail, not from this path's own needs.
- **Password resets use the admin surface until customer auth exists, pinned by a test** — rejected: Pick the surface from the request host now. Fortify only serves the admin host, and host detection would be speculative with no customer auth routes yet.
- **Register tests/Arch as an Arch testsuite in phpunit.xml and check it with --testsuite=Arch (critic blocker fix)** — rejected: Running `vendor/bin/pest tests/Arch` by path, as first drafted. phpunit.xml registers only Unit and Feature, so `php artisan test` and CI would never run the arch guards, while the path-based check passed anyway.

## Execution Plan

_Added during Phase 5 handoff. Pick up this contract cold and know exactly how to execute._

### Dependency Graph

```
Surface mail foundation
  └── Guard rails  (blocked by Surface mail foundation)
        └── Admin mail settings page  (blocked by Guard rails)
              └── Production rollout  (blocked by Admin mail settings page)
```

### Execution Steps

**Run the project** (recommended) — autopilot reads this contract, plans dependency waves, runs independent phases in parallel, and gates on failure:

```bash
/ideation:autopilot docs/ideation/2026-09-25-surface-scoped-mail/contract.md
```

**Or run it unattended** — a `/goal` is a durability wrapper around the same autopilot run: Claude re-checks the condition before it is allowed to stop, so failures get repaired and re-run. Generated by `contract-gen --print-goal`; this is the only copy of that string:

```
/goal Drive the Surface-Scoped Mail contract (2026-09-25-surface-scoped-mail) to completion with /ideation:autopilot.

1. Run `/ideation:autopilot docs/ideation/2026-09-25-surface-scoped-mail/contract.md`. All commits belong on branch ideation/2026-09-25-surface-scoped-mail — switch to it before any run.
2. It dispatches a BACKGROUND workflow. Wait for the completion notification — never start a second autopilot run while one is in flight.
3. Then run the ideation plugin's `scripts/verify.mjs` against `docs/ideation/2026-09-25-surface-scoped-mail/contract-data.json` and leave its VERIFY line in the conversation. Resolve the plugin's install directory first — `${CLAUDE_PLUGIN_ROOT}/scripts/verify.mjs` is a placeholder, not a shell variable, and bash will not expand it. That line is the only evidence this goal is judged on.
4. If anything failed, fix the spec or the implementation and go back to step 1. Autopilot skips phases that already have commits.

Done when the most recent VERIFY line reads fail=0 and commits=3/3 — or when two consecutive VERIFY lines are identical and still failing, in which case name the failing checks and stop, because a contract whose checks have rotted must not trap the run.
```

**Or run phases manually** in dependency order:

**Strategy**: Sequential

1. **Phase 1** — Surface mail foundation _(blocking)_

   ```bash
   /ideation:execute-spec docs/ideation/2026-09-25-surface-scoped-mail/spec-phase-1.md
   ```

2. **Phase 2** — Guard rails _(blocking)_

   ```bash
   /ideation:execute-spec docs/ideation/2026-09-25-surface-scoped-mail/spec-phase-2.md
   ```

3. **Phase 3** — Admin mail settings page _(blocking)_

   ```bash
   /ideation:execute-spec docs/ideation/2026-09-25-surface-scoped-mail/spec-phase-3.md
   ```

4. **Phase 4** — Production rollout _(blocking)_

   ```bash
   # Review: Production rollout
   ```

---

_This contract was generated from brain dump input. Review and approve before proceeding to specification._
