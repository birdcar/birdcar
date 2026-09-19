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

---

# Phase 3 acceptance — Reading and static print

Spec: `docs/ideation/2026-09-18-explanation-led-marketing-redesign/spec-phase-3.md`

## Execution and preservation — 2026-09-19

- [x] Clean execution baseline: `b2fbd28`; Phase 1 owner approval remains binding.
- [x] Scout GO, 5/5 gates, with the Phase 3 map appended without removing prior-phase maps.
- [x] Writing index and articles use the reviewed reading hierarchy, original metadata, notes, links and chart data.
- [x] Exactly two curated diagram names, literal escaped captions, static reading figures and collision-free repeated instances.
- [x] Host-scoped named specimen route with request-time local/testing allowlist, noindex, no analytics/calendar, and no public discovery entry.
- [x] Letter/A4 PDFs saved and visually inspected alongside screen captures; complete chart disclosures in print.
- [x] Independent fresh design review: **ship**, no material fixes.
- [x] Final spec-aware review: PASS, cycle 1, zero findings.
- [x] Owner approved the Phase 3 commit message and direct commit fallback because the /commit skill is unavailable; commit recorded in Git history with the spec path.

Exhaustive source preservation, not just selective assertions: both `git diff --exit-code 3247b6da2d3b3dfa3fa0f0d2711967ae657cb75d -- resources/writing` and `git diff --exit-code b2fbd28 -- resources/writing` return zero with empty output. This compares the entire archive directory, including all ten original essays and both JSON data files. No newer source baseline is needed; no article, paragraph, frontmatter, link, note directive or data file was changed. The archive and original public URLs remain intact. Renderer tests additionally protect both complete chart tables, original note titles/author destinations, unsafe HTML/link stripping, and metadata.

## Validation

- Required PHP command: PASS, 103 tests / 620 assertions across `MarketingDiagramTest`, `MarketingSiteTest`, `MarketingDiscoveryTest`, `MarketingFontsTest`, `MarketingLayoutTest`.
- `bun test resources/js/interactions.test.js resources/js/diagrams.test.js`: PASS, 23 tests / 142 assertions.
- Additional homepage/layout regression: `php artisan test --compact tests/Feature/MarketingAnalyticsTest.php`: PASS, 7 tests / 33 assertions.
- `vendor/bin/pint --dirty --format agent`: PASS.
- `composer types:check`: PASS, zero PHPStan errors.
- `bun run build`: PASS.
- `git diff --check`: PASS.

No dependency or database change. The complete PHP suite has not been run. `resources/views/pages/index.blade.php` has one additional support change: its existing Walkthrough instance explicitly opts into animation now that the component defaults to static. No JavaScript behavior changed. Three implementation decisions are recorded in `implementation-notes-phase-3.html`.

## Screen and print evidence

Boost resolved the local specimen to `http://localhost:8000/__design/figures`. Captures are local review files in `.impeccable/review/`; PDF/raster/text review output is under `.impeccable/review/print/`, not a public download directory.

