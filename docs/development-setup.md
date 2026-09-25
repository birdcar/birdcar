# Development setup

Run the public site, Admin, and Agent Publishing Studio locally, including real OpenRouter AI calls. For deployment, use [production setup](production-setup.md) instead. Historical ideation specs describe superseded AI and file-reader implementations.

**Local AI calls still cost real money and send context to external providers.** Keep agents paused during bootstrap, use a dedicated development OpenRouter key with a spending limit, and use non-sensitive test content. The automated tests described below fake inference; the browser smoke test does not.

All commands run from the repository root. These are instructions for you to execute, not an automatic setup script. Never point this setup at a production database or mail service.

## 1. Prerequisites and local sites

- PHP **8.5**, matching `.php-version`, for both the web runtime and CLI. Install the extensions required by the locked dependencies and your database driver. Queue timeouts require CLI `pcntl`; use a compatible macOS/Linux runtime or Linux environment for workers if your host lacks it.
- Composer with access to the private Flux Pro repository. Configure your license credentials using Composer's authentication mechanism; do not commit them or paste them into this guide.
- Bun for dependencies/builds, and a current Node **22.18+** in the Node 22 line pinned by `.node-version`. The installed Vite Plus requires at least 22.18 on that line.
- A local database and a local SMTP catcher, such as Herd's mail service or Mailpit. Database-backed queues/sessions/cache mean Redis is not required for this guide.
- An OpenRouter account with credits and a dedicated API key for the real-AI steps. No separate OpenAI, Anthropic, or Exa key is needed for publishing.

The existing owner checkout uses Herd, with these origins resolved through Laravel Boost:

- Marketing: `https://birdcar.test`
- Admin: `https://admin.birdcar.test`

Both hosts serve **the same checkout**, with Laravel's `public/` directory as the web root. Keep existing site registrations and TLS configuration. Do not start `php artisan serve`, `php artisan dev`, or `composer dev` alongside them; those are not the setup path for this checkout.

On a **fresh Herd installation only**, after cloning the repository and confirming the names are unused, you can register both hosts from its root:

```bash
herd link birdcar --secure --isolate=8.5
herd link admin.birdcar --secure --isolate=8.5
```

Trust Herd's local certificate authority through its normal setup. Verify both sites in Herd before continuing. If using an existing Lerd or other managed environment, keep that manager rather than also installing Herd: configure equivalent public/Admin hosts, use its PHP/Composer execution commands, and use the actual service hosts it supplies. The origins above describe the verified Herd setup, not a claim that another manager has already registered them.

## 2. Configure the checkout and install dependencies

Create `.env` only if it does not exist:

```bash
[ -f .env ] || cp .env.example .env
```

Edit the existing keys in `.env` rather than appending duplicates. For the HTTPS Herd origins above, set:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=https://birdcar.test
MARKETING_URL=https://birdcar.test
MARKETING_INDEXABLE=false
ADMIN_URL=https://admin.birdcar.test
POSTHOG_DISABLED=true

