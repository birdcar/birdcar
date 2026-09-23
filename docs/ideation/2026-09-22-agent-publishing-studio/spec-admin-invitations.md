# Implementation Spec: Root Admin invitations

**Contract**: ./contract.md

**Phase**: Root Admin invitations

**Prerequisite**: Release delivery and public cutover (execution order keeps the current publishing work separate).

**Risk / effort**: High / M

## Technical Approach

Provide `php artisan admin:invite person@example.com --name="Person Name" --no-interaction` for an operator running Laravel Cloud commands. There is no password option or prompt. Use the existing User identity, Laravel password broker, Fortify password-reset endpoint and global web-guard domain roles. The invitee sets their password in the browser through an expiring, single-use emailed link. No new dependency, invitation table, custom token protocol, general role-management UI or deployment is needed.

The owner clarified that this provisions the first/root Admin: grant `admin.access` and `publishing.author` by default, covering all current Admin capabilities. Keep an explicit bootstrap role bundle in `config/admin.php`; future Admin modules extend that bundle deliberately. Never create a User type, enable Spatie Teams, assign direct permissions or install a blanket `Gate::before` bypass. Tenant authorization still requires the matching membership.

Reuse an existing account by normalized email without replacing its password, 2FA, profile or unrelated roles. New users receive a cryptographically random, never-disclosed placeholder password through the existing hashed cast. Only the recipient's successful password-reset submission changes credentials. Repeat command invocations reuse the identity and role assignments and respect the broker's throttle/expiry; they are not permission to send actual invitations during this build.

Read matching `.ai/rules` and activate Laravel, Fortify, permission, testing and Flux skills before implementation. Use noninteractive Artisan generators. Verified baseline: Laravel 13.30.1, Fortify 1.39.0, Spatie Permission 8.3; `users.password` is nonnullable with a hashed cast, the password-reset token table exists, and the broker currently expires tokens after 60 minutes and throttles for 60 seconds. Reconfirm installed APIs/config rather than hard-coding those durations.

Preserve controller-owned historical `run-*.json` and generated HTML, even when earlier findings are fixed. No actual account creation, privileged grants, invitation delivery, provider setup or deployment is authorized during implementation. Factories and fake mail are authorized in the isolated test database.

## Decisions Considered and Rejected

- **Full current root-Admin role bundle by default** — the owner rejected admission-only invites with publishing opt-in.
- **Reuse existing accounts** — the owner rejected requiring an existing-user flag. Preserve their password/2FA and existing role assignments.
- **Email password setup** — rejected plaintext password arguments, prompts, generated-password output or printing setup links in Cloud command logs.
- **Existing broker and Fortify** — rejected a parallel invitation/token/authentication subsystem.
- **Explicit domain roles** — rejected a universal permission bypass, direct grants, a new User identity type or tenant membership bypass.
- **Operator-only bootstrap** — rejected a public role-grant endpoint and grants during application boot/migrations.
- **Continue the watched build** — the owner approved fixing publishing findings, finishing release delivery, then adding invitations before owner acceptance. No further artifact/message approval is required; failures and operational authorization gates remain.

## Feedback Strategy

**Inner loop**: `php artisan test --compact tests/Feature/Auth/AdminInvitationTest.php`

**Playground**: factory users, synced authorization catalogs, fake notifications/mail and isolated broker token storage. Execute the real console command and HTTP reset/login flows through tests; no live mail and no production records.

**Why**: The important outcomes are privilege assignment, secret handling, delivery failure semantics and single-use password setup—not a successful console exit alone.

## File Changes

### New Files

| File Path | Purpose |
| --- | --- |
| `app/Console/Commands/InviteAdmin.php` | Noninteractive root Admin invitation command with secret-safe status output |
| `app/Actions/Admin/InviteAdministrator.php` | Identity reuse, role preflight/assignment and broker/mail coordination |
| `app/Notifications/AdminInvitation.php` | Synchronous invitation email with Admin-origin setup URL and broker expiry |
| `app/Http/Responses/AdminPasswordResetResponse.php` | Successful reset response preserving the configured Admin origin and existing JSON behavior |
| `resources/views/auth/reset-password.blade.php` | Minimal Flux password-setup/reset form using existing Fortify endpoint |
| `tests/Feature/Auth/AdminInvitationTest.php` | Command, credential safety, mail failure and real broker/Fortify boundary coverage |

### Modified Files

| File Path | Changes |
| --- | --- |
| `config/admin.php` | Explicit root bootstrap domain-role bundle; preserve URL/host behavior |
| `app/Providers/FortifyServiceProvider.php` | Register reset view and Admin reset response without changing login/2FA behavior |
| `resources/views/auth/login.blade.php` | Display the password-reset success status if not already shown |
| `tests/Feature/Auth/AdminFortifyFlowTest.php` | Extend existing origin/2FA coverage only where shared behavior changes |
| `docs/ideation/2026-09-22-agent-publishing-studio/spec-phase-6.md` | Replace manual privileged bootstrap instructions with the operator-controlled invitation procedure |

