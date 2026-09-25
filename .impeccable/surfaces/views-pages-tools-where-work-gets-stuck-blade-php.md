---
version: 1
slug: "views-pages-tools-where-work-gets-stuck-blade-php"
primary_target: "resources/views/pages/tools/where-work-gets-stuck.blade.php"
related_targets: ["resources/css/stuck.css","resources/views/components/marketing/stuck-pattern.blade.php","resources/js/self-check.js"]
---

# Eight ways work gets stuck

## Scope

Visitor mode: **Persuade**. First lead-magnet page under `/tools/*`: a field guide of the eight pattern labels used verbatim in Walkthrough reports. The visitor recognizes their own week, reads those entries, and books a Walkthrough to learn which to fix first. Reports and the homepage story deep-link to each entry's anchor, so the eight slugs are permanent. Rebuilt 2026-09-25 inside Your business, in miniature.

## Direction contract

**THESIS:** The eight patterns as cards laid out on the owner's desk: pick one up and read it. Refuses the tips listicle and the abstract hub diagram.

**OWN-WORLD:** Daylight ground, ink, canary actions; the warm perspective desk plate (oak, green blotter, plant, stone pen cup, brass cage, coffee, notebook, canary); white 12px pattern cards with ground-colored number circles; Mona Sans; one Alkaline wordmark.

**STORY:** See the eight on the desk, pick up the ones that sound familiar, read their entries, book a Walkthrough.

**FIRST VIEWPORT:** Approved comp: three-line headline ~68px left, lead, canary booking button; the desk bleeding right with eight cards fanned on the blotter, card 7 lifted, the canary beside card 3.

**FORM:** Cards on the desk; my #6 of seven, dealt; seed bb7f0c7f.

**FINISH:** unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Approved comp

`.impeccable/mocks/decision/wbb7f-cards.png` (locked 2026-09-25 on the surface decision page, comp-led). The cards are real links in the page's type, never painted text.

## Content and behavior

Copy approved 2026-09-24 stays verbatim: title, lead, intro, the overlap note including “The scenes are composites, and the names are made up.”, all eight entries (definition, scene, You'll notice, What usually helps, the Craft & Communicate link on Assembled by hand), the interlude, and the close. `nav#map` holds the eight cards as an ordered list of links to the permanent anchors; each entry is `article#slug` with its heading, `.stuck-definition`, the signals list, and a link back to `#map`. Card 7 rests lifted; a hovered or focused card lifts; an entry reached from a card marks its number. Booking links go to /walkthrough (placements stuck-hero, stuck-interlude, stuck-close). DefinedTermSet structured data unchanged. Cards stay readable and in order without JavaScript, in reduced motion, and in print.

The owner intends this page to become a printable handout later (not part of this build), which is why the chosen structure is static cards rather than an interaction up top. Print must already read as a clean handout: no header, footer, desk art, or booking chrome; the eight cards as a flat numbered grid; every entry in full.

Close (owner request 2026-09-25): the self-check from the second structure becomes the closing call to action under “Recognize a few?”, keeping that section's approved copy. Chips list the entries' real “You'll notice” signals; ticking them fills a small dot per signal beside its pattern (dots, never a percentage or score) and orders the patterns by recognition; the button reads “Bring these to a free Walkthrough” (placement stuck-close). Without JavaScript the chips are a plain checklist; in print they are a printable checklist.

## Finish evidence

Comp-led build (seed bb7f0c7f). Card geometry: least-squares affine fits to the comp's OpenCV-detected card corners (max error 6px); every card matches the comp's ink box. The hero gate was forced only after the owner explicitly downgraded the comp for the cards' painted-versus-live lettering; the responsive gate passed without an override (81%). Independent finish review (degraded protocol): a recapture of the anchor-arrival shot (the earlier capture came from a restored scroll position; fresh deep links land 16px from the top and mark the number), then a full review: ship, no material fixes. Print already reads as a handout. DESIGN.md records the page. Not owner launch approval.

## Unresolved

The intro heading "The names I use in every report.", the self-check headings, and the summary line were drafted in the build; the owner reviews them.