SESSION_DRIVER=database
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database
```

Do not leave `.env.example`'s production `ADMIN_URL` or localhost `APP_URL` in place: authentication, invitations, routing, and previews depend on the configured origins. If your registered local sites use different schemes/ports, use those exact origins; a secure-only session cookie will not work over plain HTTP. Keep the Admin and public hosts distinct.

Do not copy production credentials into this environment. Leave optional integrations without credentials unless you are deliberately testing them. Disable any separately configured monitoring integrations you do not want sending local data.

Install the locked dependencies, including development dependencies:

```bash
composer install --prefer-dist --no-interaction
composer check-platform-reqs
bun install --frozen-lockfile
php artisan config:clear --no-interaction
```

For a **new `.env` with an empty `APP_KEY` only**, generate its key:

```bash
php artisan key:generate --no-interaction
```

Preserve an existing key and database. Do not use `composer setup` as a routine refresh: that script generates a key and runs migrations before you have deliberately completed this setup.

## 3. Configure and migrate the local database

**Existing checkout:** keep its current local PostgreSQL/database configuration and data. Do not switch engines or replace its database just to match this guide.

**Fresh checkout:** choose one local database:

- **SQLite:** the `.env.example` default and simplest bootstrap. Set `DB_CONNECTION=sqlite`, leave `DB_DATABASE` unset to use `database/database.sqlite`, and remove any unrelated `DB_URL` override. Create the file without truncating an existing one:

  ```bash
  [ -f database/database.sqlite ] || touch database/database.sqlite
  ```

- **PostgreSQL:** create a dedicated local database through your database manager, then set `DB_CONNECTION=pgsql` and its local `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`. With Lerd, use its database/environment setup tools to wire the service rather than hand-editing managed connection settings. This is the better choice for investigating production-like concurrency; SQLite tests do not establish PostgreSQL locking behavior.

Do not leave `DB_QUEUE_CONNECTION`, `DB_QUEUE_TABLE`, or `DB_QUEUE` pointing at a different environment. Unless deliberately configured otherwise, leave them unset so the database queue uses this application's connection, `jobs` table, and `default` queue. Agent jobs always use the separate `publishing-agents` queue, and the database retry window (`DB_QUEUE_RETRY_AFTER`) defaults to 960 seconds, above their 900-second job timeout; if you set it, keep it above that.

Once you have confirmed the target is local:

```bash
php artisan config:clear --no-interaction
php artisan migrate --no-interaction
php artisan authorization:sync --no-interaction
php artisan migrate:status --no-interaction
bun run build
```

Migrations provide publishing records, jobs, sessions/cache, `agent_conversations`, `agent_conversation_messages`, the activity fields for native tool approvals, and the publishing agent settings. A fresh settings row starts with agent requests **paused** and no model overrides. Do not re-publish SDK migrations or generate a second conversation schema. Authorization sync defines roles/permissions, including `publishing.configure-agents` on the author role; it does not assign an author to an account.

No default seeder is required. Avoid `migrate:fresh` on a database whose work you want to keep. Building assets is enough to browse the site; the live asset watcher is optional in section 7.

## 4. Capture invitation mail and create your local Admin

The default `MAIL_MAILER=log` is **not sufficient**. Admin invitations deliberately reject log, array, null, and unsafe failover transports, including in local development. Use SMTP delivery into a local mail catcher instead of weakening that safeguard.

Start your local catcher using its own service manager and note its SMTP host/port and inbox UI. For example, **if** it accepts unencrypted SMTP on the host's loopback port 1025:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_URL=null
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS=developer@birdcar.test
MAIL_FROM_NAME="Birdcar local"
```

Those are example catcher settings, not a claim that port 1025 is already running. Use the service's actual settings. From a container, `127.0.0.1` refers to the container, so use the manager-provided reachable mail host instead. A pre-existing `MAIL_URL` can override individual SMTP fields; clear it or deliberately configure it for the catcher. Verify that captured messages cannot be relayed to real recipients.

Then clear cached configuration and invite your local test identity:

```bash
php artisan config:clear --no-interaction
php artisan admin:invite developer@example.com --name="Local Developer" --no-interaction
```

Replace the example email/name as appropriate. This command creates or reuses the account, grants the configured root bundle (`admin.access` and `publishing.author`), sends a password-setup invitation, and reports the user ID. Open the invitation in the catcher's inbox, verify it points to your **local Admin origin**, set a password, then sign in. No queue worker is needed for this synchronous invitation.

If you already have an authorized local account, sign in with it instead of creating a duplicate. Existing passwords, two-factor data, and unrelated roles are preserved by invitations. A mail failure may occur after account provisioning: fix the catcher and retry deliberately, allowing for password-broker throttling. Do not print reset tokens or reset existing credentials by hand.

Keep the reported user ID for the archive import. For an existing account, this read-only lookup returns its ID without printing its credentials:

```bash
php artisan tinker --execute 'dump(App\Models\User::query()->where("email", "developer@example.com")->value("id"));' --no-interaction
```

## 5. Import the historical archive

Public writing, RSS, and sitemap read **only published database releases**. Files under `resources/writing/**` are import/parity inputs, not a public runtime fallback. An unimported local database has no historical essays even when those files are present.

Preview the import first:

```bash
php artisan publishing:import-archive --dry-run --no-interaction
```

Expect 10 published essays and no excluded entries. Review the report, then replace `LOCAL_USER_ID` with the intended author's actual local ID:

```bash
php artisan publishing:import-archive --write --actor=LOCAL_USER_ID --no-interaction
```

