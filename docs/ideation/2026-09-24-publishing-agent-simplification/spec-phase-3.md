# Implementation Spec: Publishing Agent Simplification — Phase 3

**Contract**: ./contract.md
**Phase**: Live model trial and recommendation validation
**Estimated effort**: M
**Prerequisite**: Phase 2 — Publishing settings and operational integration
**External preflight**: Owner confirms the intended OpenRouter workspace/key is ready. A live key exists in `.env`; never print or inspect its value in tool output.

## Technical Approach

Validate the researched per-agent recommendations with one bounded, synthetic editorial exercise through the actual application and Laravel AI SDK. Use the existing Pest runner and factories in an explicitly invoked manual test, outside the ordinary Unit/Feature suites. Do not add an application evaluation service, new CLI, package, model-routing framework, cost ledger or spending monitor.

Record actual nonsecret inputs, outputs and requested/returned model identities in `model-validation.json`. This artifact supports an offline completeness check and the owner's editorial judgment; it is not proof of quality by itself. Normal tests, CI, contract verification and artifact generation must never initiate the paid exercise.

The owner approved a small paid trial but may still need to configure their OpenRouter workspace. Confirm readiness before the first live inference; unavailable credentials, account capacity or capabilities are blockers to validation, not reasons to fabricate a passing sample, change the key's limits, or silently substitute mocked output. This phase does not publish or mutate real articles.

## Decisions Considered and Rejected

- **Research plus a live sample** — the owner rejected research/mocks as the only acceptance evidence. Mocks prove integration, not editorial usefulness.
- **Balance quality, latency and price at selection time** — evaluate economical DeepSeek/open-weight and Gemini Flash-class candidates; no per-request financial gate or price refresh is introduced.
- **Per-agent native defaults** — no return to a global default/premium tier even when several roles share one model.
- **OpenRouter Auto Router is an explicit supported choice** — no custom router or claim that Auto guarantees lowest cost or quality.
- **Costs do not control progress** — valid output remains usable without cost metadata. Native usage can exist, but no cost report, reconciliation or comparison dashboard is required.
- **Bounded paid scope** — one initial invocation per existing role, one AskAuthor continuation and one standalone Auto probe; request approval before expanding the exercise or rerunning the whole journey.
- **Isolated synthetic data only** — rejected real article publication, private customer/context transmission and privileged changes to real users or OpenRouter settings.
- **Separate mechanical evidence from human acceptance** — an evidence file and green tests do not certify the writing. The final owner gate remains explicit.
- **Main-only commits** — no new branch, merge commit or push. The owner requested artifacts first and a stop; this phase runs only after later explicit execution.

## Feedback Strategy

**Inner-loop command**: `php artisan test --compact tests/Feature/Publishing/EditorialAgentsTest.php tests/Feature/Publishing/EditorialToolApprovalTest.php`

**Playground**: Offline fake-provider regression tests during preparation; then one explicitly enabled manual Pest journey using the testing application and in-memory SQLite.

**Why**: Repair integration mistakes without paid retries, then spend once on evidence that mocks cannot provide.

A failing paid run is not an invitation to loop until green. Preserve partial evidence, identify the failure, fix offline where possible and ask the owner before additional paid attempts beyond the initial exercise.

## File Changes

### New Files

| File Path | Purpose |
| --- | --- |
| `tests/Manual/PublishingModelTrialTest.php` | One explicit, guarded synthetic live journey; not discovered by default suites. |
| `docs/ideation/2026-09-24-publishing-agent-simplification/model-validation.json` | Actual source-linked recommendation and live output evidence, never placeholder success. |

The manual test is a narrow acceptance fixture, not a shipped evaluation product. Its directory is deliberately outside `phpunit.xml`'s existing `tests/Unit` and `tests/Feature` suites. Bind `Tests\TestCase` inside that file rather than changing the global Pest configuration to discover all manual tests.

### Modified Files

