# Implementation Spec: Agent Publishing Studio — Phase 1

**Contract**: ./contract.md\
**Phase**: Publishing foundation and release integrity\
**Estimated effort**: L
**Prerequisites**: None. Implement on `ideation/2026-09-22-agent-publishing-studio`; no deployment is authorized.

## Technical Approach

Build a concrete Laravel publishing domain, not a generic workflow framework. Keep article identity, immutable manuscript revisions, a publishing attempt, approval records and immutable release packages separate. An article's live release and its current working attempt are independent: developing a revision must never hide or change published content. This phase implements database/domain invariants; the public file-backed reader remains unchanged until Phase 5.

Reuse the existing User/web-guard identity and domain-local authorization catalogs. There is no tenant publishing, machine identity, role-management UI or permission bypass. Use Laravel transactions and row locks for database mutations, with expected-version checks as well as idempotency keys. Never hold database locks across a remote request. No new package dependency is needed here.

Before editing, read applicable `.ai/rules` and activate Laravel best-practices, permission and testing skills. Inspect the schema with Boost and confirm installed versions (`composer show --direct`). Create PHP artifacts with the appropriate noninteractive Artisan generators; use existing directory families. Do not apply destructive migrations, provision accounts or change live roles without explicit approval. Tests use factories and their isolated database.

**Schema preflight (2026-09-22)**: The controller used Boost `database-schema` summary and detailed `users` inspection. The app database is PostgreSQL; `users.id` is an auto-increment bigint with a primary key and unique email. Existing tables include authorization pivots, organizations/memberships, jobs/batches and cache/locks; no publishing tables exist yet. Preserve these structures and use compatible bigint foreign keys. This is schema evidence only, not authorization to mutate application data. Refresh inspection if the environment changes. Runtime preflight found Herd CLI PHP 8.5.8 and Herd sites on PHP 8.5; reuse the already-running Herd site rather than starting another PHP server. Follow the newly confirmed Herd guidance even where older local-development notes mention starting an Artisan server.

## Decisions Considered and Rejected

- **Three distinct human gates** — rejected combining angle and plan approval. Research/outline requires the angle; full drafting requires the plan; publication requires the exact release.
- **Agents propose rather than overwrite** — rejected silent replacement of authored text. Every agent result identifies its input version; only deliberate acceptance may replace existing prose.
- **Separate live and working versions** — rejected publishing the latest mutable draft or hiding an article while it is revised.
- **$5 per publishing attempt** — rejected a reset for every autosave/draft and approval of every individual model call. Automatic retries and revisions share the same allowance.
- **Imported historical releases need no retrospective approvals** — rejected forcing old essays through fictitious historical agent stages. A new publishing attempt on an imported article uses the normal three gates.
- **Explicit owner provisioning** — rejected assuming that a seeded User already has Admin and publishing roles. Assign domain roles, not direct permissions, after identifying the account with the owner.
- **Standard Admin routes and reusable shell** — rejected Folio application routing and a publishing-specific global Admin architecture. Later phases put the module under standard Laravel routing; Folio remains marketing-only.
- **Focused implementation** — rejected generic state-machine infrastructure, a provider registry, tenant publishing and a full administration suite.

## Feedback Strategy

**Inner-loop command**: `php artisan test --compact tests/Feature/Publishing/EditorialWorkflowTest.php`\
**Playground**: Pest feature tests using factories and `RefreshDatabase`.
**Why**: The highest-risk behavior is state mutation under stale inputs, not markup. Create failing transition assertions before actions.

## File Changes

### New Files

