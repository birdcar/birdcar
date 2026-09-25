# Production setup

Operator runbook for the Admin and Agent Publishing Studio. This describes the current Laravel AI SDK implementation and database-only public writing. Historical specs in `docs/ideation/2026-09-22-agent-publishing-studio/` preserve earlier decisions; their custom AI client, file-reader toggle, and file rollback instructions are superseded.

These commands change the environment in which they run. Run production commands only after reviewing the release, taking a recoverable database backup, and confirming the target environment. Do not run `composer setup`, `migrate:fresh`, or development seeders in production. Local imported records do not accompany a Git deployment.

## 1. Review and build the release

Before merging/deploying, run in the development or CI environment with its isolated test database:

```bash
vendor/bin/pest --compact --fail-on-skipped --fail-on-incomplete
bun test resources/js/admin/publishing/document-helpers.test.js
composer lint:check
composer types:check
bun run build
```

Do not point the tests at the production database. Complete the manual checks and resolve the relevant follow-ups in section 8 before enabling live editorial work.

Build requirements:

- Use PHP 8.5, the version used for this application, and the extensions required by `composer.lock`. Run `composer check-platform-reqs --no-dev` on the production runtime.
- Install locked PHP dependencies with `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction`. Supply Flux Pro Composer authentication through the deployment platform's secret mechanism, not a committed `auth.json` or a command containing credentials.
- Install frontend dependencies with `bun install --frozen-lockfile`, then `bun run build`. Include the resulting `public/build` assets and existing public fonts in the deployed artifact. Do not run Vite's development server in production.
- Include `resources/writing/**` for the explicit archive import and parity checks. Public writing does not read these files at request time.
- The database must persist outside disposable deployment filesystems. PostgreSQL is the application's existing local engine; validate your production engine and backups before rollout.

For Laravel Cloud, configure the build/deploy steps, environment secrets, application domains, worker, and scheduler in the target environment. Run the one-time bootstrap commands below through its command console or your approved release runner. This guide does not create Cloud resources or deploy the application automatically.

## 2. Configure production environment

Use `.env.example` as a variable inventory, not a production configuration to copy unchanged. Set secrets through the deployment platform. Never print an entire production configuration or share its cached configuration file.

