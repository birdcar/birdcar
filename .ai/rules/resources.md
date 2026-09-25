---
paths:
  - 'resources/**'
---

# Resources

## Personal brand and three surfaces
Use PRODUCT.md for product truth and written voice, DESIGN.md for the chosen visual direction, and the matching Impeccable surface brief for page strategy. Marketing is custom UI for a person: use I/me/my, make the owner's expertise and perspective visible, and include a first-class Writing section. Study the owner's essays for voice only; discard the former Astro marketing and offer. Admin and customer surfaces both use Flux UI Pro, customized consistently, with Inter. Migrated marketing pages use Mona Sans (--font-studio); pages not yet migrated still use Barlow. Licensed Alkaline appears only as the single native-tracked header wordmark.

## Approved marketing launch scope
The homepage hero is “Better work, faster. Without another hire.” (PRODUCT.md holds approved copy); experience copy says “fifteen years.” Public work features Craft & Communicate only; omit GHX pending contractual permission, DataDash as the title, and retainer/build status. Do not promote archive essays from the homepage. Writing launches with the complete original archive, preserving text, titles, original dates, URLs and links; figures may be art-directed while retaining data and meaning. The Writing introduction focuses on the core business. The Walkthrough (the renamed free assessment) lives at /walkthrough with permanent redirects from /assessment and /contact, books through https://cal.com/birdcar/walkthrough, and promises a written report within three business days and a pitch-free hour; name past employers GitHub, Heroku, Zapier, Twilio, and Salesforce only (official monochrome marks allowed, framed as work history; never Apple or WorkOS); the paid discovery week gets one unpriced sentence; no invented scarcity, results, or VSL.

## Retired visual guidance
The 2026-09-13 Future, in person identity is superseded, not a second design option: do not restore its purple/horizon palette, Karla body, script display headings, or mono labels. The owner approved The clear argument Phase 1 foundation, including Barlow, on 2026-09-19. That decision is not approval of later pages or launch; acceptance.md records the actual owner gates.

## The clear argument, legacy pages only
The cyan fields, blue-green ink, and Barlow hierarchy remain only on pages not yet migrated to Your business, in miniature; do not extend them to new work. Commit Mono is for code. The legacy authored figures retain complete static HTML/SVG meaning in marketing, articles, reduced motion, no-JavaScript and print. PRODUCT.md owns unchanged offer/proof truth; DESIGN.md describes the built system; current page briefs own strategy. Local print specimens are not a public PDF feature. Agent QA never substitutes for the owner launch gate. The legacy booking_embed_opened event is Cal linkReady/readiness, not a visitor-open funnel step; preserve existing event names and the completion-property allowlist.

## Your business, in miniature replaces The clear argument
On 2026-09-25 the owner retired The clear argument (cyan fields, Barlow, flat line diagrams) for marketing and approved "Your business, in miniature": pale daylight ground, ink, canary-yellow actions and packets, premium matte isometric renders, Mona Sans (--font-studio), licensed Alkaline wordmark at weight 600 once per page. The shared foundation (tokens on .marketing-page, header, nav, mobile menu, footer, studio components) lives unlayered at the top of resources/css/marketing.css, because its media queries must beat the legacy @layer components rules; each migrated page adds its own unlayered file (home.css, walkthrough.css) and deletes its legacy rules. The homepage, Walkthrough, Work, and the where-work-gets-stuck tool are migrated; Writing page bodies still run the retired system until migrated. New pages extend the new world, never the retired one. DESIGN.md and the homepage surface brief own the details; rasters ship from public/images/home with provenance from .impeccable/assets/plates.

## All booking roads lead to the Walkthrough fit check
Owner decision 2026-09-25: no booking button opens Cal in place. x-marketing.booking-link is a plain link to /walkthrough (or #choose-a-time on that page). The only Cal surface is the inline calendar inside the fit card on /walkthrough; booking.js loads Cal only when fit-check.js dispatches booking:open after the three checks pass. Only "I can talk to the people who do it" = Not yet stops booking; keep the fit card a self-contained component so stricter qualification (e.g. a Cal routing form) can replace its internals. Cal colors are set in code from the studio tokens, never the Cal dashboard. Keep booking_cta_clicked placements and existing event names.