The actor must have `publishing.write`. Imported work appears under that author's Published tab; do not assume every database uses user ID 1. A local write does not need `--confirm-production-write`. If a command reports that production confirmation is required, stop and check your environment rather than adding the flag.

The first successful import reports `created: 10`; matching repeat imports are skipped without replacing edits or live pointers. Conflicting imports fail rather than clobber records. If this checkout already has the archive, no repeat write is necessary. Check the Published tab and the marketing host's `/writing/`, original article URLs, `/rss.xml`, and `/sitemap.xml`. There is no reader-mode environment flag to set.

## 6. Configure real OpenRouter AI

The application already includes Laravel AI SDK agents and its native OpenRouter provider. Do not install another SDK, configure the generic OpenAI provider, run an MCP server, or send direct chat-completion requests to test around the application's approval controls.

### Credentials

Only the OpenRouter credentials live in `.env`. Replace the placeholder value:

```dotenv
OPENROUTER_API_KEY=REPLACE_WITH_YOUR_PRIVATE_DEVELOPMENT_KEY
OPENROUTER_BASE_URL=https://openrouter.ai/api/v1
```

Keep the API key in gitignored `.env` or your local secret manager. It must not use a `VITE_` prefix or appear in browser code, screenshots, logs, or committed files. Publishing explicitly selects `ai.providers.openrouter`; `config/ai.php`'s generic default provider does not change this path. After editing `.env`, run `php artisan config:clear --no-interaction`.

There are no publishing model, provider, price, token-limit, or timeout variables to set. Give the development key its own credit limit in OpenRouter: OpenRouter key and workspace limits are the spend controls. The application does not keep an allowance, reserve money before a call, or reconcile generation costs, and missing usage metadata never blocks otherwise valid output.

### Models

Every agent role has a recommended model in its code, chosen from the curated list in `config/publishing_agents.php`: Gemini 3.8 Flash, DeepSeek V4 Pro, DeepSeek V4.1 Flash, and OpenRouter Auto Router. You do not need to choose anything to start.

To change a role, sign in and open **Admin → Publishing → Settings** (`/publishing/settings` on the Admin host). Each task shows its recommendation and a model selector:

- **Use recommended** removes the saved override, so the task follows the code recommendation, including future changes. **Reset … to recommended** does the same immediately for one task.
- Choosing a model pins it, even when it matches today's recommendation.
- **OpenRouter Auto Router** lets OpenRouter pick a model for each request, so the model can vary. Reasoning effort is not sent to Auto. A conversation waiting on your answer continues on the concrete model that asked the question.

Saved settings apply to work that has not started yet. They need no config rebuild or process restart, and they do not change the model recorded for work already started. The page shows only whether an OpenRouter key is configured; it never accepts or displays the key.

Research uses OpenRouter's web plugin with `engine=exa`, `mode=auto`, and at most five results, whichever model the Research challenge task uses. No separate `EXA_API_KEY` is used; plugin charges are billed by OpenRouter under the same key limits. Public source retrieval also needs outbound HTTPS. Internal/loopback URLs are deliberately blocked by source-fetch safety checks; do not weaken those checks for a local smoke test.

### Turn agent requests on deliberately

Before turning requests on, check existing pending activities and scheduled publications in your local database. Running the worker and scheduler can resume previously queued work, not just the next idea you create. Sending draft, source, and voice context for AI processing is separate from having permission to publish it.

When the key and consent are ready, open **Admin → Publishing → Settings**, turn off **Pause agent requests**, and choose **Save settings**. The saved state badge changes to **Saved: On**; an unsaved switch change is labeled as not saved and has no effect.

A non-inference check that prints only booleans, not your key:

```bash
php artisan tinker --execute 'dump(["agents_paused" => app(App\Settings\PublishingAgentSettings::class)->paused, "openrouter_key_configured" => filled(config("ai.providers.openrouter.key"))]);' --no-interaction
```

This only checks local configuration, not key validity, credits, provider availability, or paid behavior. Do not dump the complete `ai` configuration to diagnose a missing key.

## 7. Run the local processes

Keep the existing site manager serving PHP. Use separate terminals for the following processes unless equivalent managed workers are already running; do not duplicate them.

**Queue worker — required for AI:**