Core settings:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://birdcar.dev
MARKETING_URL=https://birdcar.dev
MARKETING_INDEXABLE=true
ADMIN_URL=https://admin.birdcar.dev

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=null
CACHE_STORE=database
QUEUE_CONNECTION=cloud
```

`QUEUE_CONNECTION=cloud` uses Laravel Cloud Managed Queues; Cloud supplies the queue configuration itself, so there are no queue driver credentials or retry windows to set (see section 6).

- Configure both public and Admin domains with HTTPS, pointing at this same Laravel application. Marketing routes and Admin routes use different hosts. Change the example origins if the actual production domains differ.
- Preserve an existing `APP_KEY`. For a brand-new application, provision one securely before use; never regenerate a live key as a routine deployment step.
- Configure `DB_CONNECTION` and the database connection secrets for the persistent production database. The session, cache, jobs, publishing, and SDK conversation tables come from migrations.
- Keep session cookies host-only unless cross-subdomain sharing is an explicit requirement; do not copy a local cookie domain.
- Configure a real delivery-capable `MAIL_MAILER`, its credentials, and a verified `MAIL_FROM_ADDRESS`/`MAIL_FROM_NAME`. Admin invitations reject log, array, null, and unsafe aggregate transports. Configure this before inviting an account.
- Configure the real PostHog token/host deliberately, or set `POSTHOG_DISABLED=true`. Do not leave the example token in production. Use non-indexable, analytics-disabled settings when testing on a staging domain.
- Keep agent requests paused until the OpenRouter key and its limits, the worker, and the human approval flow are verified. Migrations initialize the publishing agent settings with requests paused. Browsing public writing or importing the archive does not require AI credentials.

There is no `PUBLISHING_PUBLIC_READER` setting anymore. Public pages, RSS, and sitemap always use published database releases, with no fallback to source files.

## 3. Plan first-release traffic ordering

**Do not expose the database-only reader before the archive is imported.** An empty database means an empty archive, even if the Markdown files are present.

Either run the new release's migrations/bootstrap/import against the production database before promoting its public traffic, or use an explicit maintenance window while activating and initializing the release. If the platform's command console only runs commands in the active release, plan for the latter. Verify the platform's actual deployment hooks instead of assuming that commands run before traffic switches.

A local filesystem maintenance marker is not a reliable cross-instance or cross-release traffic gate. Use the platform's approved traffic-control procedure or correctly configured shared maintenance state. Keep background workers and the scheduler stopped during initial bootstrap. For an upgrade, let in-flight paid work finish and stop old workers before replacing their code; do not kill a provider request and assume it was unbilled.

## 4. Apply schema and provision the operator

From the new release, connected to the intended production database:

```bash
php artisan migrate --force --no-interaction
php artisan authorization:sync --no-interaction
php artisan config:cache --no-interaction
php artisan view:cache --no-interaction
```

Migrations include `agent_conversations`, `agent_conversation_messages`, the editorial activity fields used to resume native tool approvals, and the publishing agent settings (first installed paused, with no model overrides). Check `php artisan migrate:status --no-interaction` before starting new workers. Normal authorization sync grants `publishing.configure-agents` through the author role and reports stale definitions such as a leftover `publishing.budget`; it does not assign roles to users. Do not use prune as a routine deployment step.

For a new operator, or an existing account that needs the root Admin role bundle, deliberately invite the confirmed email:

```bash
php artisan admin:invite CONFIRMED_EMAIL --name="DISPLAY_NAME" --no-interaction
```

This is a privileged, email-sending operation, not an automatic deploy hook. Replace the placeholders. The command creates or reuses the account, assigns `admin.access` and `publishing.author`, and prints the user ID for the next step. It preserves an existing password, two-factor data, and unrelated roles. Mail transport acceptance is not proof of inbox delivery. If mail fails, the account and roles may already exist; correct the mail configuration before retrying, respecting broker throttling.

If the account already has the required roles, do not resend an invitation just to discover its ID. Use an approved read-only database lookup, or the following read-only command with the confirmed email:

```bash
php artisan tinker --execute 'dump(App\Models\User::query()->where("email", "CONFIRMED_EMAIL")->value("id"));' --no-interaction
```

A null result is not an instruction to guess an ID. The archive import actor must be the intended author and have `publishing.write`. Imported records belong to that account and appear in its Admin Published list; the local account ID is not necessarily the production account ID.

## 5. Import the original archive

Run the preflight and inspect its complete output:

```bash
php artisan publishing:import-archive --dry-run --no-interaction
```

The current archive contains **10 published essays**, with no excluded entries. Review titles, original dates, URLs, source/parity manifests, and figures. Dry-run parsing does not write records and does not replace the write-time collision checks.

Then replace `PRODUCTION_USER_ID` and explicitly authorize the production write:

```bash
php artisan publishing:import-archive \
  --write \
  --actor=PRODUCTION_USER_ID \
  --confirm-production-write \
  --no-interaction