| File Path | Purpose |
| --- | --- |
| `app/Authorization/Publishing/Permission.php` | Transport-neutral capabilities |
| `app/Authorization/Publishing/Role.php` | Domain-local author role |
| `app/Authorization/Publishing/Catalog.php` | Catalog definitions |
| `app/Policies/ArticlePolicy.php` | Reusable publishing authorization |
| `app/Models/Article.php` | Stable article identity and live/working pointers |
| `app/Models/ArticleRevision.php` | Append-only document snapshots |
| `app/Models/PublishingAttempt.php` | Stage, input version and allowance identity |
| `app/Models/EditorialApproval.php` | Human approvals bound to input digests |
| `app/Models/ArticleRelease.php` | Immutable prepared/published content snapshots |
| `app/Models/Publishing/EditorialStage.php` | TitleCase backed stage enum |
| `app/Models/Publishing/ApprovalKind.php` | Angle, Plan, Release enum |
| `app/Actions/Publishing/WriteArticle.php` | Capture and optimistic-concurrency saves |
| `app/Actions/Publishing/AdvancePublishingAttempt.php` | Start, pause, resume, rethink, park, abandon |
| `app/Actions/Publishing/ApprovePublishingStage.php` | Human gate decisions |
| `app/Actions/Publishing/ManageArticleRelease.php` | Release preparation/invalidation and delivery invariants |
| `app/Services/Publishing/PublishingFingerprint.php` | Deterministic canonical JSON digests |
| `database/migrations/*_create_publishing_tables.php` | Domain schema and indexes; generate timestamp with Artisan |
| `database/factories/ArticleFactory.php` | Article test states |
| `database/factories/ArticleRevisionFactory.php` | Manuscript fixtures |
| `database/factories/PublishingAttemptFactory.php` | Developing/review/paused states |
| `database/factories/EditorialApprovalFactory.php` | Explicit approval fixtures |
| `database/factories/ArticleReleaseFactory.php` | Prepared/live/imported snapshots |
| `tests/Feature/Publishing/EditorialWorkflowTest.php` | State machine and gate enforcement |
| `tests/Feature/Publishing/ReleaseIntegrityTest.php` | Version/release/scheduling invariants |
| `tests/Feature/Publishing/PublishingAuthorizationTest.php` | Capability and direct-action boundaries |

### Modified Files

| File Path | Changes |
| --- | --- |
| `config/authorization.php` | Register Publishing Catalog |
| `tests/Feature/Authorization/AuthorizationSyncCommandTest.php` | Include new catalog where existing exact expectations require it; preserve stale/prune protection |

### Deleted Files

None. Do not modify `resources/writing`, public rendering or customer authorization.

## Implementation Details

### 1. Domain records and shared interface

**Pattern to follow**: `app/Models/Organization.php`, `database/factories/OrganizationFactory.php`, `tests/Feature/Authorization/OrganizationMembershipTest.php`.

Use normal Eloquent integer IDs and explicit casts/relationships. Do not add an event store. The cross-phase interface is:

- `Article`: unique stable `slug`, nullable `author_id`, captured `idea`, `working_revision_id`, `published_release_id`, `current_attempt_id`, nullable `first_published_at`, timestamps. Unpublished ideas may have a generated internal slug; once published the public slug is immutable in this release. Validate reserved/colliding slugs before first publication. Do not automatically regenerate an existing slug from a changed title.
- `ArticleRevision`: `article_id`, monotonic `number`, `parent_revision_id`, `created_by`, `origin` (human/agent-initial/agent-accepted/import), versioned `document` JSON, `metadata` JSON, canonical `content_hash`, `client_mutation_id`, timestamps. Snapshot content is append-only; metadata includes title, description and tags. Unique `(article_id, number)` and `(article_id, client_mutation_id)` when a mutation key is supplied. Reusing a key with different payload is a conflict, not success.
- `PublishingAttempt`: `article_id`, initiating `user_id`, `stage`, `input_version`, JSON brief/angle/plan/interview context, paused/parked/abandoned timestamps and reason, `allowance_nano_usd` default `5000000000`, timestamps. Store monetary quantities as integers, not binary floats; this is $5, not $5,000. Phase 4 adds activity/evidence/review records and reservations. Beginning work is deliberate; an autosave, generated draft, pause/resume or review restart never creates a new allowance automatically.
- `EditorialApproval`: `attempt_id`, `kind`, `input_hash`, nullable `revision_id`/`release_id`, approving human `user_id`, approved and invalidated timestamps. Approvals are audit facts, not booleans supplied by a client or model. Historical imported releases have `origin=import` rather than fabricated approval rows.
- `ArticleRelease`: `article_id`, `attempt_id` (nullable for import), `revision_id`, `origin` (editorial/import), immutable `payload` JSON and `release_hash`, status, scheduled UTC instant (nullable), publishing actor, published/withdrawn timestamps. Payload includes document, rendered-content version, metadata, original public date, canonical slug, supporting-evidence/review manifest and delivery intent. Phase 2 supplies rendering; Phase 5 completes readiness and public delivery. Publication state/timestamps may change; approved content may not.

