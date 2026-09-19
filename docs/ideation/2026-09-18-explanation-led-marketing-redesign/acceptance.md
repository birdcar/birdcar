# Phase 1 acceptance — The clear argument

Spec: `docs/ideation/2026-09-18-explanation-led-marketing-redesign/spec-phase-1.md`

## Owner gate

**PENDING — not approved. Phase 2 is blocked.**

The direction selection authorizes this prototype, not approval of its finished typography, opening, figure, mobile behavior, or print sketch. No owner verdict has been received. No deployment, live booking, or PostHog account change was performed.

## Execution checklist

- [x] Inspect clean entry tree and preserve the already-committed owner alignment work.
- [x] Scout: GO, 5/5 readiness gates, recorded in `context-map.md`.
- [x] Record chosen direction with Impeccable surface-brief commands; update product commitments and superseding Boost rule.
- [x] Build cyan/Barlow foundation, single Alkaline wordmark, business-problem opening, and immediate free-Walkthrough CTA.
- [x] Build complete semantic figure, optional motion, repeated-instance and static/print behavior.
- [x] Run validation and bounded browser checks.
- [x] Close independent finish review and record the built foundation in DESIGN.md.
- [x] Close spec-aware code review.
- [ ] Obtain and record the owner's visual decision.

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

## Remaining scope

The lower homepage keeps its existing facts and narrative pending Phase 2. Walkthrough, work, Writing, articles, and the finished print specimen await Phases 2–3, although shared palette/type changes already reach them. Historical horizon assets are retained for the Phase 4 reference audit. Admin/customer typography and operational routes were not redesigned.
