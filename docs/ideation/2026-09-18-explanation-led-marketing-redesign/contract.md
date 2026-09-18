# Explanation-led marketing redesign Contract

**Created**: 2026-09-18
**Readiness**: All 5 gates ready
**Status**: Approved
**Approval**: Interactive review
**Supersedes**: None

## Problem Statement

Birdcar is demand-constrained. The first audience for this redesign is a nontechnical owner or operational leader who encountered Birdcar through outreach, a conversation, or a referral and now needs to decide whether a Walkthrough is useful. The site must make expertise understandable and the next step credible, rather than merely looking more technical. There is no established conversion baseline here proving that the existing design causes lost bookings.

The current public site has an established offer and functional booking, article, discovery, and analytics paths, but its visual world is being replaced. The owner selected The clear argument: a recognizable business problem, a plain-language explanation, concrete work, and an accessible invitation. Keep the Alkaline wordmark; other typography, composition, imagery, palette, and motion may change.

Diagrams, animated SVG, and infographics should explain relationships and decisions for nontechnical audiences, particularly in written content and eventual PDF handouts. The first diagram explains what the Walkthrough gives someone: a conversation about their real work becomes findings, recommendations, and Where I'd start in a report they keep. Animation must enhance an explanation that is already understandable in static form. Print may use a different layout or several stages, not a frozen, incomplete animation frame.

This engagement produces planning artifacts only. Implementation, deployment, external account changes, and production bookings require a separate execution decision. At intake, resources/css/marketing.css and tests/Unit/MarketingLayoutTest.php contained owner changes. Preserve their intent, recheck the current working tree at execution entry, and reconcile rather than overwrite; their commit status may have changed during planning.

## Goals

1. At launch, the owner's structured review confirms that a referred nontechnical prospect can identify the business problems addressed, what the Walkthrough gives them, its roughly one-hour/free/pitch-free nature, the three-business-day report turnaround, and their freedom not to purchase implementation; a booking invitation is readily available without completing an animation or interactive lesson.
2. Replace the public site's five page types with The clear argument visual world: a cyan-led palette (#B7EDF1), dark blue-green text (#102A33), a limited yellow emphasis (#F7CB58), white reading surfaces, large upright sans-serif headings, annotated figures, and the single Alkaline header wordmark. The diagram must support the business argument rather than make Birdcar look like a report-selling product.
3. Deliver the lead Walkthrough explanation and a second Craft & Communicate reporting figure in web, article-figure, reduced-motion/no-JavaScript, and representative print forms, with equivalent meaning and plain-language captions. Preserve the original essay archive's text, metadata, links, chart values, and figure meaning; do not rewrite archived essays to fit the new marketing.
4. Preserve and verify booking, mobile navigation, public discovery, and existing PostHog contracts. Supply a post-launch measurement handoff for CTA-to-booking progression and completed bookings, using the existing implementation and owner assessment of lead quality; do not promise conversion uplift or treat low-volume before/after observations as causal evidence.

## Success Criteria

