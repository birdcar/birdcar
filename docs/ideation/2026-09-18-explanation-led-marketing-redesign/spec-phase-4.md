# Implementation Spec: Explanation-led marketing redesign — Phase 4

**Contract**: ./contract.md
**Source of truth**: ./contract-data.json
**Scope**: Full
**Estimated effort**: M
**Prerequisite**: Phases 1–3 complete, including the actual phase-1 owner approval.

## Technical Approach

Verify the integrated public site and make only fixes required by the approved contract. Distinguish four kinds of evidence: HTTP/DOM contracts, mocked application behavior, real rendered/browser behavior, and post-launch business outcomes. No test suite proves all four.

Use the existing PostHog and Cal integrations. PostHog JS is installed (`1.433.10` at planning); Cal is an externally loaded script, not a version-pinned local package. `booking_embed_opened` currently fires on `linkReady`. Characterize what actually happens before treating that event as an opened-calendar metric. Controlled fakes prove application handling; they do not establish the external library's behavior. No broad analytics rebuild, new dashboard, production booking, dependency installation, or account change is authorized.

Complete Impeccable's independent finish review and document the built world afterward. The owner is the only required human approver. No unattended runner may bypass the owner gate or claim that planned, simulated, or unavailable checks passed.

## Decisions Considered and Rejected

- Measure demand support after release with existing PostHog and real conversations; reject invented conversion uplift or success claims without a baseline.
- Owner structured review at launch plus post-launch observation; reject recruiting three nontechnical readers.
- Independent agent QA remains required by Impeccable; it is not additional human research.
- Treat `linkReady` semantics as unverified until characterized; reject equating a suggestive event name with actual visitor behavior.
- Keep the shared visual-system scope and local print specimen; reject PDF publishing/templates, new analytics infrastructure, and content campaigns.
- Reconcile authoritative records instead of appending contradictory guidance; distinguish full rendered review from markup/build checks.
- Full scope includes both concrete diagrams. No generic diagram engine or open SVG ingestion.
- This phase is not permission to deploy, generate real bookings, or send test data to production services.

## Feedback Strategy

**Inner-loop command**: `bun test resources/js/analytics.test.js resources/js/booking.test.js`

**Playground**: Existing Bun test runner with controlled DOM/Cal/PostHog fakes, the running local application, browser request interception, and the print specimen.

**Why**: Focused tests establish event/property handling and fallback logic cheaply; browser checks establish the real integration and rendered state, with external uncertainty reported rather than concealed.

## File Changes

### New Files

| File Path | Purpose |
| --- | --- |
| `resources/js/analytics.test.js` | Existing enable/disable and placement forwarding behavior |
| `resources/js/booking.test.js` | Existing loader/fallback/callback handling with controlled fakes |

### Modified Files

Modify application files only when the corresponding check finds a real defect; this list declares potential review-fix ownership, not a mandate to rewrite them.

