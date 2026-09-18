# Implementation Spec: Explanation-led marketing redesign — Phase 3

**Contract**: ./contract.md
**Source of truth**: ./contract-data.json
**Scope**: Full — Walkthrough and reporting figures
**Estimated effort**: L
**Prerequisite**: Phase 2 complete; phase-1 owner approval remains binding.

## Technical Approach

Extend the reviewed visual world into the Writing index, long-form reading, and both authored figures' static print treatments. This is the Read surface: sustained comprehension matters more than landing-page scale or motion. Preserve every original essay's text, title, date, URL, links, notes, chart data, and meaning. Do not insert new marketing material into the archive to demonstrate components.

Use `ReadWriting`'s existing placeholder-and-Blade approach for exactly two explicitly named diagrams. Add a development specimen using the real renderer/components and CSS. It is a local/testing-only HTML view, not a PDF-generation endpoint, reusable handout template, CMS, or additional public article. Browser print/Save as PDF supplies review output; installed Laravel PDF/Browsershot packages do not imply an implemented PDF feature and need not be configured.

## Decisions Considered and Rejected

- Writing is first-class, but this redesign's initial acquisition priority remains outreach/referrals rather than new essay production or SEO campaigns.
- Use the same explanatory visual language across web, writing, and print; reject a PDF authoring/delivery feature or reusable handout/report template system.
- Equivalent meaning is required; identical layout and a frozen animation frame are not.
- Full scope requires the reporting figure as a second concrete subject; other diagrams wait for content needs.
- Use explicit allowlisted Blade handling; reject a diagram registry, arbitrary SVG loader, data DSL, or editor-facing extension API.
- Preserve original archive source/metadata and chart meaning. Do not rewrite old essays, force them into current positioning, or promote them on the homepage.
- Owner review is the human acceptance gate; no outside-reader recruitment.

## Feedback Strategy

**Inner-loop command**: `php artisan test --compact tests/Feature/MarketingDiagramTest.php`

**Playground**: Feature tests exercising `ReadWriting` and the existing local dev server for real article/figure rendering and browser print preview.

**Why**: Tests catch parsing, escaping, and environment boundaries; screen and print inspection catch legibility, pagination, font, and relationship failures those tests cannot see.

## File Changes

### New Files

| File Path | Purpose |
| --- | --- |
| `resources/views/components/marketing/article-diagram.blade.php` | Narrow figure wrapper selecting only the two approved authored components |
| `resources/views/figure-specimen.blade.php` | Standalone development-only reading and print specimen, no analytics/calendar |
| `tests/Feature/MarketingDiagramTest.php` | Rendering, repeat-instance IDs, input-denial, and specimen route boundary tests |

### Modified Files

| File Path | Changes |
| --- | --- |
| `app/Actions/ReadWriting.php` | Narrow curated diagram directive following existing placeholder rendering |
| `routes/web.php` | Host-scoped local/testing specimen endpoint with request-time production denial |
| `resources/views/pages/writing/index.blade.php` | New archive layout and reading hierarchy, unchanged archive data |
| `resources/views/pages/writing/[slug].blade.php` | New article title/prose/figure presentation without source-text changes |
| `resources/views/components/marketing/article-chart.blade.php` | Accessible chart/print treatment while retaining source values and labels |
| `resources/views/components/marketing/article-note.blade.php` | Notes in the new reading grammar, preserving titles and meaning |
| `resources/views/components/marketing/walkthrough-diagram.blade.php` | Article/static-print adaptations without changing the explained offer |
| `resources/views/components/marketing/reporting-diagram.blade.php` | Article/static-print adaptations without unsupported claims |
| `resources/css/marketing.css` | Reading, figures, source data disclosures, and Letter/A4 print styles |
| `tests/Feature/MarketingSiteTest.php` | Archive/notes/chart regressions and preservation checks |
| `tests/Feature/MarketingDiscoveryTest.php` | Specimen excluded from public discovery |
| `.impeccable/surfaces/resources-views-pages-writing-index-blade-php.md` | Archive surface strategy |
| `.impeccable/surfaces/resources-views-pages-writing-slug-blade-php.md` | Reading and figure strategy |
| `DESIGN.md` | Document the reviewed Reading/print system |
| `docs/ideation/2026-09-18-explanation-led-marketing-redesign/acceptance.md` | Screen/print evidence and known limitations |

### Deleted Files

None. In particular, no essay, chart data, or existing test is deleted.

## Implementation Details

### 1. Reading surfaces

Patterns: current Writing Folio pages, article note/chart components, and `.ai/rules/writing.md`.

- Keep chronological archive grouping, original links/dates/titles/descriptions, RSS access, current-navigation state, and unknown-article 404 behavior.
- Use a comfortable prose measure and stable type rhythm. Article titles may be expressive sans-serif without importing homepage display scale into paragraphs. Check the actual longest titles and real tables/code blocks.
- Keep note headings, source captions, original author-link destinations, and complete chart values. Restyle chart marks without changing axes, relative quantities, or interpretation.
- On print, reveal any essential source data currently hidden by closed disclosures; avoid duplicate desktop/mobile diagram versions in the print tree. Navigation and booking chrome are not part of a handout.
- Reconfirm the archive files and data are unchanged relative to the execution baseline using git diff review. Existing selective assertions alone are not proof that every paragraph is preserved. Baseline source at planning: commit `3247b6da2d3b3dfa3fa0f0d2711967ae657cb75d`; record a newer owner-approved baseline if source changed before execution.