### Deleted Files

None. No schema changes, authorization definition changes or public registration/role-management features are required.

## Implementation Details

### 1. Operator command and root role assignment

**Patterns**: `app/Console/Commands/SyncAuthorization.php`, `app/Authorization/Admin/{Role,Catalog}.php`, `app/Authorization/Publishing/{Role,Catalog}.php`, `app/Models/User.php`.

Expose required `{email}` and optional `--name=` only, plus standard Artisan options. Validate and normalize email consistently; case-insensitive lookup must reuse one unambiguous identity and fail safely if legacy records are ambiguous. A supplied name is used only for a new account; when omitted, use the email as its initial display name rather than inventing a person's name. Never silently rename an existing user.

Before any account/role mutation, verify the configured Admin origin, effective mail transport and every required web-guard role. If roles are absent, fail with an instruction to run `authorization:sync`; do not define permissions/roles from this command or silently sync every catalog. Assign the configured domain roles with `assignRole`, not `syncRoles`, retaining unrelated assignments. The default bundle is exactly Admin Access plus Publishing Author; do not discover arbitrary organization/customer roles as root privileges.

Create or reuse the User and assign roles in a short transaction. Handle unique-email races without duplicate identities or selecting the wrong user. Commit database work before contacting the mail transport; do not hold locks during delivery. A delivery failure may leave the intended account/roles provisioned: report that partial result honestly and make a later invocation safe. Do not roll back credentials behind an email that may already have been accepted by the transport.

**Feedback loop**: Call the command for a new user, an existing 2FA-enabled user with unrelated roles, then repeat it. Assert exactly one identity, both required role capabilities, no direct permissions, unchanged existing password/2FA/name and unchanged unrelated roles/memberships. Test invalid input and missing roles produce no account/role changes. Check: the inner-loop command.

### 2. Broker invitation and secret-safe mail

**Verified framework guidance**: Laravel 13 password documentation describes broker-managed user lookup, token expiry/throttle and reset statuses; Fortify 1.39 supports `resetPasswordView` and the existing POST reset flow. `PasswordBroker::sendResetLink` accepts an optional callback for a custom notification. Confirm the installed status constant names (Laravel 13 documents `Password::ResetLinkSent`) and callback return semantics in source.

Use `Password::broker(config('fortify.passwords'))->sendResetLink` for the persisted user's actual email, sending `AdminInvitation` through its callback. Reuse the broker's hashed token storage, throttle, expiry and single-use consumption. Do not hand-roll tokens or queue plaintext setup credentials in an application job payload. Send this notification synchronously; report transport acceptance, not guaranteed recipient delivery and not 'queued' when nothing was queued.

Construct the link from the trusted `config('admin.url')` origin plus the relative named Fortify reset route and encoded email. Never derive a CLI link from the marketing `APP_URL`, request Host, an arbitrary CLI URL or an intended redirect. Preserve configured scheme/port. Reject invalid origins or origins with credentials/query/fragment; require HTTPS outside local/testing. The existing local Herd Admin origin is intentionally HTTP, while production must use its configured HTTPS origin.

The log mailer logs the entire email and therefore leaks the token; the array mailer does not deliver. Before provisioning, reject effective log/array transports and failover/round-robin chains containing them. Validate actual effective configuration including URL overrides; reject missing, cyclic or unsupported configurations rather than claiming delivery. Reuse the installed MailManager/Symfony APIs instead of guessing transport internals. Tests may select a delivery-capable configuration and fake notifications; there is no `--force-insecure-mailer` bypass. Do not change real mail credentials or the live mailer.

Neither command stdout/stderr nor application diagnostics from this operation may contain passwords, raw tokens or setup URLs. Mark raw-token parameters sensitive where applicable; never dump notifications/models or print raw mailer exceptions/provider responses. Catch delivery failures and emit a sanitized category/status. Do not report success for throttling, failed delivery or no-op array transports; report that provisioning may already have succeeded. Infrastructure access-log redaction is separate: do not promise that a normal emailed reset URL can never appear in provider/browser/server telemetry.

The email explains root Admin access, links to password setup, states the actual broker lifetime and explains that an existing password remains usable until changed. Re-inviting never removes 2FA or unrelated authorization. A throttled repeat does not send another email; after the throttle, a resend uses normal broker invalidation of older tokens.

**Feedback loop**: Fake notification delivery, capture its action URL only inside the test, and verify origin/port, email encoding and expiry text. Test log, array, nested unsafe failover, invalid origin, throttling, a throwing mail transport and a later successful retry. Assert secret values never enter command output or captured diagnostics, and failures do not duplicate identities. Check: the inner-loop command.

### 3. Password setup and authentication

