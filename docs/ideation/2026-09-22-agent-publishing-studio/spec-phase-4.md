# Implementation Spec: Agent Publishing Studio — Phase 4

**Contract**: ./contract.md
**Phase**: Agent development, evidence and bounded reviews
**Estimated effort**: XL
**Prerequisite**: First Admin publishing workspace

## Technical Approach

Add a small set of explicit editorial activities to the existing publishing domain. Use Laravel HTTP, queues, transactions and persistent records; no `laravel/ai`, additional SDK, provider framework, dynamic tool registry or separate deployment infrastructure. Work is represented by durable activity rows bound to the initiating human, attempt, stage/input version and exact revision. Queue delivery may repeat; local application of results and billing reconciliation must be idempotent. External exactly-once billing is not promised.

Use OpenRouter Chat Completions directly, with an explicitly priced default model/provider and optional premium route for designated critical roles. Research uses the bounded web plugin with Exa, not an autonomous server-tool loop. Model names/prices in the original gist are evaluation hypotheses, not verified defaults. Missing credentials, pricing, capability or allowance pauses paid work. Automated tests use fakes exclusively; the owner authorizes/configures actual paid pilot work later.

Before implementation read applicable rules, Laravel/testing skills and installed queue configuration. Horizon is already installed: activate its skill if changing worker configuration, but prefer the existing default queue without any Horizon/config change. No actor/request state in singletons or static properties. Never hold a database lock while performing HTTP.

## Decisions Considered and Rejected

- **Agents advance inside human gates** — rejected manual orchestration of every call. Missing information, budgets and approval boundaries still pause work.
- **Three separate approvals** — rejected combining angle/plan or treating model approval as human approval.
- **$5 per attempt** — rejected resetting allowance on each draft/retry/review cycle. Top-up is explicit.
- **One three-lens review batch, one affected-area recheck** — rejected indefinite polish loops and automatic repeated review cycles.
- **No silent changes to human prose** — rejected applying agent output over authored text. Protected passages require explicit human unlocking before accepting a touching patch.
- **Material facts/safety block; style advises** — rejected blanket overrides of known unsupported claims/rights concerns or mandatory adoption of all editorial advice.
- **AI processing is not publication permission** — rejected conflating them; mandatory ZDR routing and a confidentiality administration subsystem remain out of scope.
- **Small configured roster** — rejected a model registry or separate configurable provider for every role. Use one default and an optional premium route, selected before real evaluation.
- **Concrete Laravel HTTP integration** — rejected adding an AI SDK/framework for one vendor.
- **Budget reservations before calls** — rejected post-hoc accounting and pretending an application estimate guarantees a provider invoice ceiling.

## Feedback Strategy

**Inner-loop command**: `php artisan test --compact tests/Feature/Publishing/AgentBudgetTest.php`

**Playground**: Pest feature tests with deterministic HTTP/queue/clock fixtures and an isolated database.

**Why**: Cost and stale-result correctness must hold before any paid call is possible.

## File Changes

### New Files

| File Path | Purpose |
| --- | --- |
| `config/publishing_agents.php` | Disabled-by-default integration, two model routes and bounded constants |
| `app/Models/EditorialActivity.php` | Durable work, exact input refs, output and status |
| `app/Models/AgentBudgetReservation.php` | One reservation and settlement per actual billable call |
| `app/Models/EvidenceSource.php` | Retrieved passages, provenance and restricted-source permissions |
| `app/Models/EditorialFinding.php` | Anchored findings/proposals and human dispositions |
| `app/Models/Publishing/EditorialActivityKind.php` | Concrete role enum, including reconciliation/recheck |
| `app/Models/Publishing/EditorialActivityStatus.php` | Pending/running/paused/completed/failed/stale status enum |
| `app/Services/Publishing/AgentBudget.php` | Atomic reservations, settlements and authorized top-ups |
| `app/Services/Publishing/OpenRouterClient.php` | Chat, generation lookup, endpoint metadata and conservative quotes |
| `app/Services/Publishing/EditorialPrompts.php` | Versioned deterministic prompts and output validators |
| `app/Services/Publishing/PublicSourceFetcher.php` | Bounded SSRF-safe source retrieval |
| `app/Actions/Publishing/StartEditorialActivity.php` | Persist eligible work and dispatch after commit |
| `app/Actions/Publishing/RunEditorialActivity.php` | Queueable execution and result application |
| `app/Actions/Publishing/ApplyEditorialProposal.php` | Human acceptance with revision/protection checks |
| `app/Actions/Publishing/FinishEditorialReview.php` | Dispositions, one recheck and explicit cycle restart |
| `app/Console/Commands/RecoverEditorialActivities.php` | Durable pending-work recovery and reconciliation |
| `database/migrations/*_create_editorial_activity_tables.php` | New records plus attempt review-cycle/top-up audit fields |
| `database/factories/EditorialActivityFactory.php` | Activity test data |
| `database/factories/AgentBudgetReservationFactory.php` | Reservation states |
| `database/factories/EvidenceSourceFactory.php` | Public/restricted/evidence-missing fixtures |
| `database/factories/EditorialFindingFactory.php` | Anchored findings and disposition states |
| `tests/Feature/Publishing/AgentBudgetTest.php` | Reservations, retry/recovery, top-ups and HTTP protocol |
| `tests/Feature/Publishing/EvidenceResearchTest.php` | Actual retrieved evidence, source safety and role boundaries |
| `tests/Feature/Publishing/EditorialReviewTest.php` | Draft proposals, same-revision reviews and bounded recheck |