- [ ] The owner can answer the six offer/comprehension questions in goal 1 from the homepage and Walkthrough page, and identifies no diagram label or metaphor that requires technical knowledge. — judgment call: The owner reviews the rendered desktop and mobile homepage and Walkthrough page, states the answers in their own words, and approves or lists corrections; no outside reader recruitment is required and this is not evidence of comprehension by all prospects.
- [ ] All five public page types implement The clear argument coherently, retain only the Alkaline wordmark as a mandatory old visual element, and provide immediate booking access alongside the explanatory sequence. — judgment call: The owner and an independent design reviewer compare desktop/mobile captures of homepage, Walkthrough, work, Writing index, and a representative article against the selected direction board and the phase-1 approved prototype; the owner signs off the new typography, diagram language, and hierarchy.
- [ ] Existing approved offer facts, public proof boundaries, archive rendering, and public route behavior remain correct; new diagram integration must not change original article source content or chart meaning. — check: `php artisan test --compact tests/Feature/MarketingSiteTest.php` → Exits 0 with stable offer-fact, route, proof-boundary, and archive-preservation assertions; presentation-dependent exact phrasing may change with the approved design, but the free roughly one-hour/pitch-free offer, three-business-day report, and optional implementation must remain verified.
- [ ] Canonical origins, metadata, structured data, public robots/sitemap/RSS behavior, redirects, and private-host boundaries remain correct. — check: `php artisan test --compact tests/Feature/MarketingDiscoveryTest.php` → Exits 0; redesign does not change the established public discovery or private-surface boundaries.
- [ ] Booking placements and PostHog enable/disable behavior remain correct, and supported booking callbacks forward only the existing allowlisted event properties without diagram text or personal form contents. — check: `php artisan test --compact tests/Feature/MarketingAnalyticsTest.php && bun test resources/js/analytics.test.js resources/js/booking.test.js` → Exits 0; phase 4 supplies the two focused JS test files, covering disabled analytics, placement forwarding, and verified Cal event semantics with controlled fakes, not real production bookings.
- [ ] Curated article diagrams render their complete static explanation and caption through the existing trusted authoring path; unsupported names cannot select arbitrary files or inject raw SVG/script; legacy chart, aside, and callout directives retain their behavior. — check: `php artisan test --compact tests/Feature/MarketingDiagramTest.php tests/Unit/MarketingFontsTest.php tests/Unit/MarketingLayoutTest.php` → Exits 0; phase 3 creates positive Walkthrough rendering tests plus negative unknown-name, path-traversal-like-name, and raw SVG/script cases; legacy chart/aside/callout coverage remains. Phases 1–3 reconcile font/layout tests without discarding the owner's pre-existing test work.
- [ ] Explanatory animation is optional and non-looping, skips under reduced motion, and completes safely if that preference changes; existing menu and opening interactions retain their tested behavior where still applicable. — check: `bun test resources/js/interactions.test.js resources/js/diagrams.test.js` → Exits 0; phase 1 creates diagram interaction tests, retains current menu behavior, and replaces obsolete horizon-specific expectations with the new entrance behavior rather than deleting coverage.
- [ ] The production frontend compiles and the affected PHP code passes the project's configured static analysis. — check: `bun run build && composer types:check` → Both exit 0 using the existing dependencies; any proposed dependency change requires separate owner approval.
- [ ] At 390px, 768px, and 1440px widths, and at 200% browser zoom, text and diagrams remain readable without page-level horizontal scrolling; keyboard navigation and focus are visible, and real no-JavaScript/reduced-motion checks retain the full explanation and booking fallback. — judgment call: The implementation reviewer checks the running site in a real browser, captures the named widths and representative zoom/no-JavaScript/reduced-motion states, checks keyboard order and diagram text alternatives, and reports defects separately from PHP or mocked-DOM test results.
- [ ] The representative print specimen preserves every essential relationship and label from the Walkthrough and reporting explanations, uses no animation-dependent information, has no clipped/split figure or missing font, and remains understandable in grayscale. — judgment call: The owner and reviewer inspect the same specimen as screen HTML and browser print/locally saved PDF at US Letter and A4, including grayscale; font embedding rights must be respected and this specimen is not a reusable handout template or a new public PDF service.
- [ ] The existing booking experience works in-browser, and the measurement handoff distinguishes CTA click, embed readiness versus actual open, fallback click, and completed booking rather than treating misleading event names as evidence. — judgment call: The reviewer checks modal/inline booking and fallbacks in-browser and requires a controlled bookingSuccessfulV2 callback exercise with analytics requests intercepted locally. The handoff distinguishes observed external behavior, current official Cal documentation, and locally simulated payload handling; simulation alone does not establish remote Cal semantics. Exclude embed readiness from an opened-calendar funnel unless actual-open behavior is observed. Provide the owner the existing-event funnel and release cutover, explicitly stating which production checks were not performed; real bookings or production analytics writes require separate approval.
- [ ] The first viewport remains usable before motion or third-party booking/analytics loads, and the final product/design records consistently describe the built world, the new explanatory-visual preference, and the unchanged offer. — judgment call: The design reviewer inspects a cold page load and motion-disabled view for visible content and CTA, then reviews PRODUCT.md, DESIGN.md, relevant surface briefs, and shared rules for contradictory old design guidance; the owner confirms the review before launch.

