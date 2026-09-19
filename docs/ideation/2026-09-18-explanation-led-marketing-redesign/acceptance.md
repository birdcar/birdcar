# Phase 1 acceptance — The clear argument

Spec: `docs/ideation/2026-09-18-explanation-led-marketing-redesign/spec-phase-1.md`

## Owner gate

**APPROVED — owner visual decision recorded on 2026-09-19.**

After reviewing the implemented Phase 1 surface, the owner selected **“Approve Phase 1”** in response to the explicit gate covering the opening, Barlow typography, Walkthrough figure, mobile behavior, and static/print sketch. This approves implementation commit `0731e62`, not future pages or a finished print specimen. Phase 2's visual prerequisite was satisfied; at that approval point Phase 2 had not started and required a separate execution request. No deployment, live booking, or PostHog account change was performed.

## Execution checklist

- [x] Inspect clean entry tree and preserve the already-committed owner alignment work.
- [x] Scout: GO, 5/5 readiness gates, recorded in `context-map.md`.
- [x] Record chosen direction with Impeccable surface-brief commands; update product commitments and superseding Boost rule.
- [x] Build cyan/Barlow foundation, single Alkaline wordmark, business-problem opening, and immediate free-Walkthrough CTA.
- [x] Build complete semantic figure, optional motion, repeated-instance and static/print behavior.
- [x] Run validation and bounded browser checks.
- [x] Close independent finish review and record the built foundation in DESIGN.md.
- [x] Close spec-aware code review.
- [x] Obtain and record the owner's visual decision.

## Validation evidence — 2026-09-19

- `bun test resources/js/interactions.test.js resources/js/diagrams.test.js`: PASS, 23 tests, 142 assertions.
- `php artisan test --compact tests/Feature/MarketingSiteTest.php tests/Unit/MarketingFontsTest.php tests/Unit/MarketingLayoutTest.php`: PASS, 55 tests, 362 assertions.
- `vendor/bin/pint --dirty --format agent`: PASS after correcting the new Blade facade import.
- `composer types:check`: PASS, zero PHPStan errors.
- `bun run build`: PASS. Barlow 400/500/600/700 assets self-hosted through existing Bunny/Vite pipeline; generated font CSS uses `font-display: swap`.

Additional regression check: `php artisan test --compact tests/Feature/MarketingAnalyticsTest.php tests/Feature/MarketingDiscoveryTest.php` PASS, 23 tests, 78 assertions. `git diff --check` PASS.

No dependency was added. The complete PHP suite has not been run; the affected suite above includes archive, redirects, proof, offer, and wordmark invariants. `MarketingLayoutTest.php` remains unchanged and passing: the lower homepage's work/Walkthrough grids still share horizontal layout rules. Existing opening lifecycle cases were adapted into `diagrams.test.js`; native menu cases remain in `interactions.test.js`.

## Browser evidence

Boost resolved the existing development server to `http://localhost:8000`. Reused it rather than starting a conflicting server. Captures are local review artifacts under `.impeccable/review/` (not shipping assets).

- `phase-1-opening-{1440,768,390}.png`: real opening, loaded Barlow, single wordmark, CTA visible without animation. CTA/terms end at approximately 522px, 452px, and 564px respectively in the initial checks.
- `phase-1-{1440,768,390}.png`: complete homepage; no horizontal overflow. Mobile full-page evidence uses the complete no-JS render after an oversized animated-page capture exhibited a compositor/tiling artifact.
- `phase-1-diagram-{1440,768,390}.png`: focal report, first recommendation, three optional branches. Cropped from document-top full-page captures, not unstable element screenshots.
- `phase-1-zoom-200.png`: 720×450 CSS-pixel layout, equivalent to a 1440×900 viewport at 200% browser zoom. No overflow. This is reflow emulation, not an OS/browser zoom-control test.
- `phase-1-no-js.png`: complete explanation with JavaScript disabled; native menu opens and the main CTA retains `/walkthrough` fallback.
- `phase-1-print.png`: browser print-media sketch with all four stages and report contents visible, including a monochrome first recommendation. Not a finished paginated print specimen; that belongs to Phase 3.
- `phase-1-regression-*.png`: `/walkthrough`, `/work`, `/writing/`, and `/writing/your-ai-wrote-a-bug/` at 1440/390px return 200 with no horizontal overflow. These are foundation-regression checks, not completed redesigns. The external calendar did not render in these captures; its plain event link remains available and no booking was attempted.