- `phase-3-{writing,specimen,long-title,bugs,prompts,notes}-{1440,768,390}.png`: archive, specimen, the longest original title (You're already doing all hands support), Your AI wrote a bug, Six months of talking to a machine, and Stop giving me take homes. All 18 pages returned 200, loaded Barlow, and had no document horizontal overflow. Separate article `opening` captures retain readable first-viewport evidence instead of relying on long thumbnails.
- `phase-3-line-chart-{1440,768,390}.png` and `phase-3-line-chart-open-390.png`: zero/400/800 axis, all six values in the source table, readable minimum plot size, visible small-screen scroll instruction. ArrowRight on the focusable plot scrolled horizontally; native disclosure opened the complete table. An earlier End-key probe did not scroll and is not claimed as keyboard-scroll proof.
- `phase-3-{writing,long-title,bugs,prompts,notes,specimen}-{Letter,A4}.pdf`: actual Chromium print output, backgrounds disabled, no browser header/footer, fonts awaited. Page PNGs are rasterized from these saved PDFs, not print-media screenshots. Figure labels/captions remain adjacent; tables appear even though native details are closed. Navigation/booking chrome is absent. The specimen is three pages in each format: labeled excerpt/note/chart, numbered Walkthrough stages, reporting relationships. Page geometry was inspected in both formats, including grayscale linework and recommendation borders.
- `phase-3-specimen-{no-js,reduced,animated}.png` and corresponding Letter/A4 PDFs: static meaning remains complete. The animation probe temporarily opted the existing component into the real `diagrams.js` initializer in the browser only; nine active browser animations were observed before printing and zero after. All six state-specific PDFs had identical extracted text to their corresponding static reference before the final equal-width print table adjustment; that adjustment changes column layout only, not content or state behavior.
- `phase-3-stress-390.png` and print `phase-3-stress-{Letter,A4}.pdf`: temporary browser-only long code/table probe. The archive has inline code but no fenced code blocks or Markdown tables, so synthetic stress content was not added to essays. Screen overflow stays local; print wraps code and long table values within the paper width. An initial PDF accidentally used emulated screen media; it was discarded and replaced with explicit print-media output. The probe exposed long unbroken table overflow; fixed-layout print tables and anywhere wrapping fixed it.

The existing server rendered analytics configuration on ordinary marketing pages despite earlier Phase 2 notes; browser QA blocked PostHog requests before navigation and did not change environment configuration. The standalone specimen rendered no PostHog or Cal markers and loaded only the CSS/font pipeline. No booking or analytics submission was deliberately made. Recent Boost browser logs contain Vite connection events, no application exception. Lerd tools were not available in this session, so no Lerd request profiling is claimed.

## Font rights and limitations

`pdffonts` confirms the specimen embeds subsets of Barlow Regular/Medium/SemiBold/Bold, with Unicode maps. The Barlow upstream license at `https://raw.githubusercontent.com/google/fonts/main/ofl/barlow/OFL.txt` was read: SIL OFL 1.1 explicitly permits embedding and excludes produced documents from its font-license requirement. Real article PDFs also use Commit Mono for inline code; `resources/fonts/commit-mono/LICENSE.txt` contains the same OFL embedding permission. No Alkaline font appears in the inspected PDFs: the licensed wordmark is hidden with navigation, and the standalone specimen never renders it. Alkaline document-embedding rights were not established and are not claimed. No font files are distributed as handout assets.

This is a local review specimen, not a production PDF feature, reusable client report, public handout, or owner launch acceptance. Output was inspected in Chromium; physical printers and Safari/Firefox pagination remain untested. The existing archive's prose/data are preserved even where interpretation could be debated; this phase does not revise historical assertions.

## Independent review

Fresh `impeccable-finish-reviewer`: **ship**, no material fixes; contract/persistence/type/material/ground/reading/figures/print checks passed. Its scope is the supplied screen and saved-PDF evidence, not owner approval. No new comp or quality-bar card was required for the extension of the approved world.

One detector pass returned 30 advisories: 28 typography-step differences and two deliberate grayscale print colors against the Phase 2 design record. No blocking detector finding. The independent documenter updated `DESIGN.md`, `.impeccable/design.json`, and both Writing briefs without changing UI merely to silence advisories.

The independent boundary review raised one medium suggestion to add exhaustive archive snapshots. A separate fresh archive-proof reviewer independently confirmed empty diffs, identical blob IDs, and exactly the original 12 source/data files against both baselines, with no concrete renderer regression. This satisfies the spec’s exhaustive Git-diff requirement; no Git-dependent runtime test or duplicate archive snapshot was added. Parser, runtime environment, host, noindex, unsafe-input and discovery checks passed the focused tests.

Final spec-aware reviewer: **PASS**, cycle 1 of 3, zero critical/high/medium/low findings. New files were registered with intent-to-add before the reviewer read `git diff HEAD`. The independent design-record check identified pre-commit status wording and header/footer label ambiguity; wording now distinguishes implemented/reviewed Phase 3 from its commit, and booking labels from shared destinations. DESIGN frontmatter/JSON sidecar and final whitespace checks pass.

## Remaining scope

Phase 4 owns integrated validation and owner launch acceptance. No deployment, new essay, public PDF endpoint, reusable report-template system, registry, arbitrary SVG loader, or new commercial claim was introduced.