## Scope Boundaries

### In Scope

- Redesign the homepage, Walkthrough, work, Writing index, and article page types in The clear argument world. — The referral-led buying journey and its supporting proof/writing must feel like one practice, not a new homepage bolted onto the discarded identity.
- Create the lead Walkthrough explainer as accessible SVG/HTML with short optional animation and complete static semantics. — The owner selected the offer's concrete value as the first visual explanation, ahead of a reporting or follow-up diagram.
- Support the Walkthrough explanation as an article figure through explicit allowlisted Blade-component handling in the existing authoring path. — Prove reuse for the agreed first explanation without building a generic loader, registry, schema DSL, editor, or arbitrary SVG ingestion. The reporting diagram is added only at Full tier; all other figures wait for real content needs.
- Produce one development-only screen/print specimen of the Walkthrough explanation and the minimal print styles needed for equivalent meaning. — Prove handout suitability without a PDF feature. Reuse actual components in resources/views/figure-specimen.blade.php, reached at /__design/figures only in local/testing environments (404 in production, absent from discovery); save review PDFs/captures under .impeccable/review/print/.
- Preserve approved facts, original archive content, Cal booking/fallbacks, public discovery, and existing analytics; validate and narrowly correct existing event semantics if testing reveals a mismatch. — A persuasion redesign must not regress the working site or infer success from a misnamed readiness event.
- Run focused automated tests, real-browser design/accessibility/print checks, owner review, and reconcile authoritative product/design records. — Approved prior-work lessons require actual rendered evidence and consistent guidance, not source checks or append-only documentation.
- Add a second explanatory figure for Craft & Communicate's reporting story, with a static article/print treatment. — A second concrete subject proves that the visual language generalizes beyond the Walkthrough while strengthening existing implementation proof; use only owner-supplied facts, never fabricated interfaces or numerical results.

### Out of Scope

- Admin and customer project surfaces, authentication, authorization, or tenant features. — This is a public-brand and explanation redesign, not an operational-product redesign.
- PDF-generation endpoints, downloadable-report delivery, reusable handout/report templates, or a publishing workflow. — The owner selected a shared visual system and a representative print example instead.
- A general diagram editor, schema-driven infographic engine, visual CMS, or arbitrary user-uploaded SVG support. — A few curated authored figures need concrete components, not a new product or extensibility platform.
- A new logo, preserving the old palette/horizon, or retaining Alkaline as a mandatory heading font. — Only the existing Alkaline wordmark remains a required visual anchor.
- A full offer/positioning rewrite, new commercial promises, invented client results, GHX disclosure, or fake testimonials/screenshots. — Existing product truth and public proof permissions remain authoritative; targeted explanatory labels and transitions must not change those facts.
- Rewriting archived essays, publishing new essays, a new taxonomy, or homepage promotion of archive articles. — Preserve the full archive and improve its reading/figure presentation; content production and archive curation are separate decisions.
- Paid acquisition, outreach execution, SEO campaigns, an A/B-testing program, new PostHog dashboards, or a broad analytics rebuild. — The site supports demand creation but does not itself create traffic; use existing measurement and avoid experiments unsupported by current volume.
- Recruiting external test readers or making a measured conversion uplift a launch prerequisite. — The owner chose structured personal review plus post-launch PostHog and prospect evidence.
- New dependencies, paid font purchases, production deployment, real bookings, or remote account changes without separate approval. — The requested output is a plan; execution and externally visible or paid actions remain explicit decisions.