| File Path | Changes |
| --- | --- |
| `resources/js/analytics.js` | Narrow correctness fixes only if tests reveal a defect |
| `resources/js/booking.js` | Minimal correction or clear semantic treatment of existing callbacks; preserve compatibility |
| `resources/js/interactions.js` | Lifecycle/menu fixes identified by integrated review |
| `resources/js/interactions.test.js` | Regression coverage for any interaction fix |
| `resources/js/diagrams.js` | Bounded fixes to explanatory animation |
| `resources/js/diagrams.test.js` | Regression coverage for motion fixes |
| `resources/css/app.css` | Final token corrections if required |
| `resources/css/marketing.css` | Material responsive/accessibility/print fixes and removal of discarded selectors |
| `resources/views/components/marketing/layout.blade.php` | Material navigation, focus, or loading fixes |
| `resources/views/components/marketing/booking-link.blade.php` | Material fallback/placement fixes |
| `resources/views/components/marketing/assessment-invitation.blade.php` | Final offer presentation corrections |
| `resources/views/components/marketing/walkthrough-diagram.blade.php` | Reviewer-requested explanatory fixes |
| `resources/views/components/marketing/reporting-diagram.blade.php` | Reviewer-requested explanatory fixes |
| `resources/views/components/marketing/article-diagram.blade.php` | Reviewer-requested accessibility/static fixes |
| `resources/views/components/marketing/article-chart.blade.php` | Data-preserving reading/print fixes |
| `resources/views/components/marketing/article-note.blade.php` | Reading/print corrections |
| `resources/views/pages/index.blade.php` | Material reviewer fixes only |
| `resources/views/pages/walkthrough.blade.php` | Material reviewer fixes only |
| `resources/views/pages/work.blade.php` | Material reviewer fixes only |
| `resources/views/pages/writing/index.blade.php` | Material reviewer fixes only |
| `resources/views/pages/writing/[slug].blade.php` | Material reviewer fixes only |
| `resources/views/figure-specimen.blade.php` | Final screen/print proof corrections |
| `tests/Feature/MarketingSiteTest.php` | Regression checks for actual fixes |
| `tests/Feature/MarketingAnalyticsTest.php` | Preserved analytics emission/placement assertions |
| `tests/Feature/MarketingDiagramTest.php` | Regression checks for actual figure/specimen fixes |
| `tests/Unit/MarketingFontsTest.php` | Actual shipped font assertions |
| `tests/Unit/MarketingLayoutTest.php` | Retain reviewed layout intent |
| `PRODUCT.md` | Confirm product truth, new visual preferences, and unchanged offer |
| `DESIGN.md` | Document the final reviewed system, not the proposed board |
| `.ai/rules/resources.md` | Reconcile superseded marketing visual guidance using the required shared-rule tooling |
| `.impeccable/surfaces/resources-views-pages-index-blade-php.md` | Final homepage strategy |
| `.impeccable/surfaces/resources-views-pages-walkthrough-blade-php.md` | Final offer strategy |
| `.impeccable/surfaces/resources-views-pages-work-blade-php.md` | Final work-story strategy |
| `.impeccable/surfaces/resources-views-pages-writing-index-blade-php.md` | Final archive strategy |
| `.impeccable/surfaces/resources-views-pages-writing-slug-blade-php.md` | Final reading strategy |
| `.impeccable/surfaces/resources-views-welcome-blade-php.md` | Mark legacy target guidance superseded so it cannot steer later work |
| `docs/ideation/2026-09-18-explanation-led-marketing-redesign/acceptance.md` | Review results, owner decisions, event semantics, and post-launch handoff |

### Deleted Files

| File Path | Reason |
| --- | --- |
| `public/images/future-horizon.png` | Discarded visual-world asset; delete only after confirming no shipping reference remains |
| `public/images/future-horizon.json` | Paired provenance for the retired asset; remove with it |

Do not delete unrelated pre-existing assets or tests. If a discovered required fix is outside this file inventory, update the affected-file record before execution/commit rather than hiding scope expansion.

## Implementation Details

### 1. Event and booking verification

Patterns: `resources/js/analytics.js`, `resources/js/booking.js`, current Bun interaction tests, and `MarketingAnalyticsTest.php`.

Verify these existing events without adding an event taxonomy:

- `booking_cta_clicked`: preserve placement values and page path.
- `booking_embed_opened`: currently `linkReady`; document whether observed behavior is readiness, once-per-load, or an actual open signal. Do not use it as an opened-calendar funnel step unless actual-open semantics are observed. Existing-event compatibility is preferable to silent renaming.
- `booking_completed`: `bookingSuccessfulV2`; forward only booking UID, event type ID, start time, status, and mode as currently allowed. Do not spread the full payload or capture attendee text, email, diagram content, or form fields.
- `booking_fallback_clicked`: preserve direct-link measurement and page path.

Test initialization without configuration, disabled/opted-out behavior, enabled placement events, modifier-click/navigation fallback, missing custom element, modal/inline modes, and missing optional payload fields. Prefer dependency fakes over architectural changes made only for testing. No live PostHog writes from tests.

Retrieve current official Cal embed event documentation during implementation and record source/retrieval date in `acceptance.md`. Cal may change independently of package locks. Require a controlled in-browser `bookingSuccessfulV2` callback exercise with outbound analytics locally intercepted; distinguish it from a real booking. Also inspect actual calendar readiness/open behavior where the external embed is reachable. If it is not reachable, record that limitation and do not mark real integration evidence passed. A full production test booking requires separate owner permission.

**Feedback loop**: Exercise each callback with expected and incomplete payloads, each booking mode, and both loaded/failed script paths. Run the inner-loop command and `php artisan test --compact tests/Feature/MarketingAnalyticsTest.php`. Compare fake behavior with observed/documented Cal behavior; simulated agreement alone is not enough to invent a remote guarantee.

### 2. Integrated rendered review

Check the homepage, Walkthrough, work, Writing index, and representative articles at 390/768/1440px and representative 200% zoom. Capture real rendered output after fonts and entrance motion settle. Inspect keyboard traversal/focus, no-JavaScript fallbacks, reduced motion including runtime preference change, cold loading, repeated figure instances, long headings, real chart values, mobile menu dismissal, and print.