### Modified Files

| File Path | Changes |
| --- | --- |
| `.env.example` | Nonsecret integration enable/key/default/premium sample names |
| `app/Models/PublishingAttempt.php` | Review-cycle/recheck fields, allowance-change audit and relations |
| `app/Actions/Publishing/AdvancePublishingAttempt.php` | Start/pause/resume eligible activity rows |
| `app/Actions/Publishing/ApprovePublishingStage.php` | Advance only after the matching human gate |
| `app/Actions/Publishing/WriteArticle.php` | Safe first empty draft and stale/proposal integration |
| `app/Actions/Publishing/ManageArticleRelease.php` | Evidence/review disposition hashes and readiness |
| `resources/views/components/admin/publishing/⚡article-workspace.blade.php` | Wire interview, source/voice selection, findings, proposals, review restart and budget top-up |
| `resources/views/components/admin/publishing/⚡dashboard.blade.php` | Real pending/blocked/activity indicators |
| `routes/console.php` | Register the existing scheduler's recovery command |
| `tests/Feature/Publishing/AdminPublishingEditorTest.php` | Real agent/review/budget UI action wiring with fakes |
| `tests/Feature/Publishing/EditorialWorkflowTest.php` | Complete automated stage progression and pauses |

### Deleted Files

None. No dependency/lockfile or worker-configuration changes are planned.

## Data Model and Interfaces

Use the Phase 1 model/action names. Add normal foreign keys and explicit query indexes; reference IDs must belong to the same article/attempt. Keep money as integer **nano USD**: initial allowance `5_000_000_000`. Decimal pricing conversion rounds reservation/settlement amounts upward conservatively, never through imprecise binary-float accounting.

- `EditorialActivity`: attempt/article/initiating-user IDs; kind/status; stage input version, revision ID/hash; review-cycle/batch identity; unique local idempotency key; prompt version/hash and immutable input references; model/provider/price snapshot; bounded response/proposal JSON; run count, availability/start/completion timestamps, pause/error reason and generation ID. Index `(status, available_at)` and `(attempt_id, review_cycle, kind)`. Inputs stay immutable; no mutable latest-draft reads after dispatch.
- `AgentBudgetReservation`: attempt/activity IDs, unique `(activity_id, call_number)` and local call key; reserved/actual nano USD, state, price/request-bound snapshot, provider generation ID, settled timestamp and retained-unknown reason. Index `(attempt_id, state)`. Multiple billable retries have different rows. Do not overwrite a previous call's cost.
- `EvidenceSource`: attempt/article/activity IDs; source type (public retrieval, owner recollection, restricted supplied material), URL/final URL/title, retrieval time/method, bounded extracted text and content hash, origin annotation metadata, unresolved-evidence reason; restricted-source processing consent and publication permission recorded separately with actor/time. Public citation is not a blanket license to republish entire content. Private source consent applies only to deliberately marked restricted material; do not require a manual compliance checklist for every public URL.
- `EditorialFinding`: attempt/article/activity/review-cycle/revision IDs and input hash; lens/kind/severity; stable block/passage anchors with expected subtree hashes; statement/rationale; supporting source IDs; proposed bounded patch; human disposition/reason/actor/time; stale marker. Index `(attempt_id, review_cycle, revision_id)` and unresolved blocking state.
- Extend attempt with `review_cycle`, `recheck_used`, and a small append-only `allowance_changes` JSON audit (mutation key, actor, previous/new amount, time). Spending/top-ups are not editorial input changes and must not invalidate an angle by themselves.