### Future Considerations

- Use real prospect conversations and PostHog after release to revisit unclear explanations and booking friction; judge lead quality separately from completed-booking counts.
- Design actual educational handouts/report templates and a PDF publishing workflow if their use becomes concrete.
- Add additional diagrams only when a real explanation benefits; retain equivalent static meaning rather than requiring every figure to animate.

## Decisions Considered and Rejected

- **Plan the redesign now; do not implement or deploy it in this engagement.** — The owner explicitly requested the ideation planning process.
- **Prioritize understandable expertise and the buying journey together.** — rejected: Visual-identity elevation as the primary objective.. The owner says the business is demand-constrained, not supply-constrained.
- **Optimize outreach/referral visitors first, with Writing as supporting credibility.** — rejected: Educational-content-first acquisition or equal-priority entry journeys.. The owner chose outreach/referrals as the first visitor situation to optimize.
- **Keep only the Alkaline wordmark as a mandatory part of the existing visual identity.** — rejected: Preserve the wordmark plus cherry/lilac palette, or preserve the full current visual family.. The owner explicitly opened the surrounding visual system to replacement.
- **Explain what the Walkthrough gives the prospect in the first diagram.** — rejected: Lead with reporting before/after or a hypothetical follow-up scenario.. The owner selected the concrete offer as the prototype's subject.
- **Create a shared web/article/print visual language and one print specimen.** — rejected: Reusable handout templates or a PDF publishing workflow in this redesign.. The owner selected the shared-system scope.
- **Use owner structured review at launch and existing PostHog plus prospect evidence afterward.** — rejected: Recruit three nontechnical test readers before launch.. The owner selected personal review and post-launch measurement and confirmed PostHog is already set up.
- **Adopt The clear argument from the direction board, implemented code-led.** — rejected: The report, unfolded; Clarity, in layers; or the category-standard studio layout.. The owner selected clear-argument on the browser decision board; its recognizable-business-problem-first composition supports the agreed referral journey. Board: .impeccable/mocks/decision/redesign-directions.json; Impeccable seed d5125406, kind pick.
- **Verify rendered behavior separately from markup/build checks and reconcile authoritative records together.** — The owner accepted both lessons mined from the Walkthrough project, recorded in docs/ideation/learnings.md.
- **Treat the existing booking_embed_opened event's semantics as something to verify, not assume.** — resources/js/booking.js currently emits it on Cal linkReady; readiness is not automatically proof of a visitor opening the calendar.
- **Use explicit allowlisted handling for the Walkthrough figure in MVP, adding reporting only at Full tier.** — rejected: A vaguely defined small-set diagram capability that could invite a generic registry or loader.. Scope and complexity critics identified avoidable abstraction; concrete components meet the written-content requirement.
- **Treat Cal as an external embed and cite current documentation separately from controlled application tests.** — rejected: The draft phase note's assumption that all booking callback semantics could be verified from installed-version documentation.. The dependency critic confirmed Cal is loaded from app.cal.com, not installed as a versioned package.
- **Keep spec generation after contract approval and label all draft spec paths as planned, not executable.** — rejected: The dependency critic's suggestion to generate implementation specs before scope approval.. The ideation process deliberately gates specification on contract approval; explicit draft semantics prevent execution from following missing artifacts.
- **Keep independent agent design review as implementation QA, with the owner as the only required human approver.** — rejected: Making the independent finish review optional because external reader recruitment was declined.. Agent QA is not external user research and is part of the requested Impeccable finish process; no additional human reviewers need recruiting.
- **Target Full scope and generate implementation specs for interactive review only.** — rejected: MVP without the second reporting figure, or immediate implementation.. The owner approved Full and full-review planning on 2026-09-18; the reporting figure is required in these specs. The owner subsequently approved all four implementation specs; the approved handoff remains planning-only until execution is separately requested.

