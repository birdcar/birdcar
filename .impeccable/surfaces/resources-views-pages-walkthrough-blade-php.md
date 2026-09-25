---
version: 1
slug: "resources-views-pages-walkthrough-blade-php"
primary_target: "resources/views/pages/walkthrough.blade.php"
related_targets: ["resources/css/walkthrough.css","resources/js/fit-check.js","resources/views/components/marketing/fit-card.blade.php"]
---

# The Walkthrough

## Scope

Visitor mode: **Persuade**. A referral prospect understands exactly what the free hour produces, checks honestly that it fits, and picks a time in place. PRODUCT.md owns offer truth and approved copy. Rebuilt 2026-09-25 inside Your business, in miniature; The clear argument version is retired.

## Direction contract

**THESIS:** Qualify, then book, on the owner's desk: the card on the desk asks three honest checks before it turns into the calendar. Refuses the long sales scroll and the bare calendar embed.

**OWN-WORLD:** Daylight #edf3f0, ink, canary actions; premium matte isometric desk plate (pale wood, green blotter, plant, open brass cage, canary perched on the card); white 14px cards; Mona Sans; one Alkaline wordmark.

**STORY:** Read the offer and pitch-free promise, see the report you keep, answer three checks, pick a time in place; below: steps, the problem, your choice, fair questions.

**FIRST VIEWPORT:** Approved comp. Headline left at ~76px, lead, promise, report card with canary “Where I’d start”; fit card on the desk right, Yes/Not yet pills, canary “Show me times”; four-step band at the fold.

**FORM:** Fit card on the desk; my #1 of six in re-roll 1, owner-steered; seed 5b3fd960.

**FINISH:** unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Approved comp

`.impeccable/mocks/decision/w5b3r1-fit-card.png` (locked 2026-09-25 on the surface decision page after the owner re-rolled with the steer “booking desk look, fair-questions qualification before the calendar”; comp-led). Do not literalize: the comp's step band (owner chose four steps: Check the fit, Choose a time, Walk me through it, Keep the report), “we're a good match”, or any prop detail beyond desk, blotter, plant, pen cup, notebook and pen, open brass cage, canary.

## Content and behavior

Keep: the Cal inline embed (`data-cal-inline`, namespace `walkthrough`) and plain `data-booking-fallback` Cal.com link; report contents with Where I'd start; the pitch-free promise; the three-business-day report; Bring the problem (consequence copy, eight-patterns link); the visible next-step choice (use it yourself, separate implementation purchase, or leave it; not a working implementation); the seven native FAQ disclosures including the fit disqualifier and the single unpriced paid-discovery-week sentence.

Fit card (`#choose-a-time`): three radio rows (Yes / Not yet). Only “I can talk to the people who do it” = Not yet stops: it replaces Show me times with the not-the-right-fit note and the eight-patterns link. Not yet on the other two shows a short honest note and still continues. Show me times (`data-booking-cta="walkthrough-hero"`, `href="#choose-a-time"`) flips the card to the inline calendar in place and moves focus to it. Without JavaScript the card shows the checks and the plain Cal.com link. Built as its own component so a stricter gate or a Cal routing form can replace its internals later. Booking placements stay header, mobile-menu, walkthrough-hero; existing event names unchanged.

## Finish evidence

Comp-led build (seed 5b3fd960): spec, plates (desk, canary), hero, sections, motion, responsive. Hero and responsive gates were forced only for the step band and header wordmark, after the owner explicitly downgraded the comp for those two regions; every other region is held to the comp (hero diff 86%). Independent finish review (degraded protocol, fresh subagent) returned fix; the canary plate was regenerated facing right, and the report bar geometry was corrected. Declines upheld: headline tracking (at the -0.04em floor), fit-sub color (the comp's gray fails contrast), shared nav pill (foundation). Verdict: ship, scoped to the six scored fixes. DESIGN.md records the page. Not owner launch approval.

## Unresolved

The copy for the two soft Not yet notes and the sections below the fold was drafted in the build; the owner reviews it.