Real-browser behavior: nine active Motion property animations observed on viewport entry; switching reduced motion on cancels them and restores static transforms. Escape closes the menu and returns focus; a click on content outside closes it. Keyboard action focus has a solid outline and contrasting outer ring. Two figures injected before initialization render eight visible stages and restore to static when print media is entered. No page errors were observed in the successful behavior checks.

An initial outside-click probe landed on the scrollbar rather than document content; the corrected content click passed. Two intercepted-response probes timed out before navigation; repeated-instance verification instead used a browser-only pre-initialization DOM clone. These failed probes are not application-test failures or accepted evidence.

## Independent finish review

Initial reviewer `ad87b9d9`: **fix**. Keep the opening, palette, type, factual copy, and static explanation; raise the figure beyond four equally weighted text columns. Requested a focal report artifact, relationship-specific choices, and a distinctive editorial explanation.

Fix batch: two preparatory stages now lead into a larger authored SVG/HTML report; the three next-step choices branch from it. Yellow retains a single recommendation emphasis. Tablet/mobile reflow in semantic order. Verdict-only reviewer `be6a82e9`: all four findings resolved, no remaining findings, **ship**. This is a bounded finish verdict, not owner acceptance.

Independent documenter updated `DESIGN.md` and `.impeccable/design.json`. Final integration corrected frontmatter quoting and extracted the figure preview directly from implemented markup/CSS so its report layout matches the source. YAML frontmatter and JSON parse checks passed.

The mechanical detector ran once over the changed UI targets: **98 advisories**, all comparisons with the superseded DESIGN.md (80 typography, 17 color, one radius). No non-advisory findings. Raw result: `phase-1-detector.json`. The new documenter must describe the built system rather than preserve the obsolete palette/type ramp.

## Spec-aware code review

Cycle 1 of 3: **PASS**, zero findings (zero critical/high/medium/low). The read-only reviewer compared the full `git diff HEAD`, including intent-to-add files, with the original spec and pattern files. No fixes were required. This review does not replace the owner's visual decision.

Final combined verification after documentation integration: 23 Bun tests / 142 assertions; 78 PHP tests / 440 assertions; Pint, PHPStan, Vite production build, and `git diff --check` all passed.

## Remaining scope at the end of Phase 1

The lower homepage kept its existing facts and narrative pending Phase 2. Walkthrough, work, Writing, articles, and the finished print specimen awaited Phases 2–3. Historical horizon assets were retained for the Phase 4 reference audit. Admin/customer typography and operational routes were not redesigned.

---

# Phase 2 acceptance — Referral-led marketing journey

Spec: `docs/ideation/2026-09-18-explanation-led-marketing-redesign/spec-phase-2.md`

## Execution checklist — 2026-09-19

- [x] Clean entry tree; Phase 1 owner gate satisfied.
- [x] Scout: GO, 5/5 gates; Phase 2 extension saved in `context-map.md`.
- [x] Complete homepage problem → offer → proof → implementation/care/person → invitation journey.
- [x] Complete Walkthrough deliverable, sequence, objections, independent next steps, and inline booking.
- [x] Complete Craft & Communicate story and reusable static reporting illustration on home/work.
- [x] Preserve named routes, configured URLs, wordmark, navigation, placements, and booking fallbacks.
- [x] Validate at 390/768/1440px, without JavaScript, and with loaded/blocked booking script.
- [x] Independent finish review: **ship**, no material fixes. Independent proof-boundary review: no findings.
- [x] Document the built system in `DESIGN.md`, `.impeccable/design.json`, and all three surface briefs.
- [x] Final spec-aware review: PASS, cycle 1, zero findings.
- [x] Phase commit message explicitly approved by the owner; commit recorded in Git history with the spec path.

This is implementation/QA evidence, not owner visual approval of Phase 2. The Phase 1 approval remains correctly scoped. No deployment, slot selection, booking submission, or remote analytics configuration change was made.

## Validation evidence

- Baseline inner loop: 47 PHP tests, 256 assertions, PASS.
- Required PHP validation: `php artisan test --compact tests/Feature/MarketingSiteTest.php tests/Feature/MarketingDiscoveryTest.php tests/Feature/MarketingAnalyticsTest.php tests/Unit/MarketingLayoutTest.php`: PASS, 72 tests, 384 assertions.
- Required Bun validation plus focused booking regression tests: `bun test resources/js/interactions.test.js resources/js/diagrams.test.js resources/js/booking.test.js`: PASS, 31 tests, 157 assertions. The original two spec files retain 23 tests / 142 assertions.
- `vendor/bin/pint --dirty --format agent`: PASS.
- `composer types:check`: PASS, zero errors.
- `bun run build`: PASS.
- `git diff --check`: PASS.