Only modify recommendation/configuration files when the live evidence justifies it; record which were actually changed.

| File Path | Allowed changes |
| --- | --- |
| `app/Ai/Agents/Interviewer.php` | Evidence-backed model/effort correction if needed. |
| `app/Ai/Agents/Researcher.php` | Evidence-backed model/options correction if needed. |
| `app/Ai/Agents/Planner.php` | Evidence-backed recommendation correction if needed. |
| `app/Ai/Agents/Drafter.php` | Evidence-backed prose/document recommendation correction if needed. |
| `app/Ai/Agents/FactReviewer.php` | Evidence-backed factual-review recommendation correction if needed. |
| `app/Ai/Agents/VoiceReviewer.php` | Evidence-backed editorial recommendation correction if needed. |
| `app/Ai/Agents/BuyerReviewer.php` | Evidence-backed recommendation correction if needed. |
| `app/Ai/Agents/ReviewReconciler.php` | Evidence-backed recommendation correction if needed. |
| `app/Ai/Agents/RevisionRechecker.php` | Evidence-backed recommendation correction if needed. |
| `app/Ai/Agents/EditorialAgent.php` | Only the minimum SDK-compatible research/options correction necessary to complete the approved trial. |
| `config/publishing_agents.php` | Keep the tiny allowlist/capability hints consistent with actual selected recommendations. Never add prices or runtime discovery. |
| `tests/Feature/Publishing/EditorialAgentsTest.php` | Regression coverage for justified request-option/default changes. |
| `tests/Feature/Publishing/EvidenceResearchTest.php` | Regression coverage if a minimal research-option correction is required. |
| `docs/ideation/2026-09-24-publishing-agent-simplification/spec-phase-1.md` | Update its dated research rationale if actual selections change; distinguish initial proposal from observed result. |

### Deleted Files

None. Do not delete a failing safety test, old activity or live conversation to make a trial appear successful.

## Implementation Details

### 1. Confirm readiness without revealing or managing secrets

**Pattern to follow**: existing environment-backed `config/ai.php`, the new Publishing settings page and normal provider failure reporting.

Before any inference:

1. Verify `main`, completed prerequisite phases and a clean tree outside intended artifacts.
2. Confirm with the owner that the dedicated OpenRouter key/workspace is ready. Their earlier statement that `.env` has a key does not prove workspace setup is complete. Never fetch or display its raw value; the app loads it normally.
3. Validate only a boolean credential-presence result and the intended nonsecret OpenRouter base URL. Do not call APIs that change keys, workspace membership, routing policies or spend limits.
4. Recheck public model metadata/source documentation for the chosen IDs. This does not require a key or paid inference. An unavailable recommendation needs a justified replacement in the relevant agent, not a hidden fallback to an arbitrary model.
5. Explain the exact initial paid exercise: the nine current roles, one Interviewer continuation and one Auto compatibility probe. These are logical invocations, not a guaranteed dollar cost or invoice cap.
6. If preflight is blocked, report this phase incomplete and stop. Do not weaken acceptance or replace the live records with mocks.

### 2. Prepare the manual test safely

**Pattern to follow**: `tests/Feature/Publishing/PublishingJourneyTest.php`, `EditorialToolApprovalTest.php`, `EvidenceResearchTest.php`, existing factories and `tests/Pest.php`.

Generate the Pest test with Artisan, then place it in `tests/Manual/PublishingModelTrialTest.php`. Do not add that directory to ordinary PHPUnit suites or Composer scripts.

Require an explicit, test-only process opt-in `PUBLISHING_LIVE_TRIAL=1`. If absent, the manual test must skip before touching a provider. This is a safeguard for a paid acceptance fixture, not a new application/deployment environment variable: do not put it in `.env.example`, production config or settings. The later command is:

```bash
PUBLISHING_LIVE_TRIAL=1 vendor/bin/pest tests/Manual/PublishingModelTrialTest.php --compact
```