Use concrete services with PHPDoc array shapes, not undeclared DTO classes. `OpenRouterClient` provides `chat(array): array`, `generation(string): array`, `pricedEndpoint(array $route): array`, `quote(array $endpoint, array $request): array`. `AgentBudget` provides reserve/settle/retainUnknown/available and `increaseAllowance(User, attempt, integerDelta, mutationKey)` operations. `RunEditorialActivity` implements `ShouldQueue` and takes only an activity ID; generate it with a fully-qualified Artisan class path under the existing Actions namespace. All output payloads are locally validated against role-specific expected shapes before storage/application.

## Implementation Details

### 1. Spend authorization and external-call protocol

**Pattern to follow**: Phase 1 lock/expected-version mutations; Laravel HTTP fakes.

Under an attempt row lock, recheck actor permission, current stage/version, pause flags, known endpoint pricing and `confirmed spend + outstanding/unknown reservations + new bound <= allowance`. Persist a reservation before HTTP, then release the lock. Claim activity state atomically so duplicate queue deliveries cannot each initiate the same call. Cache locks can assist but are not the accounting authority.

Reserve input, output/reasoning, request and plugin costs. For web-search calls, injected result text has no documented strict excerpt-size ceiling: use the eligible endpoint's full context-capacity upper bound for input, not “typically 2–4k characters.” If a conservative full bound cannot fit $5, pause or select a genuinely cheaper supported endpoint; do not reduce the bound by guessing. Use a verified output cap inclusive of reasoning when applicable; if this cannot be established for a model, it is ineligible. Unknown extra fees, context limits or required support pause.

Send non-streaming Chat Completions to the fixed HTTPS OpenRouter API, with the configured model, messages, `max_completion_tokens`, supported structured-output parameters, `provider.only` restricted to the quoted endpoint/provider selection, `allow_fallbacks: false`, `require_parameters: true`, and verified `max_price` caps. Model metadata token prices are per token, while routing prompt/completion caps are per million tokens: convert units explicitly and test the conversion. Pin/price every eligible endpoint; `order` alone is not an allowlist. No `openrouter/auto`, implicit fallback or `:online` suffix. Do not include unsupported parameters and hope the provider ignores them.

Validate the response and record generation ID/usage immediately. Use the documented total billable cost, including plugin/search charges, rather than upstream inference-only cost. If cost is not complete, query `/generation?id=...`; retained uncertainty blocks additional automatic work. Settle exactly once. If actual cost exceeds its reservation, record the truthful amount and pause; do not cap away the excess. This system authorizes spending based on bounds but cannot guarantee the provider's invoice.

An HTTP timeout after possible receipt retains the reservation and never triggers a blind retry. Only known non-billable failures, or a fully reconciled failed generation followed by a separately authorized new call, may retry automatically, at most twice total per activity. Each new billable call gets its own reservation. 401/402/403/capability errors pause. Worker retries cannot reset this persisted limit. A stale result is still charged; it is never applied.

Top-up requires `publishing.budget`, a positive bounded integer delta and an explicit owner action. Append the allowance-change audit under lock and make repeated mutation keys idempotent. Do not reset allowance on recheck, save or resume.

**Feedback loop** — Playground: budget tests with deterministic prices and HTTP responses. Experiment: concurrent reservations totaling above $5, duplicate calls, per-million conversion, missing price, unknown timeout, double settlement, actual overrun and repeated top-up key. Check: `php artisan test --compact tests/Feature/Publishing/AgentBudgetTest.php`. Use a real nonproduction transactional DB for actual lock contention; in-memory SQLite tests are not concurrency proof.

### 2. Durable activities and bounded roles

Persist pending work inside the workflow transaction and dispatch after commit. Schedule `publishing:recover-activities` periodically through the existing scheduler. It may re-enqueue unclaimed pending work; it must not resend ambiguous running/reserved calls. Recover known generation IDs through read-only accounting lookups; otherwise pause for an operator decision. Recheck original actor permissions at start and completion, not just at UI dispatch.

The concrete sequence is: Develop idea → interview/brief → human angle approval → research/challenge → outline and visual plan → human plan approval → draft → three reviews → reconciled proposals/human decisions → one affected-area recheck → release preparation in Phase 5. Missing interview answers pause and appear as questions in the UI. Answer submission resumes the same attempt without spending-reset side effects.

One initial AI draft may populate a still-empty, untouched manuscript under the already approved plan, with `origin=agent-initial`, then proceed to review without introducing a fourth mandatory approval gate. The write is CAS-protected and never replaces typed human content. If the manuscript is nonempty or changed while drafting, store the result as a proposal; human acceptance is required to replace text. Imported articles follow this existing-prose rule. Initial metadata authored by the owner is not silently overwritten either.

