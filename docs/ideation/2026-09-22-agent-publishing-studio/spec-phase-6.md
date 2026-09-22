# Acceptance Gate: Agent Publishing Studio — Phase 6

**Contract**: ./contract.md\
**Kind**: Human gate — not a build/commit phase\
**Prerequisite**: Release delivery and public cutover
**Owner**: Nick

This file is an executable review checklist for the owner and assisting agent, not authorization to publish, spend money, grant permissions or deploy. Do not dispatch it as an unattended implementation phase, report it as a no-op, create a phase-completion commit, or mark it passed from automated verification. The five implementation phases can finish while this gate remains pending.

## Decisions Considered and Rejected

- **Three representative pieces** — rejected a one-piece pilot or invented productivity baseline.
- **Owner editorial judgment** — rejected model agreement, code review or green tests as evidence that the voice and reader usefulness are right.
- **Facts and safety block release** — rejected overriding genuinely unresolved material factual, invented-detail, confidentiality/rights or integrity problems. Evidence, removal and reasoned false-positive resolution remain available.
- **Desktop/mobile task review** — rejected markup assertions as proof of the writing interaction or preserving work after failures.
- **Full original archive** — rejected on-demand or read-only legacy migration; all baseline essays and their meaningful figures must be present.
- **$5 automatic allowance per attempt** — rejected automatic resets on drafts/retries. Any top-up is a new explicit decision.
- **No mandatory ZDR policy** — rejected forced zero-retention routes. AI-use disclosure still does not confer permission to publish client details.
- **Reusable Admin app shell** — rejected tying all Admin navigation/layout to publishing; future modules have a stable place without speculative features now.

## Feedback Strategy

**Mechanical preflight**: `node /Users/birdcar/.pi/agent/git/github.com/nicknisi/ideation/scripts/verify.mjs docs/ideation/2026-09-22-agent-publishing-studio/contract-data.json`\
**Playground**: Confirmed local/staging application with Nick's explicitly provisioned account and operator-authorized configuration.
**Human loop**: Run one named scenario, record observed result and revision/release IDs, fix a demonstrated defect, rerun that scenario. Passing the command above does not count the judgment criteria as passed.

## File Changes

No application changes are authorized by this gate. Record actual human outcomes, once obtained, in `docs/ideation/2026-09-22-agent-publishing-studio/acceptance.json` with check ID, pending/accepted/rejected status, actor, timestamp, article/revision/release IDs and evidence/notes. Do not prefill approvals, put credentials in it, or record confidential source excerpts. Fixes discovered here return to the appropriate implementation phase and its focused tests.

## 1. Operator Prerequisites

- Confirm that the target is local/staging, not production. Resolve the real scheme/host/port with the project URL tools; do not assume the sandbox address or hard-coded Admin production domain is the local app.
- Confirm migrations and the complete importer dry-run/parity report. Ask for authorization before writing the real local archive records. Preserve originals; no `migrate:fresh` against an existing application database.
- Run `php artisan authorization:sync --no-interaction`. Ask Nick to identify his existing account. Obtain approval before assigning the existing `admin.access` and new `publishing.author` roles. No guessed email, newly created account, direct permission grants or automatic boot-time access.
- Verify login/logout and, if enabled on that account, its existing two-factor challenge. Confirm admission-only users cannot access editorial content. Admin routing is standard Laravel; marketing stays on Folio.
- Select the database public reader only after parity succeeds. Confirm queue/scheduler availability using the actual environment's existing workers; do not provision or restart unrelated services.
- Have Nick supply the OpenRouter credential through the environment's secret mechanism, not this document/chat artifacts. Validate configured default and optional escalation model/provider IDs, required capabilities and current prices. Missing/unpriced configuration is a pause, not a reason to make real experimental calls secretly.
- Obtain explicit authorization for the three real editorial pilot attempts and their automatic $5 allowances. Disclose the OpenRouter/search-provider data path. Do not automatically approve top-ups or assume the app's allowance guarantees a provider invoice cap.

**Check loop**: Missing account/role, unavailable provider and absent queue each produce a clear blocked state; a fully configured environment permits deliberate Develop idea. Confirm no billable call occurs merely from opening the dashboard or saving an idea for later.

## 2. Archive Fidelity

Review all original archive identities and representative rendered cases for every special-block type. Compare against original source and file-backed baseline, not only against a second output from the new renderer.

- Preserve prose, titles, original publication dates, slugs/URLs and link destinations for the full baseline corpus (ten essays at project start).
- Check note/callout titles and content, author mention links, chart labels/values/captions, diagram labels/relationships/meaning and code/list formatting.
- Inspect desktop and mobile, keyboard scrolling where needed, JavaScript disabled, reduced motion and print. No clipped figure meaning or unreadable fallback.
- Verify Writing, direct article URLs, RSS, sitemap and canonical metadata. No draft-only content, private evidence, editorial notes or working titles appear.
- Repeat import: existing CMS edits and live releases must remain unchanged; source files still match the pre-project Git baseline.