Prepare and verify the test without that opt-in first. Do not run the enabled command during artifact generation, normal CI or automatic contract verification.

Before any migrations/factories execute, assert the booted application is in `testing`, the default connection is SQLite and its database is `:memory:`. Refuse cached production configuration, non-memory databases and unexpected connection URLs rather than trusting environment labels alone. Do not apply `RefreshDatabase` before these safety assertions; explicitly migrate the confirmed memory connection afterward. The file can bind the existing Laravel `Tests\TestCase` and perform its own isolated setup.

Use fake queues/mail/notifications/broadcasts and synthetic users assigned roles through the existing catalog in this memory database. Real background workers and the scheduler must never see these activities. Invoke controlled actions/jobs inline; do not dispatch a chain of uncontrolled real workers. Do not invoke real publication/scheduling actions. The ordinary PHPUnit configuration already isolates most services; assert rather than assume critical DB isolation.

No paid provider fake is allowed in this fixture. Keep accidental outbound traffic constrained: the installed Laravel HTTP factory supports `preventStrayRequests()` and `allowStrayRequests(only: ...)`; allow only the intended OpenRouter API and the specific public evidence hosts needed for the sample. Preserve the application's public-source SSRF protections. Do not allow arbitrary internal/private hosts or print request headers on failure.

The normal feature tests retain provider HTTP fakes and stray-request prevention. Do not change them to accommodate the live fixture.

**Feedback loop**:
- Playground: manual test without opt-in, then the normal fake-provider suites.
- Experiment: missing opt-in, wrong environment, file-backed DB, missing credentials and valid isolated setup. Unsafe cases must fail/skip before migrations or inference.
- Check command: `vendor/bin/pest tests/Manual/PublishingModelTrialTest.php --compact` — confirms the default path skips without paid calls.

### 3. One bounded synthetic editorial journey

Use a clearly fictional operations scenario suitable for the owner's audience, such as improving a small team's intake/follow-up workflow. Supply only synthetic business details and a synthetic voice brief; do not pull the owner's real manuscripts, private voice samples, customer records or production evidence into prompts. External claims can use a small set of public official documentation passages that the researcher can retrieve and quote.

Include a deliberately missing author fact to elicit AskAuthor. Answer it through the actual authorized application action and native SDK Decision mechanism, not by bypassing the tool with a direct second chat request. Explicitly simulate the owner approving the synthetic angle and plan; no agent self-approval. Do not send a release or make a public article.

Cover:

1. Interviewer asks for missing information and proposes a usable brief/angle without fabricating owner intent.
2. One authorized AskAuthor continuation consumes the answer with stable conversation/model identity.
3. Researcher retrieves public evidence and returns checkable quotations and explicit gaps.
4. Planner connects supplied facts/evidence to an argument and outline.
5. Drafter returns a valid canonical Tiptap document with appropriate voice and protected structure.
6. FactReviewer catches a seeded unsupported claim or contradiction in a synthetic review revision.
7. VoiceReviewer catches a seeded violation of the supplied synthetic voice guidance.
8. BuyerReviewer catches a seeded audience/objection mismatch without inventing buyer facts.
9. ReviewReconciler groups overlapping findings without dropping blocking concerns.
10. RevisionRechecker distinguishes a deliberately fixed issue from one left unresolved.
11. One standalone Auto Router probe uses a representative structured editorial task and records both `openrouter/auto` and the concrete returned model. Native approval/Auto continuity is also covered offline; if Auto becomes the Interviewer's recommendation, its real continuation must use the selected concrete model.

Seed controlled review defects in memory; the trial need not rely on the drafter accidentally producing mistakes. Keep native step bounds and disable extra application retries for this controlled paid exercise. SDK/OpenRouter behavior may involve internal work; do not present the invocation count as a financial guarantee.

Record each attempted result once as it happens, including failed/incomplete records, so a later failure does not erase useful evidence. A failed stage stops dependent live stages. Do not fabricate successful rows for skipped roles or mark a mocked retry as a live observation. Do not automatically rerun the journey to tune prompts or models. Present the reason and obtain approval for any additional paid test scope.