```

Defaults are `--source=resources/writing` and `--baseline=72f7d8ad8521573cb224022c902447f9ca4c4351`. Keep the baseline unless deliberately importing a different audited archive. Source files remain unchanged.

A first successful import reports `created: 10`. Matching repeat imports report `already_imported`; they do not overwrite CMS edits or reset live pointers. Unrelated slug collisions or changed import identity fail rather than clobber records. The import writes transactionally. Keep the operator report with deployment evidence, treating its source paths and operational details as internal.

The import creates editable revisions and already-published historical release snapshots. It does not retroactively invent editorial approvals, invoke AI, or import the local application's database. Do not put the write command in every deployment hook.

## 6. Run the queues and scheduler

Production uses Laravel Cloud Managed Queues. Cloud runs the workers, so there are no worker flags; each managed queue handles one queue name. Publishing agent jobs (`RunEditorialActivity`) are routed to their own `publishing-agents` queue so long model requests never hold up other jobs:

- **`publishing-agents`**: a Standard managed queue on **Pro** compute (Flex caps a job at 90 seconds, and agent requests can run for several minutes), 256 MiB, autoscaling from 1 to 5 workers. Do not make it the environment's default queue.
- **Default queue**: keep a default managed queue for every other job.

The job declares its own limits, which Managed Queues honour: a 900-second `timeout` (above the 540-second provider request plus research source fetches) and one `try`, because the activity owns its retry policy and a redelivered paid request must not run again silently. Cloud extends a running job's visibility while it works, and a stopping Pro worker has one hour to finish its current job, so a deploy does not cut off an agent request. Revalidate this chain (request timeout, research fetches, job timeout) if any of those bounds change.

Self-hosted alternative: with `QUEUE_CONNECTION=database`, run a supervised worker for both queues and keep the database retry window (`DB_QUEUE_RETRY_AFTER`, default 960 seconds) above the job timeout, or a long job is handed out twice:

```bash
php artisan queue:work database --queue=publishing-agents,default --sleep=3 --tries=1 --timeout=900 --max-time=3600 --no-interaction
```

Horizon is installed but is not used for either setup. If deliberately choosing Redis/Horizon, configure a supervisor for both queues in `config/horizon.php`: the checked-in supervisor's 60-second timeout cannot run agent jobs, and its retry window must exceed the job timeout.

Enable the platform's Laravel scheduler, or configure one cron invocation every minute:

```cron
* * * * * cd /path/to/current-release && php artisan schedule:run --no-interaction
```

Use one scheduling arrangement, not both platform scheduling and a duplicate cron/`schedule:work` process. Route stdout/stderr to your process monitoring. In a multi-instance deployment, run the scheduler on a designated instance with a suitable shared cache for overlap locks.

Check registration without executing jobs:

```bash
php artisan schedule:list --no-interaction
```

The schedule contains:

- `publishing:publish-due` every minute: delivers due, still-authorized and current approved release snapshots. This can publish content; it is not a harmless health-check command.
- `publishing:recover-activities` every five minutes: while agent requests are on, re-enqueues durable pending work, which can cause queued inference, and first returns pre-request configuration pauses (missing key, unsupported override or recommendation) to pending once that configuration is valid. It also pauses running activities older than 30 minutes as uncertain for review. It makes no billing lookups and does not blindly regenerate an ambiguous paid result.

After code/configuration updates, gracefully restart workers through the platform, or use `php artisan queue:restart --no-interaction` with the supervised process. Reload any long-running web runtime as well. All web and worker processes must receive the same configuration and shared data stores. Saving the publishing agent settings page is not a configuration update: each job reads the saved settings, so no config rebuild or restart is needed.

## 7. Configure and enable publishing AI separately

Publishing uses Laravel AI SDK agents with an explicitly selected native OpenRouter provider. It does not use the SDK's default OpenAI provider, so an `OPENAI_API_KEY` is not required for this flow. Installing `laravel/mcp` does not add a publishing MCP endpoint or require another service.

Set `OPENROUTER_API_KEY` through the secret manager. The normal endpoint is `OPENROUTER_BASE_URL=https://openrouter.ai/api/v1`. These credentials are the only publishing environment configuration: there is no model, provider, price, token-limit, or timeout matrix to supply. The provider HTTP timeout is code-owned (540 seconds). Agent work is queued, so it is only a guard against a hung request, not a latency target: high-effort roles have exceeded 80 seconds in the live model trial. It must stay below the job's 900-second timeout (section 6), with room for research source fetches, and a timeout pauses the activity as uncertain after the provider may already have billed it. A Redis/Horizon supervisor would need a timeout above it; the checked-in 60-second supervisor is not suitable. Agent requests also ask OpenRouter to prefer higher-throughput upstream providers and to exclude 4-bit, 6-bit and integer quantizations, so a request can cost more per token than OpenRouter's price-first default; the key's OpenRouter limit still bounds spend.

Spending is controlled in OpenRouter, not by the application. Set the key's credit limit and any workspace limits there before enabling requests. The application keeps no allowance, makes no reservation before a call, and does not reconcile generation costs; missing usage metadata never blocks otherwise valid output.

Every agent role has a recommended model in code, drawn from the curated list in `config/publishing_agents.php`. An operator with `publishing.configure-agents` can pin a different listed model per task under **Admin → Publishing → Settings**, or reset a task so it follows its recommendation again. OpenRouter Auto Router is one of the options; it lets OpenRouter choose per request, and approval continuations resume on the concrete model that asked. Saved choices apply to work that has not started, and they never change the model recorded on started work. The page shows only whether a key is configured; keys are never entered or displayed there.

Research enables OpenRouter's web plugin with `engine=exa`, `mode=auto`, and at most five results. No separate `EXA_API_KEY` is used; OpenRouter bills plugin use under the same key limits.

When authorized to run the editorial pilot, turn off **Pause agent requests** on the settings page and save. No configuration cache rebuild or restart is needed for that change. Unpausing dispatches nothing by itself: queued work waits for the next recovery pass, newly started work is dispatched immediately, and paused, failed, or approval-waiting activities keep their status. Developing an idea or answering a paused interview can enqueue billable work. Requests can transmit permitted brief/manuscript/evidence/voice context to OpenRouter and its selected providers; research adds the search-provider path. AI-processing consent and permission to publish are separate.

Provider failures are recorded on the activity with fixed messages that never echo the key or response body:

- Missing key: the activity pauses before any request with “OpenRouter credentials are not configured.” Set the secret, rebuild the configuration cache, and restart workers; the next recovery pass resumes the paused work.
- HTTP 401/403: OpenRouter rejected the key or its permissions. HTTP 402: the key or workspace has no remaining credit or limit. Both pause the activity.
- Other HTTP 4xx: the selected model is unavailable or does not support the required parameters. Choose another model or reset the task on the settings page.
- HTTP 429, or a reply the app rejects: the activity is marked failed and is never retried automatically. The owner chooses **Try again** in the workspace once the cause clears; after the retry limit, a further failure pauses the activity for review.
- A saved override that is no longer on the curated list pauses the activity before any request and is marked **Reset required** on the settings page. Resetting it lets the next recovery pass resume the work.
- Timeouts, disconnects, and interrupted runs pause the activity as uncertain. Check OpenRouter's activity log before deliberately rerunning.

Native `AskAuthor` tool requests wait in the workspace until the initiating author supplies answers or declines. Answers resume the same stored conversation via a queued, re-authorized SDK call. Declining does not make another model completion. Neither action approves the angle, plan, or exact release: those remain separate human publishing gates.

## 8. Verify before reopening traffic or enabling live editorial use

Public import checks, with agent requests still paused:

- Confirm all 10 imported essays under the intended account's Published tab.
- Check `/writing/`, every original article URL, `/rss.xml`, and `/sitemap.xml`; the feed should contain 10 archive entries before any new publications.
- Compare original titles, dates, links, text, chart data, and diagram meaning. Verify public pages expose the published snapshot, not a later working draft or private evidence.
- Confirm public canonical URLs use the production marketing origin, Admin requests remain private, and local/staging origins were not cached into the release.

Authenticated/editorial checks:

- Confirm the intended operator can sign in and log out, and an admission-only account cannot access publishing. Test mail delivery and password setup without logging tokens or passwords.
- Exercise desktop/mobile navigation, keyboard access, light/dark appearance, editor hydration, autosave, conflict recovery, and preview. Test a server-applied proposal/protection change followed by another edit and a reload; do not rely only on server-side tests.
- With explicit paid-pilot authorization, verify a native interview pause survives reload, answering resumes it, stale/replayed requests are rejected, and provider failures remain visible on the activity. Check separate angle, plan, review, release approval, scheduling, and delivery gates. Do not publish disposable smoke-test content to the public production archive.

Known follow-ups at this test checkpoint, not claims of completed production acceptance:

- The current `User` model lacks Fortify's two-factor trait; successful recovery-code authentication has not been established. Fix and test the existing two-factor flow before relying on it for an enabled account.
- Server-side proposal/protection changes may leave the `wire:ignore` editor stale. Verify and resolve synchronization before using those mutations for live editorial work.
- Home selects newest-per-kind activities before freshness filtering, so a newer stale activity can hide an older current failure. Its 50-row lazy batches also have no total scan bound. Use the article workspace as authoritative and resolve these Home correctness/scaling findings before treating its attention list as exhaustive.
- Automated tests and fixture screenshots do not establish owner acceptance, real paid-provider quality, or PostgreSQL concurrency under simultaneous traffic. Archive appearance, authenticated usability, and the three-piece editorial pilot remain owner checks.

## 9. Failure handling and rollback

- Failed migrations, imports, or incomplete archive checks: do not open the new public reader to traffic. Inspect the failure and retained database backup; do not solve a collision by deleting an article.
- Invitation failure: inspect sanitized output and actual mail configuration. Account/role provisioning may have succeeded already. Do not reset existing credentials by hand or print a password-reset link in logs.
- Pending agent work: check the configured queue connection, queue name, worker health, and scheduler. Approval-waiting work intentionally does not resume itself.
- Uncertain or stale paid output: preserve conversation and activity records, check OpenRouter's activity log, and investigate before any deliberate new attempt. `queue:retry all` and manually resetting activity statuses are not safe recovery procedures, and unpausing agents resets no statuses. The periodic recovery command pauses old running work; it does not resolve already-paused records.
- To stop new inference, turn on **Pause agent requests** on the settings page and save; no recache or restart is needed, and queued work and author answers wait in place. The pause is not a kill switch for requests already in flight. The delivery scheduler is independent; withdraw scheduled releases or deliberately pause the scheduler if publication must also stop.
- There is no file-reader rollback. Revert to code compatible with the retained publishing schema and SDK conversations, or restore a coordinated application/database backup with explicit approval and reconciliation of changes since that backup. Do not run blanket migration rollback or delete conversation tables after live work exists.
- On subsequent releases, normally build, migrate, sync authorization, refresh caches, and restart supervised processes. Do not automatically re-invite the operator or repeat the archive write.