Prompts have concrete JSON output validators: interview `{questions, brief, angleOptions}`, research/challenge `{claims, sourceReferences, contradictions, gaps}`, plan `{outline, argument, visualPlan}`, draft `{document, metadataProposals}`, reviews `{findings}`, reconciliation `{groups, conflicts}`, recheck `{resolved, unresolved, newBlockingFindings}`. Unknown/oversized fields and invented evidence IDs are rejected. Natural-language safety instructions complement—but never replace—the server's inability to call approval/publish tools from model output.

Select relevant archived voice samples explicitly in the workspace and freeze their IDs/excerpts in the activity input. Keep them separate from PRODUCT/offer truth, owner's stated experience and retrieved evidence. No embeddings/search index or automatic voice learning is needed. A voice sample cannot substantiate a factual claim about the current client or offer. Do not expose secrets, arbitrary runtime tools, approval actions or a shell to the model.

**Feedback loop** — Playground: workflow/review fixtures with fake role responses. Experiment: missing question, no angle, no plan, empty untouched manuscript, typing during generation, lost enqueue, actor revoked and malicious “publish now” output. Check: `php artisan test --compact tests/Feature/Publishing/EditorialWorkflowTest.php tests/Feature/Publishing/EditorialReviewTest.php`.

### 3. Evidence and safe public retrieval

Research requests add `plugins: [{id: "web", engine: "exa", mode: "auto", max_results: 5}]`. Official docs retrieved on 2026-09-22 state Exa auto costs $0.007/request for up to ten results, plus model usage. Revalidate before the paid pilot and retain the price snapshot; this is not a forever-fixed price promise. Exa annotations may include `url_citation.content` containing retrieved highlights. Store those with retrieval provenance; annotations without content are only pointers. A retrieved passage is evidence to inspect, not proof a claim is true.

For material missing text, use a bounded public fetch or pause for an owner-supplied passage. Require supporting quotations to exist in stored extracted text; flag absent or contradictory support. Keep recollection, interpretation and externally supported claims distinct. New material uncertainty cannot be hidden as advisory style feedback.

`PublicSourceFetcher` uses a fresh unauthenticated request—not the OpenRouter token-bearing client. Allow HTTP(S) and safe ports only, reject credentials/control characters, canonicalize host/IP forms, resolve and validate every IPv4/IPv6 destination (including mapped IPv4). Deny local, private, loopback, link-local, reserved/documentation/multicast ranges and the application's private/Admin hosts. **Pin the actual connection to the validated address** while preserving original hostname/TLS verification, e.g. supported Guzzle/cURL resolve options; a DNS check followed by an independent lookup is vulnerable to rebinding. Disable automatic redirects and manually revalidate/pin at most three hops. No cookies/auth headers; cap total timeout, decompressed bytes (256 KiB), permitted text content types and extraction size. Denial/oversize/parse failure leaves an unresolved record, not a fabricated summary. Test resolver and transport seams, including redirects and DNS rebinding.

External text is untrusted data. It cannot grant permissions, alter provider routing, trigger publication or override user decisions. For explicitly restricted supplied sources, require processing consent before external transmission and separately assess permission to disclose identifying facts/passages in a release. Do not impose mandatory ZDR or build a policy-management product.

**Feedback loop** — Playground: evidence fixtures and fake resolver/HTTP transport. Experiment: real annotation highlight, missing text, quotation absent from page, conflicting sources, prompt injection, redirect to metadata IP, IPv6-mapped loopback, DNS rebinding and restricted material with AI consent only. Check: `php artisan test --compact tests/Feature/Publishing/EvidenceResearchTest.php`.

### 4. Same-revision reviews and human dispositions

Create facts/argument, voice/narrative and buyer/discovery activities with one revision/hash and cycle ID. Workers may run independently; none writes the manuscript. A failed/missing lens is visibly incomplete and cannot count as a successful review batch. Reconcile after all required lenses complete, deduplicating compatible findings but retaining conflicting recommendations for Nick. Model agreement is not truth.

Findings reference stable block IDs and before-hashes, evidence IDs and bounded proposed patches. Accept/reject/false-positive actions require publishing permission and the current input revision. For applying a batch, validate all selected patches against the same base, reject overlapping/conflicting edits, enforce protected subtrees, construct one candidate document, validate it, then append one `origin=agent-accepted` revision. Never repeatedly mutate the base while pretending later patches still match it. A human can reject advice without losing it from the audit trail.