The research tool currently uses the OpenRouter Exa plugin. Current public documentation prefers a server tool, but broad search modernization is excluded. First test the existing SDK-compatible integration. If it is actually rejected/deprecated beyond usability, make the smallest verified request-option adaptation that preserves evidence grounding and add an offline regression test; do not replace the agent/tool runtime or upgrade dependencies silently.

**Feedback loop**:
- Playground: actual opt-in manual journey in the isolated test application.
- Experiment: the single scripted sample and seeded defects above, with actual requested/returned model identity and output recorded.
- Check command: the explicitly authorized `PUBLISHING_LIVE_TRIAL=1 vendor/bin/pest tests/Manual/PublishingModelTrialTest.php --compact` command, once after readiness confirmation. A failing live run stops; it is not an ordinary retry loop.

### 4. Evidence and research record

Create `model-validation.json` only from actual work. It must contain no API key, headers, private prompt content or account identifiers. Basic schema:

```json
{
  "schema_version": 1,
  "recorded_at": "ISO-8601 timestamp of the evidence update",
  "recommendations": [
    {
      "agent": "Interviewer",
      "model": "exact recommended model ID",
      "rationale": "Role fit, alternatives, source claims versus observations, and price/latency tradeoff.",
      "sources": ["official source URL"]
    }
  ],
  "live_trials": [
    {
      "scenario": "initial",
      "agent": "Interviewer",
      "requested_model": "requested exact model ID or openrouter/auto",
      "returned_model": "concrete provider-reported model ID",
      "input": {"synthetic": true},
      "output": {"actual": "returned editorial data, never fabricated"},
      "status": "completed"
    }
  ]
}
```

The example is a shape, not evidence to copy as a passing record. Use `scenario: initial` for the role samples, `ask_author_continuation` for the Interviewer's native resume and `auto_probe` for the separate Auto trial. Include every concrete role using its selected recommended model, the native continuation and the Auto probe. Old evidence for a different model cannot certify a newly changed recommendation. Failed/unattempted records must retain their actual status. Optional useful fields include `observed_at`, `duration_ms`, `generation_id`, `error_category`, `claims_from_sources`, `live_observations` and `owner_acceptance`; do not record secrets or build a new cost schema.

Each recommendation needs current source links, the task-specific reason, alternatives considered and a distinction between advertised capabilities and observed output. Public metadata can establish identifiers and advertised parameter support; it does not prove latency or quality. Measure observed latency during the sample if discussing it as a result. Static input/output prices may be cited as dated research, not reported as what the article necessarily cost.

Primary sources to consult or refresh (the public model catalog was checked during planning):

- https://laravel.com/framework/docs/ai-sdk#agent-configuration
- https://openrouter.ai/api/v1/models
- https://openrouter.ai/docs/guides/routing/routers/auto-router
- https://openrouter.ai/docs/guides/routing/provider-selection
- https://openrouter.ai/docs/features/structured-outputs
- https://openrouter.ai/docs/guides/features/tool-calling
- https://openrouter.ai/docs/guides/best-practices/reasoning-tokens
- https://openrouter.ai/docs/guides/features/server-tools/web-search

Refresh time-sensitive claims when executing, not on each production request. If the paid evidence motivates a recommendation change, make that small code/test change, update Phase 1's rationale and describe any additional validation still needed. Do not silently spend on comparative sweeps.

**Feedback loop**:
- Playground: JSON parsing and the contract's offline evidence-completeness command.
- Experiment: missing role, empty output, failed Auto probe or placeholder result must fail completeness; actual completed records satisfy structure but not owner quality judgment.
- Check command: use the exact `python3 -c ...` evidence check in `contract-data.json`; it performs no network call.

### 5. Owner acceptance and truthful completion