Create foreign keys in dependency order, adding circular article pointers only after target tables exist. Index article live/working lookups, attempt stage/paused status, approvals `(attempt_id, kind, input_hash)` and release `(status, scheduled_at)`. Check that all referenced revisions/releases belong to the same article; a foreign key on a bare ID alone does not prove this. Retain published history when a User is removed; do not cascade-delete articles with their author.

Canonical fingerprints sort associative keys recursively but preserve array order. Reject non-finite numbers and unserializable values. Shared document boundary: `{version: 1, type: "doc", content: [...]}`; Phase 2 owns the complete node/mark validator. Until then, require this basic envelope and impose payload-size limits; no editor endpoint is exposed before Phase 2 validation exists.

**Feedback loop** — Playground: fresh migration and factories in the feature suite. Experiment: absent pointers, cross-article IDs, duplicate mutation keys and duplicate revision numbers. Check: `php artisan test --compact tests/Feature/Publishing/ReleaseIntegrityTest.php`.

### 2. Concrete workflow and version checks

**Pattern to follow**: `app/Actions/ReadWriting.php` for a small domain action with named methods; no generic repository layer.

`WriteArticle` exposes capture and save operations taking the actor, article ID, expected revision ID, document/metadata and mutation ID. `AdvancePublishingAttempt` exposes deliberate develop/rethink/pause/resume/park/abandon operations. `ApprovePublishingStage` takes an actor, attempt, approval kind and expected input digest, never a trusted client-supplied approval status.

A saved idea has no running attempt. Develop creates the attempt and makes interview/brief work eligible; Phase 4 dispatches it. Within Developing, angle approval permits research/challenge/outline/visual planning; plan approval permits full drafting. The state proceeds through Drafting, InReview, Approved, Scheduled and Published. A separate pause/park/abandon marker retains where work stopped. Missing answers or blockers pause; resume does not imply answering them. Published refers to an attempt's completed release, not to absence of a newer working attempt.

Brief/angle changes invalidate angle, plan and release approvals. Plan changes invalidate plan and release approvals. Manuscript/release-metadata changes invalidate exact-release approval, mark old results stale and withdraw a scheduled replacement. Rethink is explicit; never perform it because a reviewer dislikes a sentence. Human authoring can occur before the agent gates; the gates constrain automatic stage progression and later publication, not the ability to type.

Under a short transaction, lock the article and current attempt in a consistent order, recheck expected IDs/versions and authority, append a revision, advance the pointer and invalidate downstream approvals atomically. A stale edit produces a conflict without mutating anything. The response supplies the authoritative revision ID/hash; it never replaces the client's unsaved text. Agent jobs later use the same commit boundary with their recorded input version. Do not dispatch work until after commit.

**Feedback loop** — Playground: transition tests. Experiment: zero approvals, each correct approval, changed brief, parked attempt, duplicate develop clicks and old revision saves. Check: `php artisan test --compact tests/Feature/Publishing/EditorialWorkflowTest.php`.

### 3. Release invariants

`ManageArticleRelease` prepares a snapshot from the current revision, records an exact human approval and validates a delivery request against that snapshot. These are concrete domain operations; Phase 5 wires their readiness checks, scheduler and public reader. Keep semantic document rendering out until Phase 2 supplies it rather than introducing fake production renderers.

Approval must bind to every release-affecting input, including title/description/tags, slug, public date, document/figures, evidence/review disposition manifest and chosen delivery intent. Select scheduled time before approval; changing it requires a newly approved package. Actual delivery timestamps are audit output rather than prose inputs. A release-approved state must not be inferred from an approved manuscript alone.

Any delivery operation rechecks the exact approval, current version and unresolved blockers inside the transaction. Use the expected previous live release as a compare-and-swap condition so an older scheduled job cannot replace a more recent publication. Duplicate delivery of the same package is a no-op with the same result; mismatched versions fail closed. Preserve the first publication date on revisions. Public readers will later use only `published_release_id`.