No dependency was added. `booking-link.blade.php`, `arrow.blade.php`, and `MarketingLayoutTest.php` required no changes: their existing behavior/line treatment/aligned section-grid invariant remain applicable and passing. The pronoun-ratio and question-count cases were replaced, not deleted, with visible independent choices and concrete buying-journey assertions. Proof boundaries were strengthened; no archive tests were removed. The complete PHP suite has not been run.

## Browser evidence

Captures live under `.impeccable/review/` and are local review artifacts, not shipping assets. Boost resolved the existing server to `http://localhost:8000`.

- `phase-2-{home,walkthrough,work}-{1440,768,390}.png`: all three page compositions at all target widths; no horizontal overflow. Real short/long headings wrap in reading order. Homepage opening and Phase 1 figure remain intact.
- Full-page Walkthrough captures show the external iframe as blank when captured offscreen. They are layout evidence only, not loaded-calendar evidence. `phase-2-calendar-{1440,768,390}.png` separately show the real loaded inline calendar at viewport size; `phase-2-modal.png` shows the loaded modal. Captures waited for real date buttons, not a loader marker alone. The selected-day ink matches the new palette.
- `phase-2-no-js-{home,walkthrough,work}.png`: complete static pages; native menu opens; the Work invitation navigates to `/walkthrough`; Walkthrough CTAs keep `#choose-a-time` and the plain Cal.com fallback.
- `phase-2-reporting-repeated-gray.png`: two copies on one page, complete labels/captions, zero IDs or reference collisions. `phase-2-reporting-print.png`: full static relationships with readable print type. This is print-media QA, not Phase 3’s finished paginated specimen.
- Keyboard Enter opens the real modal while retaining the source homepage. Walkthrough keyboard activation navigates to the inline anchor at all target widths. The booking action has a solid 2px focus outline plus its contrasting outer ring.
- Actual Meta-click opens `/walkthrough` in a new tab, leaves the source page in place, and opens zero modals after the fix. Unit tests also cover Alt/Ctrl/Shift and middle-click behavior.
- With Cal’s script blocked, the homepage CTA navigates normally to `/walkthrough`, whose plain fallback remains `https://cal.com/birdcar/walkthrough`.
- Escape closes the mobile menu and restores summary focus; an outside content click closes it. No booking interaction proceeded beyond viewing available dates/times.

The initial browser probe exposed enabled local analytics and stale asset URLs in long-running workers. The application JS returned 404 and no PostHog resource entries were observed; this does not establish remote receipt one way or the other. Subsequent QA blocked PostHog requests, set gitignored `POSTHOG_DISABLED=true`, refreshed workers using temporary cached configuration, and verified that rendered pages omit analytics configuration. The temporary configuration cache was removed afterward; local analytics remains disabled. No production analytics test was deliberately sent.

Failed probes were not counted as passes: response interception caused navigation timeouts; an unsupported URL constructor interrupted one result collector; waiting for the zero-sized Cal custom-element host to become visible was replaced by waiting for actual date controls; an outside-click probe initially targeted a heading covered by the open menu. Corrected probes supplied the evidence above.

## Independent review

Fresh finish reviewer: **ship**, no material fixes. It reviewed all three widths, static/no-JS/repeated/grayscale/print figures, and separate loaded-calendar evidence. Its verdict covers the supplied Phase 2 pages, not owner approval, Writing, or production rollout. Independent factual review: no offer, voice, proof, employer, or commercial-boundary findings.

One mechanical detector run produced 87 advisories, all typography-step differences against the Phase 1-only design record; no non-advisory findings. The independent documenter updated the built design record and sidecar without changing UI to satisfy the detector.

Five implementation decisions are recorded in `implementation-notes-phase-2.html`, notably the demonstrated Cal modifier-click collision and focused extra JS tests.

Spec-aware review cycle 1 of 3: **PASS**, zero critical/high/medium/low findings. The reviewer examined the final `git diff HEAD`, including all intent-to-add files, against the original spec and its pattern files. Independent design-record review also checked claims and preview fidelity: the header specimen is now explicitly marked schematic rather than a booking-integration example. The required FINISH provenance clause remains conditional; no shipping rasters exist to which it applies.

Final design YAML frontmatter and JSON sidecar both parse. `git diff --check` passes. The owner explicitly approved the Phase 2 commit message; this is commit authorization, not visual approval of the new pages.

## Remaining scope

Writing, articles, and the finished print specimen await Phase 3. Full analytics callback semantics and historical-asset reference audit remain in Phase 4. Admin/customer surfaces are unchanged. No conversion lift is inferred from tests or screenshots.