**Patterns**: existing `resources/views/auth/login.blade.php`, `two-factor-challenge.blade.php`, `AdminLoginResponse`, `ResetUserPassword` and `AdminFortifyFlowTest`.

Register `Fortify::resetPasswordView` and render a small Flux form in the established Admin guest style, using Inter and existing assets. Submit to the existing named `password.update` endpoint with CSRF, token, email, password and confirmation; use new-password autocomplete and accessible validation errors. Do not introduce another password-reset controller, token-validation protocol or authentication guard. Do not expose this screen through Folio or load marketing analytics.

Keep setup responses private/non-cacheable and avoid referrer leakage from the credential-bearing page. Do not prefill or flash password fields. Show actionable invalid/expired-link errors and tell the recipient to ask the operator to resend; a broader forgot-password/account-management UI is not required by this phase.

Bind an Admin password-reset success response that preserves `config('admin.url')` for the login redirect and retains Fortify's JSON contract. Surface the success status on login. Reuse existing password rules/reset action, token consumption and session protections; do not mark unrelated identity fields verified or clear 2FA/passkeys as a side effect. Existing confirmed 2FA remains required at subsequent login.

**Feedback loop**: Use the real broker token delivered to a fake notification to GET the setup form and POST a valid password. Assert token consumption, configured login redirect and subsequent login. Repeat with wrong email/token, expired token, replay and invalid passwords; assert unchanged credentials on failure. Exercise existing-user 2FA after password setup. Fake any breach-password check and block unintended external requests. Also inspect the form in a fresh guest browser context on the already-running Herd site at desktop/mobile widths, with keyboard navigation and an obviously fake token/reserved example email; this needs no real account or live email. Do not log out an existing human session to perform the check. Check: `php artisan test --compact tests/Feature/Auth/AdminInvitationTest.php tests/Feature/Auth/AdminFortifyFlowTest.php`.

## Testing Requirements

- Prove new and existing account paths, safe repeats and case-normalized identity reuse.
- Both Admin admission and publishing capabilities are granted by roles; no direct permissions or organization membership bypass.
- Missing roles/unsafe transport/invalid origin fail before provisioning; delivery failure after provisioning is explicit and retry-safe.
- Never accept or output a password, raw reset token or setup URL in the command; sanitized error paths are tested too.
- Broker throttle, expiry, old-token invalidation after resend and one-use consumption are real integration tests, not mocked token assertions alone.
- Configured Admin origin wins over marketing origin, including local/nondefault ports; invalid credentials in URL are refused.
- Existing passwords/2FA remain unchanged until recipient action; 2FA remains enabled afterward.
- Production tests use fake mail only. No actual user, privileged role grant, invitation or deployment during development.

## Failure Modes

| Component | Trigger | Impact | Mitigation |
| --- | --- | --- | --- |
| Identity | Existing/mixed-case email or repeated command | Duplicate account or lost credentials | Reuse one normalized identity; preserve existing fields; unique-race handling |
| Authorization | Unsynced role catalog | Partial or incorrect privilege grant | Preflight every web role; explicit authorization:sync prerequisite |
| Mail | Log fallback, array transport or SMTP failure | Leaked credential or false delivery claim | Effective-transport preflight; sanitized truthful failure; safe retry |
| Link | Marketing origin or wrong port | Recipient cannot set password | Trusted Admin-origin construction and route/port tests |
| Reset | Expired, replayed or mismatched token | Unauthorized credential change | Existing broker/Fortify validation and integration coverage |
| Scope | Root confused with tenant superuser | Cross-tenant privilege escalation | Explicit global domain-role bundle; no universal Gate bypass |

## Validation Commands

```bash
php artisan test --compact tests/Feature/Auth/AdminInvitationTest.php tests/Feature/Auth/AdminFortifyFlowTest.php tests/Feature/Authorization tests/Feature/Publishing/PublishingAuthorizationTest.php
vendor/bin/pint --dirty --format agent
composer types:check
bun run build
```

Rerun affected tests after fixes. The owner approved tested/reviewed conventional phase commits without extra message prompts. Include `docs/ideation/2026-09-22-agent-publishing-studio/spec-admin-invitations.md` verbatim in this phase's commit body, with no Co-Authored-By trailer. Preserve unrelated working changes and historical run reports.

## Rollout and Owner Gate

This phase creates code and tests only. Before a real invitation, the operator confirms the target email, configures an actual delivery-capable mailer and HTTPS production ADMIN_URL, applies the approved application schema and runs `php artisan authorization:sync --no-interaction`. Then the operator deliberately runs `php artisan admin:invite <confirmed-email> --name="<display-name>" --no-interaction` from Laravel Cloud or the approved local environment. No password appears in the command. Never substitute a guessed email or send a live test invitation from the build agent. Password setup and sign-in become prerequisites of the existing owner acceptance gate, not automatic acceptance of the publishing pilot.