**Feedback loop**: Inspect the index, a long-title article, `your-ai-wrote-a-bug`, `six-months-talking-to-a-machine`, and `stop-giving-me-take-homes` across screen/print. Run `php artisan test --compact tests/Feature/MarketingSiteTest.php tests/Feature/MarketingDiscoveryTest.php`.

### 2. Explicit curated figure syntax

Pattern: `app/Actions/ReadWriting.php::render()`.

Support this narrow repository-authored form:

```text
@figure kind=diagram name=walkthrough caption="What the Walkthrough gives you."
@endfigure
```

The other allowed name is `reporting`. Match only those names, map them explicitly to the two known components, and use the existing block-placeholder substitution so ordinary Markdown continues stripping unsafe HTML. Do not construct a view/file path from arbitrary input. Keep captions escaped by Blade; do not accept raw SVG in Markdown. Unknown or malformed diagram directives remain harmless escaped text, never a file lookup or executable fragment. No configurable registry or loader is necessary.

Keep article figures static by default. Marketing animation may enhance explicitly opted-in instances, but reading must not repeatedly move while someone studies a figure. Ensure multiple identical diagrams in one render do not share SVG/ARIA IDs. Add only the concrete component props necessary to distinguish screen/print or animation participation; no graph configuration API.

**Feedback loop**: Render both names, two occurrences of one name, text before/after a figure, captions with quotes/markup, unknown names, traversal-like names, raw SVG/script payloads, and mixed legacy chart/aside/callout directives. Run the inner-loop command; assert meaningful content, safe escaping, no arbitrary lookup, and complete static output.

### 3. Development specimen and print proof

Add a named route for `/__design/figures` within the configured marketing host's existing `routes/web.php` group. Enforce allowed `local`/`testing` environments when handling the request, not solely when registering routes, so cached routes cannot expose it in production. Other environments return 404. It is not a Folio marketing page, has no sitemap/RSS/navigation entry, and sends a noindex response. Robots exclusion is not its access boundary; the runtime 404 is.

Render `resources/views/figure-specimen.blade.php` as standalone HTML with the same font/CSS pipeline and actual renderer/components. Include a short explicitly labeled illustrative reading excerpt, the Walkthrough and reporting figures, and representative note/chart content using existing trusted data. It must not create a published essay or be mistaken for a client deliverable. Do not attach PostHog config, booking triggers, or Cal embeds.

Create screen and static print arrangements from the same information. On US Letter and A4, ensure labels and captions remain adjacent, figures do not clip, reading order remains clear, and grayscale preserves relationships. If a full figure cannot fit on one page, design labeled stages rather than shrinking it to illegibility. Wait for fonts when saving review PDFs. Confirm any embedded font license permits the intended output; never distribute licensed font files as handout assets or bypass restrictions. If rights are unresolved, report the limitation to the owner instead of claiming the specimen is distributable.

Save local review output under `.impeccable/review/print/` with separate Letter/A4 captures/PDFs; these are review artifacts, not public downloads. Resolve the configured host with Boost before sharing the local specimen URL.

**Feedback loop**: Test local/testing success, production/staging denial, noindex, absent analytics/Cal markers, and absence from sitemap/RSS. Render/print with animation enabled, disabled, and reduced motion; essential information must match. Check `php artisan test --compact tests/Feature/MarketingDiagramTest.php tests/Feature/MarketingDiscoveryTest.php`.

## Testing Requirements

- Create the feature test with `php artisan make:test --pest MarketingDiagramTest --no-interaction` during implementation; read the testing skill first.
- Test positive rendering and negative input cases, not merely presence of a figure class.
- Prove the request-time specimen gate works even when a route was registered before the environment changes in a controlled test; restore application state between tests.
- Retain complete original chart tables, note titles, dates, URLs, and unsafe-HTML/unsafe-link stripping.
- Inspect both screen and saved PDF visually; screenshots or passing parser tests do not substitute for that comparison.

## Failure Modes

| Component | Failure / trigger | Impact | Mitigation |
| --- | --- | --- | --- |
| Directive parser | Dynamic input becomes a view path | File disclosure/template misuse | Two explicit names only; negative tests |
| Placeholder rendering | Diagram consumes adjacent Markdown | Missing essay text | Mixed-content and repeated-figure tests |
| Article chart | New styling changes apparent quantities | Misleading figure | Preserve data/axes and inspect actual chart |
| Specimen route | Environment checked only at registration | Cached route exposed in production | Request-time denial and regression test |
| Print | Animation captured mid-sequence or disclosures closed | Missing explanation/data | Static print rules and visible essential information |
| Print | Whole desktop figure shrunk onto paper | Illegible labels | Print-specific flow or labeled stages |
| Fonts | Font unavailable or embedding rights unresolved | Wrong output or license problem | Check load and permitted use; report blockers honestly |

## Validation Commands

```bash
php artisan test --compact tests/Feature/MarketingDiagramTest.php tests/Feature/MarketingSiteTest.php tests/Feature/MarketingDiscoveryTest.php tests/Unit/MarketingFontsTest.php tests/Unit/MarketingLayoutTest.php
bun test resources/js/interactions.test.js resources/js/diagrams.test.js
vendor/bin/pint --dirty --format agent
composer types:check
bun run build
```

## Exit and Rollout

Finish with bounded browser/print inspection and a fresh agent design review of the Reading surface and specimen. Document the reviewed system and record actual evidence in `acceptance.md`. The original archive remains unchanged and public URLs remain stable. No PDF endpoint, scheduled export, new essay, or public handout is introduced. Phase 4 verifies the integrated result before owner launch acceptance.
