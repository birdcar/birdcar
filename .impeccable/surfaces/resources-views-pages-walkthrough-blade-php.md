---
version: 1
slug: "resources-views-pages-walkthrough-blade-php"
primary_target: "resources/views/pages/walkthrough.blade.php"
related_targets: ["resources/css/marketing.css","resources/js/interactions.js","resources/js/booking.js","resources/views/components/marketing/layout.blade.php","resources/views/components/marketing/booking-link.blade.php"]
---

# The Walkthrough

## Scope

Visitor mode: **Persuade**. This page implements the user-selected **Future, in person** world from 2026-09-13. PRODUCT.md owns approved content and offer; DESIGN.md records the built visual system.

## Direction contract

**THESIS:** Silicon Valley technical capability, made personally accessible through a clear typographic presentation.

**OWN-WORLD:** Black cherry openings, pale orchid reading space, lilac actions and explanatory fields, aubergine support; licensed Alkaline, Karla, and restrained Commit Mono. One Birdcar wordmark in the shared header.

**STORY:** Recognize work that keeps coming back to the owner, understand the free conversation, its pitch-free promise, and the useful report, resolve objections, and choose a time.

**FIRST VIEWPORT:** A black cherry opening pairs the Alkaline question and readable personal offer with a level pale report-contents panel. The booking action is lilac. The panel carries no logo.

**FORM:** Future, in person, seed `537c5794`; the homepage's approved Direct conversation composition anchors the public world.

**FINISH:** The earlier public-site finish review established the shared visual direction. The 2026-09-18 interaction pass verified desktop/mobile disclosures, keyboard focus restoration, rapid toggling, reduced motion, and no-JavaScript fallbacks. The marketing feature tests and interaction tests passed; this was not a new visual-fidelity review.

## Offer and content

The page lives at `/walkthrough`, named route `public.walkthrough`. `/assessment` and `/contact` redirect permanently to it. The offer is **The Walkthrough**, not a free assessment.

The opening asks “Does it all come back to you?” and pairs the personal explanation with a level report-contents panel. A free, roughly one-hour conversation produces a written report within three business days. The hour is pitch-free: “If you want to talk about hiring me, you’ll bring it up, not me.” The visitor keeps the report without buying implementation.

The report panel describes “What’s happening,” “What I recommend,” and “Where I’d start”: the first change and why it comes first. It uses Karla contents and the Alkaline signoff “Yours to keep.” It describes the offer, not a fabricated client report.

The sequence continues through recognizable operational problems, the three meeting steps (choose a time, walk through the work, keep the report), seven native FAQ disclosures, and the closing inline calendar. Questions cover cost, preparation, fit, next steps, what an hour can reveal, AI, and the effect on the team. Access to the people doing the work is a fit requirement. Implementation is a separate purchase; the paid discovery week receives one unpriced sentence as the deeper version of the same process.

## Booking and navigation

Booking uses `https://cal.com/birdcar/walkthrough`, calendar link `birdcar/walkthrough`, namespace `walkthrough`, through the marketing configuration. The calendar is embedded inline in the final aubergine section (`#choose-a-time`) inside a level paper panel. Booking controls on this page, including the header and mobile menu, link to that section rather than opening an overlay. A plain “book on Cal.com” link remains beneath the calendar when JavaScript or the embed is unavailable.

Elsewhere on the public site, booking controls open the same calendar in an overlay and retain the Walkthrough page as their ordinary-link fallback. The shared desktop and mobile navigation identify this page with `aria-current="page"`.

## Responsive behavior and motion

On mobile, the offer precedes the report panel, the primary booking action is full width, and the meeting steps and FAQ stack in document order. Preserve the existing typography, flat surfaces, and two-color button focus treatment.

Motion acknowledges interaction rather than withholding content:

- FAQ answers enter over 200ms with a small upward offset settling to zero and partial opacity resolving to full opacity. Arrows rotate over 220ms to communicate open/closed state; closing the answer is immediate. Native `details` and `summary` remain functional without JavaScript.
- The mobile menu opens over 220ms with a bounded clip, small offset, and opacity change; its two-line icon becomes a cross. Closing is immediate. Navigation, outside click, and Escape dismiss it; Escape restores summary focus. Switching to desktop clears the mobile menu's open state.
- Buttons, directional arrows, and navigation underlines share restrained feedback. Spatial feedback uses a no-bounce spring with approximately 200ms perceived duration and 400ms settling time. Button presses compress slightly over 100ms; keyboard focus receives equivalent directional feedback without losing its outline. Hover-only effects are limited to fine pointers that support hover.
- Reduced motion removes transitions, animation, smooth scrolling, and decorative spatial feedback while preserving readable content, focus indicators, color feedback, and the final disclosure/menu icon states.

This page has no opening choreography, repeated scroll reveals, or autonomous loops. The homepage's type-and-horizon entrance stays specific to the homepage. All offer content remains server-rendered and usable independently of animation.

## Constraints

Preserve first-person singular voice and the approved promise of a free conversation and report within three business days. Use consequence-based urgency only. Do not introduce scarcity, capacity claims, prices, invented outcomes, a VSL, a custom booking form, or a promise that implementation is included.
