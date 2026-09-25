---
version: 1
slug: "resources-views-pages-writing-slug-blade-php"
primary_target: "resources/views/pages/writing/[slug].blade.php"
related_targets: ["resources/css/writing.css"]
---

# Individual writing and print

## Scope

Visitor mode: **Read**. Stay with the argument. Preserve every essay's text, title, date, URL, links, notes, and chart data; figures may be art-directed but keep their data, labels, and meaning. Rebuilt 2026-09-25 inside Your business, in miniature. The admin publishing preview reuses these classes (`.essay`, `.essay-heading`, `.essay-description`, `.essay-byline`, `.article-prose`, `.back-link`), so they stay.

## Direction contract

**THESIS:** Nothing but the essay: one quiet column, the canary resting on the byline rule. Refuses the blog template of sidebars, share bars, and related-post grids.

**OWN-WORLD:** Daylight ground, ink; Mona Sans display title and comfortable Mona Sans reading type on a white 14px reading surface; notes and quotes on daylight panels; ink charts; Commit Mono only for code; one Alkaline wordmark.

**STORY:** Read the essay, inspect any figure or data, return to the archive or subscribe.

**FIRST VIEWPORT:** Approved comp: back link, a big title, description, byline on a hairline with the canary at its right end, all in a centered ~790px column; the white surface begins below with the prose.

**FORM:** The quiet column; my #1 of seven, dealt; seed acbd312e.

**FINISH:** unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Approved comp

`.impeccable/mocks/decision/wacbd-quiet-column.png` (locked 2026-09-25 on the surface decision page, comp-led). The comp abridged the essay's opening and invented the paragraph under the first heading; every page renders its essay's real text.

## Content and behavior

Prose measure about 66ch at a comfortable reading size; headings with more space above than below; notes (asides and the Key takeaway callout) as daylight panels; charts in ink with their accessible data tables; code in Commit Mono. The end offers Back to all writing and Subscribe via RSS. Print keeps the essay, figures, and data tables unfolded without navigation, on 16mm margins.

## Unresolved

None beyond owner review of the build.

## Finish evidence

Comp-led build (seed acbd312e). The canary plate was regenerated to the comp's slim side-profile pose and sits on the byline rule; back link, rule, white surface and prose align to the comp within a few pixels at 1536 (hero 83%). The hero gate stays open on a tooling refusal: the owner answered "Downgrade both (Recommended)" to downgrading the comp for the title (pinned Mona Sans 800) and the shared header, and --force refused the quoted answer twice. Prose line count differs only because every essay renders its real text. Manual checks: all ten essays without horizontal scroll at 320 to 1536, canary never overlaps the byline, mobile reading surface inset 8px, no-JS complete, print unfolds data tables without chrome. Figures (charts, notes, diagrams) moved onto studio tokens in writing.css. Independent finish review (degraded protocol): fix (byline hairline used a literal gray), then verdict ship. Not owner launch approval.