## Execution Plan

_Added during Phase 5 handoff. Pick up this contract cold and know exactly how to execute._

### Dependency Graph

```
Visual foundation and Walkthrough prototype
  └── Owner visual approval  (blocked by Visual foundation and Walkthrough prototype)
        └── Referral-led marketing journey  (blocked by Owner visual approval)
              └── Writing figures and print equivalence  (blocked by Referral-led marketing journey)
                    └── Integrated verification and design records  (blocked by Writing figures and print equivalence)
                          └── Owner launch acceptance  (blocked by Integrated verification and design records)
```

### Execution Steps

**Run the project** (recommended) — autopilot reads this contract, plans dependency waves, runs independent phases in parallel, and gates on failure:

```bash
/ideation:autopilot docs/ideation/2026-09-18-explanation-led-marketing-redesign/contract.md
```

**Or run it unattended** — a `/goal` is a durability wrapper around the same autopilot run: Claude re-checks the condition before it is allowed to stop, so failures get repaired and re-run. Generated by `contract-gen --print-goal`; this is the only copy of that string:

```
/goal Drive the Explanation-led marketing redesign contract (2026-09-18-explanation-led-marketing-redesign) to completion with /ideation:autopilot.

1. Run `/ideation:autopilot docs/ideation/2026-09-18-explanation-led-marketing-redesign/contract.md`.
2. It dispatches a BACKGROUND workflow. Wait for the completion notification — never start a second autopilot run while one is in flight.
3. Then run the ideation plugin's `scripts/verify.mjs` against `docs/ideation/2026-09-18-explanation-led-marketing-redesign/contract-data.json` and leave its VERIFY line in the conversation. Resolve the plugin's install directory first — `${CLAUDE_PLUGIN_ROOT}/scripts/verify.mjs` is a placeholder, not a shell variable, and bash will not expand it. That line is the only evidence this goal is judged on.
4. If anything failed, fix the spec or the implementation and go back to step 1. Autopilot skips phases that already have commits.

Done when the most recent VERIFY line reads fail=0 and commits=4/4 — or when two consecutive VERIFY lines are identical and still failing, in which case name the failing checks and stop, because a contract whose checks have rotted must not trap the run.
```

**Or run phases manually** in dependency order:

**Strategy**: Sequential, attended implementation with an early visual approval and final owner acceptance. Shared CSS, layout, and figure components make parallel page rewrites unsafe. Full scope and all four phase specs were approved interactively by the owner on 2026-09-18. The specPath artifacts exist and are ready for a separately authorized execution. Begin with phase 1 and honor both owner checkpoints; no implementation runs during this planning engagement.

1. **Phase 1** — Visual foundation and Walkthrough prototype _(blocking)_

   ```bash
   /ideation:execute-spec docs/ideation/2026-09-18-explanation-led-marketing-redesign/spec-phase-1.md
   ```

2. **Phase 2** — Owner visual approval _(blocking)_

   ```bash
   # Review: Owner visual approval
   ```

3. **Phase 3** — Referral-led marketing journey _(blocking)_

   ```bash
   /ideation:execute-spec docs/ideation/2026-09-18-explanation-led-marketing-redesign/spec-phase-2.md
   ```

4. **Phase 4** — Writing figures and print equivalence _(blocking)_

   ```bash
   /ideation:execute-spec docs/ideation/2026-09-18-explanation-led-marketing-redesign/spec-phase-3.md
   ```

5. **Phase 5** — Integrated verification and design records _(blocking)_

   ```bash
   /ideation:execute-spec docs/ideation/2026-09-18-explanation-led-marketing-redesign/spec-phase-4.md
   ```

6. **Phase 6** — Owner launch acceptance _(blocking)_

   ```bash
   # Review: Owner launch acceptance
   ```

---

_This contract was generated from brain dump input. Review and approve before proceeding to specification._