```bash
php artisan queue:work database --queue=publishing-agents,default --sleep=3 --tries=1 --timeout=900 --no-interaction
```

This mirrors production, where Laravel Cloud runs a dedicated `publishing-agents` managed queue beside the default one. The worker must list `publishing-agents`, or agent jobs wait unprocessed. Keep `QUEUE_CONNECTION=database` and leave `DB_QUEUE_RETRY_AFTER` unset (960 seconds) or above 900. Use the actual default queue name if you intentionally changed `DB_QUEUE`. Do not use `sync` for the interactive AI flow, and do not use `composer dev`/`php artisan dev` for agent work: it starts a competing Octane server, Horizon, and a `queue:listen --timeout=0` worker for the default queue only.

Horizon is installed but only processes Redis queues; it is not a replacement for this database worker. Switching to it requires deliberate Redis/supervisor configuration, including timeouts and retry windows. Do not launch it alongside this guide's worker expecting it to process database jobs.

**Scheduler — for full publishing/recovery behavior:**

```bash
php artisan schedule:work --no-interaction
```

It delivers due approved releases every minute and runs editorial recovery every five minutes. While agent requests are on, recovery re-enqueues durable pending activities; it always pauses running work older than 30 minutes as uncertain so you can review it. It makes no billing lookups. Neither the scheduler nor a recovery command is a read-only smoke check. To inspect registration without executing the scheduled commands:

```bash
php artisan schedule:list --no-interaction
```

**Asset watcher — optional while editing frontend code:**

```bash
bun run dev
```

Use only one asset watcher. If live assets are unavailable or HTTPS/HMR is not configured for your manager, stop the watcher and use `bun run build` instead; do not start another PHP server. An existing managed Vite worker can provide the same asset service.

After changing `.env` or PHP code, clear configuration and gracefully replace existing queue workers. Saving the publishing agent settings page is not a code or `.env` change and needs neither step:

```bash
php artisan config:clear --no-interaction
php artisan queue:restart --no-interaction
```

`queue:restart` lets the current job finish and tells workers to exit; it does **not** launch a new process. In this terminal setup, run `queue:work` again after the old worker exits. Restart `schedule:work` after schedule changes, and reload any managed long-running web runtime as appropriate. Avoid killing a model call mid-flight and assuming no charge occurred.

## 8. Run a small, real-AI smoke test

Use your local author account and a new, non-sensitive idea rather than modifying an imported essay. Keep the worker terminal and the workspace's activity display visible, and watch usage in OpenRouter.

1. Open Admin → Publishing, enter an idea, and choose **Develop idea**. This creates the active attempt; use **Start interview** in **Brief & plan** to start the AI interview.
2. Wait for the queued activity. Use **Refresh agent work** if needed; visible active work also polls. Check the activity status in the workspace, not only the Home attention list.
3. If the interviewer calls `AskAuthor`, the workspace shows **The agent needs your input**. Reload to confirm the questions persist, enter **Your answers**, and choose **Send answers and continue**. The queue resumes the same stored conversation on the model that asked, with one more paid completion.
4. **Decline request** instead stops that request without another model completion; it does not refund the initial call. A model may return a completed interview rather than ask a tool question, so absence of a pause alone is not proof of a configuration failure. The automated tool-approval tests exercise the pause/resume path deterministically.
5. Review the proposed brief and angles. Native tool answers are not an angle/plan/release approval. **Save interview answers** elsewhere in the form is not the native request's **Send answers and continue** action.
6. If deliberately testing the remaining paid stages, review and approve the angle, run research/planning, review and approve the plan, then test drafting and review. These steps can make additional calls; monitor spending in OpenRouter. Do not click publication or scheduling actions as a harmless connectivity test: they change the locally visible public archive.

Expected outcome: a recorded activity with either usable interview output or a durable author question, its model recorded on the activity, and no implicit publishing approval. This is not proof of the full editorial pilot, release quality, or production readiness.

## 9. Automated checks without paid inference

The publishing tests use SDK/HTTP fakes; they do not require a working OpenRouter key. `phpunit.xml` specifies an in-memory SQLite database, array mail/cache/session drivers, and a synchronous queue for tests only. Do not override those settings with a production or personal working database, or run tests with a cached production configuration.

Focused setup/AI coverage:

```bash
php artisan config:clear --no-interaction
vendor/bin/pest --compact \
  tests/Feature/Auth/AdminInvitationTest.php \
  tests/Feature/Publishing/ArchiveMigrationTest.php \
  tests/Feature/Publishing/EditorialAgentsTest.php \
  tests/Feature/Publishing/EditorialToolApprovalTest.php \
  tests/Feature/Publishing/PublishingAgentSettingsTest.php \
  tests/Feature/Publishing/AgentBudgetTest.php
```

Before committing application changes:

```bash
vendor/bin/pest --compact --fail-on-skipped --fail-on-incomplete
bun test resources/js/admin/publishing/document-helpers.test.js
composer lint:check
composer types:check
bun run build
git diff --exit-code 72f7d8ad8521573cb224022c902447f9ca4c4351 -- resources/writing
```

## 10. Troubleshooting and stopping

- **Admin redirects to a production host, login loops, or missing pages:** confirm both local hosts point to this checkout, check `APP_URL`/`MARKETING_URL`/`ADMIN_URL`, scheme/ports, cookie settings, and clear configuration. Do not broadly share cookies across unrelated hosts to mask a domain mistake.
- **Invitation refuses the mailer or never appears:** use the direct local SMTP catcher, not `log` or `failover` containing `log`; verify the reachable SMTP host/port and `MAIL_URL`. The account may already exist after failed delivery.
- **Archive empty or Published tab missing essays:** confirm the database was imported and the signed-in author matches the import actor. Source files do not act as a fallback.
- **Activity remains pending:** check the settings page shows **Saved: On**, the configured database/queue matches the worker, and the worker was restarted after `.env` or code changes. Work queued while paused waits for the next scheduled recovery pass (or a deliberate `php artisan publishing:recover-activities`); unpausing does not dispatch it. Inspect old pending work before allowing that pass to run.
- **“OpenRouter credentials are not configured” or the settings page reports no key:** set `OPENROUTER_API_KEY`, clear configuration, and restart the worker. The activity pauses before any request; once the key is configured, the next recovery pass returns it to pending work.
- **“The saved model override for this agent role is not supported”:** the settings page marks that task **Reset required**. Reset it, or save the page, to return the task to its recommendation; the next recovery pass then returns the paused activity to pending work.
- **HTTP 401/403 or 402:** 401/403 means OpenRouter rejected the key or its permissions; 402 means the key or workspace has no remaining credit or limit. Fix it in OpenRouter, then rerun the paused activity deliberately.
- **Other HTTP 4xx, such as unsupported parameters:** the selected model could not accept the structured-output, tool, or reasoning parameters the task requires. Choose another model or reset the task on the settings page; do not enable arbitrary failover or remove structured-output requirements.
- **HTTP 429 rate limit:** the activity is marked failed before generating output. The application does not retry it automatically; start the work again after the limit clears.
- **Timeout, disconnect, or interrupted worker:** the activity pauses because the provider outcome is uncertain; recovery also pauses running work older than 30 minutes. Preserve the activity and conversation records, check OpenRouter's activity log, and deliberately rerun or start new work. Do not use `queue:retry all` or manually reset statuses; unpausing agents does not reset them either.
- **Stale approval or changed manuscript:** reload the authoritative workspace. Ownership, revision, and input checks also apply on resume; do not force an old tool decision into a new conversation.
- **Editor assets absent:** use the existing asset worker or `bun run build`; check the browser console and public font/build files. A successful PHP response alone does not establish that the editor booted.

Known Home activity filtering/scan-bound findings, missing Fortify two-factor trait, and potential ignored-editor synchronization issues are documented in [production setup, section 8](production-setup.md#8-verify-before-reopening-traffic-or-enabling-live-editorial-use). Use the article workspace as authoritative; do not treat local UI screenshots or green tests as resolution of those findings.

To stop local inference, turn on **Pause agent requests** in **Admin → Publishing → Settings** and save; no config clear or restart is needed. Queued work and author answers wait in place. To end the session, also use `queue:restart` to let the current job finish before leaving the worker stopped, and stop the scheduler and optional asset watcher; if they are managed services, use their manager so they do not automatically restart. Pausing does not cancel a provider request already in flight, and scheduled publication is independent of the pause. Keep the database and `.env` for the next session rather than reseeding or regenerating keys.