**Feedback loop** — Playground: release tests with fixed test clock. Experiment: approve then edit, approve then change time, deliver twice, deliver an old scheduled revision after a newer publication. Check: `php artisan test --compact tests/Feature/Publishing/ReleaseIntegrityTest.php`.

### 4. Authorization and operator bootstrap

**Pattern to follow**: `app/Authorization/Admin/{Permission,Role,Catalog}.php`, `app/Policies/OrganizationPolicy.php`, `app/Console/Commands/SyncAuthorization.php`.

Define `publishing.view`, `publishing.write`, `publishing.develop`, `publishing.approve`, `publishing.publish`, `publishing.budget`; a domain-local `publishing.author` role grants this personal tool's set. Policies/actions check permissions, never role names. Admin admission remains only `admin.view`; possessing it alone cannot read manuscripts or invoke publishing actions. No organization membership grants these global capabilities. All mutations require the authenticated actor again at the domain boundary; worker context must not bypass permission checks.

Operator bootstrap is an explicit later action, not a migration/seeder/boot side effect: run `php artisan authorization:sync --no-interaction`; ask Nick which existing account ID is his; obtain approval to grant `admin.access` and `publishing.author`; assign those catalog-defined roles using a deliberate operator command/session; verify login and action access. Never guess the user email or silently create a User. Test grants use factories only. A later module can reuse the shell without receiving publishing permission.

**Feedback loop** — Playground: synced catalogs and factory users. Experiment: guest, unrelated membership, admission-only user, authorized author, permission revoked between request and mutation. Check: `php artisan test --compact tests/Feature/Publishing/PublishingAuthorizationTest.php tests/Feature/Authorization/AdminAuthorizationTest.php`.

## Testing Requirements

| Test file | Required coverage |
| --- | --- |
| `tests/Feature/Publishing/EditorialWorkflowTest.php` | Every allowed/denied gate transition, deliberate capture/develop, pause/resume/park/abandon, rethink, import-origin versus new attempt |
| `tests/Feature/Publishing/ReleaseIntegrityTest.php` | Immutable snapshots, stale saves, cross-article IDs, idempotency payload collisions, downstream invalidation, live-version isolation, schedule withdrawal, duplicate delivery |
| `tests/Feature/Publishing/PublishingAuthorizationTest.php` | Global role grants, direct-action checks, admission-only denial, no membership bypass, revoked actor |

Tests asserting database locks with in-memory SQLite cannot prove actual simultaneous row-lock behavior. Add deterministic stale/version tests now; Phase 4/5 must also exercise concurrent reservations/delivery against the configured transactional database without using production data. Never describe sequential fake requests as proof of real lock contention.

## Failure Modes

| Component | Failure / trigger | Impact | Mitigation |
| --- | --- | --- | --- |
| Saves | Two tabs submit the same base revision | Lost human prose | Lock plus expected-version comparison; preserve rejected payload for UI recovery |
| Workflow | Earlier context changes during agent work | Wrong plan/draft attached | Input-version binding and downstream invalidation |
| Approvals | Client or model supplies an approved flag | Unauthorized release | Human-only authorized action; server-derived digest |
| Release | Delayed old job arrives after a new release | Public regression | Snapshot binding, expected-live pointer and idempotency |
| Identity | Owner not provisioned, or actor removed | Unusable UI or unauthorized job | Explicit bootstrap; fail closed on missing/revoked actor |
| Persistence | Same mutation key used for different content | Silent discarded edit | Compare stored digest and return conflict |

## Validation Commands

```bash
php artisan test --compact tests/Feature/Publishing tests/Feature/Authorization
vendor/bin/pint --dirty --format agent
composer types:check
```

Run affected tests again after changes and before committing. Use the repository's commit skill when available; no Co-Authored-By trailer. Phase commit body must include `docs/ideation/2026-09-22-agent-publishing-studio/spec-phase-1.md` verbatim. Do not stage unrelated changes or declare later integration criteria passed.

## Rollout and Boundaries

No public cutover, model calls, production dependencies, role grants or real archive import in this phase. Records and tests are the output. Later phases must use these model/action names and invariants, extending them rather than creating a second state machine. Owner approval remains outstanding for the real editorial pilot even after code tests pass.