Present the actual sample outputs and a concise per-role recommendation rationale. Ask the owner to accept or identify concrete failures in interviewing, evidence, prose, factual review, voice or audience fit. The final contract node is an explicit human gate. Do not mark that gate passed merely because tests or the offline completeness command passed.

If the owner is not available, commit the validated code/evidence with acceptance explicitly pending and stop. If the workspace or a provider blocked the paid trial, preserve partial evidence and report Phase 3 incomplete rather than inventing a pass. Do not rerun the full exercise simply to force a successful orchestration status.

## Testing Requirements

| Check | Expected result |
| --- | --- |
| Manual test without opt-in | No provider request; clearly skipped before paid work. |
| Unsafe environment/DB | Refuse before migration, model creation or inference. |
| Ordinary Unit/Feature suites | Exclude manual trial; retain HTTP fakes and never spend. |
| Native question continuation | Real AskAuthor pause and authorized resume, same execution identity, no implicit stage approval. |
| Seeded review cases | Actual reviewers expose the seeded issues; reconciler/rechecker retain unresolved blockers. |
| Auto probe | Valid structured output and concrete model reported; no promise of optimal price/quality. |
| Evidence check | Missing/failed roles or missing Auto evidence cannot pass completeness. |
| Recommendation changes | Focused offline agent/evidence tests pass after every code adjustment. |

## Failure Modes

| Component | Failure | Trigger | Impact | Mitigation |
| --- | --- | --- | --- | --- |
| Workspace | Key exists but workspace is not ready | First live request | Provider rejection | Owner readiness gate; no remote account mutation. |
| Manual fixture | Testing label points at real DB | Cached/mismatched config | Real data modified | Assert actual memory DB before migration/factory. |
| Invocation | Manual test enters CI | Broader test discovery | Unplanned paid calls | Outside default suites plus explicit test-only opt-in. |
| Model | Advertised capability differs by provider | Required tool/structured output request | Integration failure | require_parameters, source review and bounded live sample. |
| Evidence | Success recorded despite failure | Partial run or copied placeholder | False acceptance | Record actual attempts/statuses; retain human gate. |
| Review | Seeded issue missed | Inadequate chosen model/output | Editorial quality inadequate | Show output honestly; authorize any further paid comparison. |
| Retry | Repeated tuning spends on extra calls | Automatic retry-until-green loop | Approved scope exceeded | Stop on failure; no evaluation loop. |
| Search | Existing plugin no longer works | Provider removes/rejects it | Research cannot complete | Minimal verified correction and offline regression. |

## Validation Commands

Offline/default-safe checks:

```bash
vendor/bin/pest tests/Manual/PublishingModelTrialTest.php --compact
php artisan test --compact tests/Feature/Publishing tests/Feature/Authorization
vendor/bin/pint --dirty --format agent
composer types:check
bun test resources/js/admin/publishing
bun run build
git diff --check
```

After the preflight and only for the approved paid exercise:

```bash
PUBLISHING_LIVE_TRIAL=1 vendor/bin/pest tests/Manual/PublishingModelTrialTest.php --compact
```

After actual evidence exists, run the contract verifier using the installed ideation script:

```bash
node /Users/birdcar/.pi/agent/git/github.com/nicknisi/ideation/scripts/verify.mjs docs/ideation/2026-09-24-publishing-agent-simplification/contract-data.json
```

Resolve that plugin path for another machine. The verifier reads saved evidence and runs offline checks; it must not invoke the live trial. Its judgment lines remain pending until a human reviews them.

## Rollout / Handoff

- Commit scoped changes and nonsecret evidence directly on `main`; no push, production migration, deployment or publication.
- Record whether the live trial completed, which models actually ran, and whether owner acceptance is complete or pending.
- Do not delete or alter real paused activities, real settings, users or OpenRouter account limits as a side effect of a test.
- Cost display and ongoing model optimization remain deferred. This phase validates starting recommendations, not a permanent guarantee of provider behavior or editorial quality.