**Check command**: `php artisan test --compact tests/Feature/Publishing/ArchiveMigrationTest.php tests/Feature/MarketingSiteTest.php tests/Feature/MarketingDiagramTest.php tests/Feature/MarketingDiscoveryTest.php`.

Mechanical parity is necessary but does not sign off the rendered-meaning judgment.

## 3. Admin Task Journey

Nick performs the journey at a desktop width and a real narrow mobile viewport (not a browser-window resize that fails to change the CSS viewport). Inspect keyboard focus, labels, save/retry messages, modal escape behavior and control accessibility.

1. Capture an idea in Flux Composer. Save for later: remains passive. Develop idea: starts the interview/brief and makes the current activity visible.
2. Answer a question; leave required information missing and verify the attempt pauses. Approve an angle, then inspect research/challenge and the narrative/visual plan. No full draft precedes plan approval.
3. Approve the plan; inspect the generated draft proposal. Edit paragraphs, protect a passage, edit a note/callout, change chart values/caption and author or change a supported diagram source. Invalid values/source fail without damaging the last valid document.
4. Save and perform a full page reload. Confirm exact canonical document/metadata retention and block editability. Verify no figure is flattened or dropped by stock editor HTML serialization.
5. Trigger a failed save and retry. Open the article in another tab and submit a stale edit. Unsaved text remains recoverable; a conflict never silently overwrites another save. Local recovery is clearly distinguished from server-saved state.
6. Run three reviews on the same revision, inspect conflicting recommendations and choose accept/reject/resolve. A protected passage cannot be changed by an agent patch until Nick unlocks it. Finish decisions to request one affected-area recheck; no endless automatic loop starts.
7. Prepare an exact release, review blockers/advisory findings and approve the package. Edit it afterward and observe approval/schedule invalidation. Re-approve a deliberate valid package.
8. In the authorized local/staging environment, publish or schedule. Verify the correct live version; repeat delivery is harmless. Begin a revision of an already published article and verify the existing version remains public.
9. Confirm shared Admin navigation/layout are usable without implying that the publishing module owns future Admin functionality. Published remains a separate archive rather than replacing the Ideas/Active writing home.

**Check command**: `vendor/bin/pest --compact --fail-on-skipped --fail-on-incomplete tests/Feature/Publishing`.

## 4. Editorial Pilot

Use three real owner-chosen topics, not invented customer case studies:

- An opinion-led article expressing Nick's actual perspective.
- A practical explanation useful to the approved reader.
- An evidence-heavy article requiring real supporting sources and a challenge to its main claims.

Each piece passes its own angle, plan and exact-release approvals. Select relevant archive samples for voice separately from `PRODUCT.md` business truth and source evidence. Resolve every material factual/rights concern; do not invent experience, results, permissions or buyer demand. A draft can be rejected without manufacturing success to complete the contract.

For each piece, record topic/type, approved brief/angle, initial draft revision, final accepted revision/release, selected voice samples, known claim resolutions, owner judgment and observed costs. Record hands-on editing minutes supplied by Nick and retained first-draft prose with its measurement method (an explicitly labeled owner estimate is acceptable; do not present it as calculated). If a token-diff calculation is used, name the baseline and method and keep it an observation, not a quality score. Record model/tool costs from the usage ledger, including failed calls and retries; unresolved charges stay marked unknown.

Nick explicitly accepts or rejects viewpoint, rhythm, specificity, rhetorical restraint and reader usefulness for each manuscript. There is no invented time-saving baseline or requirement to show a conversion lift. Qualified inquiries are a later business measure, not evidence already obtained.

## Failure Modes and Disposition

| Failure | Required disposition |
| --- | --- |
| Missing credentials, roles, imports or scheduler | Keep the gate pending; obtain the specific operator authorization/configuration |
| Editor loses or overwrites prose/figures | Reject the journey; fix and repeat the failed scenario before approval |
| Public draft/private-source leak | Block release immediately; restore safe reader/live pointer with owner authorization and add regression coverage |
| Budget exhausted or unconfirmed cost | Pause; Nick chooses whether to authorize more, reconcile or stop |
| Poor voice or unsupported material claim | Reject/revise that pilot piece; do not relabel it a successful acceptance |
| Automated checks pass, owner unavailable | Report implementation verified but owner acceptance pending |
| Request to deploy | Separate explicit deployment approval and environment procedure; this checklist is not permission |

## Completion Rule

All automated criteria pass and Nick explicitly accepts archive fidelity, the desktop/mobile task journey and all three pilot pieces. Record real evidence and actor/timestamps. Until then, report the outstanding judgment checks plainly. Passing this gate still does not authorize production deployment, distribution or deferred features.