Use a bounded capture/fix process: batch target states, fix material findings together, and confirm. Run the detector once for the changed UI targets if no hook is active. Obtain an independent Impeccable finish review with the actual screenshots, direction contract, findings, and static/print examples. Follow its real disposition. A missing browser, blank screenshot, unavailable embed, or skipped print inspection is missing evidence, not a pass.

The print comparison includes both authored diagrams and the representative article/chart content, at Letter/A4 and in grayscale. Keep meaningful data/labels readable without color or motion. Review saved PDFs, not just print CSS source.

**Feedback loop**: Start the existing dev server with analytics disabled; use the named browser-state matrix and run the contract's six mechanical commands after material fixes. Stop chasing extra polish beyond the agreed review scope.

### 3. Records and post-launch measurement

After the last material correction, use Impeccable's documenter to make DESIGN.md describe what actually shipped. Reconcile PRODUCT.md, all relevant surface briefs, and `.ai/rules/resources.md`; the old Future, in person palette/headline mandates must not remain unqualified authority. Use Boost `record-rule` for durable shared rules. If the tool cannot retire a conflicting entry, escalate that limitation instead of silently claiming the records are consistent. Avoid changing settled product positioning, public proof permissions, or the offer while recording the new visual system.

Append a compact measurement handoff to `acceptance.md`:

1. Actual event names, property allowlist, tested semantics, and which checks were simulated versus live.
2. A release-cutover field for the owner to fill when deployment is separately authorized; no fake release date.
3. Existing PostHog views/funnels the owner can use: public visits and CTA clicks by placement/page, CTA-to-completed-booking progression, and completed bookings over comparable windows. Use available referral/source context, not fabricated attribution for offline introductions. Do not treat fallback-link clicks as completed bookings.
4. Inspect the embed-readiness event separately unless actual-open behavior is confirmed. Preserve current insights by documenting any narrowly required correction.
5. Record traffic and conversion denominators, sample sizes, and changes in outreach/source mix. Low-volume before/after comparisons are descriptive, not proof of redesign causality. No A/B test program or promised percentage uplift.
6. The owner assesses whether booked conversations are qualified and notes misunderstandings from real prospects. PostHog alone cannot infer either quality or comprehension.

No remote dashboards/accounts are modified. Prospective measurement is a handoff, not a false assertion of results at build acceptance.

## Failure Modes

| Component | Failure / trigger | Impact | Mitigation |
| --- | --- | --- | --- |
| Analytics funnel | `linkReady` counted as visitor open | Misleading conversion rate | Document observed meaning; omit from open funnel unless verified |
| Tests | Fake callback mistaken for external verification | False integration confidence | Separate observed, documented, and simulated evidence |
| Booking payload | Full event object forwarded | Unnecessary personal data captured | Existing property allowlist; negative assertions |
| Browser evidence | Animation timing or unavailable browser | Missing content hidden by a false pass | Valid settled captures; report blocked checks |
| Documentation | Old palette/type rules survive | Future agents restore discarded design | Reconcile shared authorities after final review |
| Measurement | Source mix or tiny sample changes | False redesign attribution | Report denominators and qualify comparisons |

## Validation Commands

```bash
php artisan test --compact tests/Feature/MarketingSiteTest.php tests/Feature/MarketingDiscoveryTest.php tests/Feature/MarketingAnalyticsTest.php tests/Feature/MarketingDiagramTest.php tests/Unit/MarketingFontsTest.php tests/Unit/MarketingLayoutTest.php
bun test resources/js/interactions.test.js resources/js/diagrams.test.js resources/js/analytics.test.js resources/js/booking.test.js
vendor/bin/pint --dirty --format agent
composer types:check
bun run build
```

After focused feature tests pass, ask the owner to run the complete suite with `php artisan test --compact`. Do not describe that full suite as passed unless it actually ran and passed.

## Exit and Rollout

**STOP for owner launch acceptance.** Present the final screenshots, actual review verdict, print specimens, test results, and any unavailable evidence. Have the owner answer the contract's comprehension questions and approve or list corrections. Record their real decision in `acceptance.md`; never auto-approve it.

No deployment is included. Keep the source history linear and follow the project's commit rules only when execution/committing is authorized. Use the commit skill for any eventual commits. The subsequent operational step is authorized deployment and observation through the existing PostHog setup—not another automatic feature phase.