The explicit Finish review action starts at most one affected-area recheck after the owner's decisions/edits—not after each individual click. Preserve unaffected findings only when their input subtrees/context are still valid; do not blanket-stamp a changed article reviewed. New material contradictions or unresolved issues return to the owner. Further cycles require deliberate restart, increment cycle identity/reset its one recheck entitlement and share the same attempt allowance; no new free $5. If the user edits during review/recheck, mark incompatible output stale and return control rather than auto-launching more cycles.

Wire these actions, source/voice selection, cost display and top-up into the Phase 3 workspace. Remove temporary “not wired” labels for delivered behavior; do not leave only backend service tests while UI buttons remain inert. Poll only safe status data for this first release; a new broadcast architecture is unnecessary.

**Feedback loop** — Playground: review and Livewire fixtures. Experiment: all three no-change reviews, failed lens, duplicates/conflicts, overlapping patches, protected block, stale output, multiple disposition clicks before Finish review, one recheck and explicit cycle restart. Check: `php artisan test --compact tests/Feature/Publishing/EditorialReviewTest.php tests/Feature/Publishing/AdminPublishingEditorTest.php`.

## Configuration

`config/publishing_agents.php` holds enabled=false, the credential from `OPENROUTER_API_KEY`, and two explicit routes (`default`, optional `premium`) with model/provider env names. A fixed role map chooses default except deliberately configured premium critical reviews. Missing premium configuration falls back to default only as a documented pre-call selection, never as a hidden provider retry. No nine-model UI or arbitrary model/plugin registration.

Use bounded code constants for retry count, fetch limits and role output sizes; make a value configurable only where deployment really varies it. Model context/output capability is verified metadata, not an invented env default. Catalog/pricing reads can be cached briefly; each paid request uses a still-valid quote and routing price ceiling. Automated development keeps integration disabled and prevents stray HTTP. Credentials, current model/provider selection and actual paid runs are execution-time owner gates, not guessed values.

## Testing Requirements and Failure Modes

| Test file | Required failures as well as success |
| --- | --- |
| `AgentBudgetTest.php` | Race/duplicate reservation, unknown prices/outcomes, cost-unit conversion, overrun, fallback denial, bounded retries/recovery, authorized/idempotent top-up |
| `EvidenceResearchTest.php` | Real supporting text versus pointer, missing/contradictory quotation, unsafe redirects/DNS rebinding, malicious instructions, consent/publication distinction |
| `EditorialReviewTest.php` | Same-revision lenses, missing lens, no-change result, conflicts/overlap, protected prose, initial empty draft, stale paid output, exhausted recheck and manual restart |
| `AdminPublishingEditorTest.php` | Interview answer, proposal decisions, Finish review, source/voice selection and budget actions actually wired with fakes |

Data-shadow failures (empty output, missing usage, deleted actor, changed revision, no evidence, incomplete lens) must pause visibly, never be coerced into success. Retain enough bounded identifiers/diagnostics for recovery without logging credentials, private source text or full prompts to general logs. Tests validate local authority even if a fake model returns malicious instructions; they do not claim to prove an LLM never hallucinates. The three real pilot pieces remain necessary.

## Validation Commands

```bash
php artisan test --compact tests/Feature/Publishing/AgentBudgetTest.php tests/Feature/Publishing/EvidenceResearchTest.php tests/Feature/Publishing/EditorialReviewTest.php tests/Feature/Publishing/AdminPublishingEditorTest.php
vendor/bin/pest --compact --fail-on-skipped --fail-on-incomplete tests/Feature/Publishing
vendor/bin/pint --dirty --format agent
composer types:check
bun run build
```

All external requests are fake or explicitly blocked. Use the commit skill when available and include `docs/ideation/2026-09-22-agent-publishing-studio/spec-phase-4.md` verbatim in the commit body. No paid evaluation or deployment occurs here.

## Official API References

Retrieved official documentation during specification; revalidate live capability/pricing before paid use:

- https://openrouter.ai/docs/api/api-reference/chat/create-a-chat-completion.md
- https://openrouter.ai/docs/guides/features/plugins/web-search.md
- https://openrouter.ai/docs/guides/routing/provider-selection.md
- https://openrouter.ai/docs/api/api-reference/generations/get-request-&-usage-metadata-for-a-generation.md
- https://openrouter.ai/docs/api/api-reference/models/list-all-models-and-their-properties.md

## Rollout

Disabled until the owner supplies credentials, selects validated model/provider routes and authorizes paid work. Existing queue/scheduler workers must be confirmed operational; missing infrastructure produces an explicit blocked state, not successful “agent completion.” Phase 5 completes publication and public cutover; Phase 6 supplies editorial acceptance.
