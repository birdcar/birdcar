---
name: Birdcar — Make room
description: A personal visual identity built from expressive lettering, decisive color, and room for real thinking.
---

<!-- SEED: established with the user before implementation; re-run $impeccable document once there's code to capture the actual tokens and components. -->

# Design System: Birdcar

## Overview

**Creative North Star: “Make room.”**

This is a greenfield design. Existing application boilerplate carries no visual
or component authority and may be replaced to realize this direction.

Birdcar is a person whose expertise, voice, and vision are the offering. The
design gives that person a recognizable presence: confident Alkaline lettering,
broad color fields, asymmetric panel cuts, and quieter space for developed ideas.
The selected world draws its graphic rhythm from jazz title cards. Its subject
is the owner's perspective and the real work of helping people run their businesses.

Marketing uses custom UI. Admin and the customer project site share Flux UI Pro,
customized as needed, with Inter and consistent operational conventions. Translate
the identity through deliberate color and typography choices suited to each
surface's work. Keep the two operational surfaces visually consistent.

The reusable signature is expressive lettering crossing a decisive field of
color, balanced by calm, plainly written explanation. Imagery may use deliberate
black silhouettes and cut-paper forms to clarify a situation. Pictures of people
must not imply an invented Birdcar team; the owner and a client's team are distinct.
Real portraits and work artifacts must be authentic and available before use.

Optional motion follows the panel geometry through brief directional transitions.
Content is readable at rest; reduced motion preserves the complete experience.
Keep sound opt-in and only introduce it if a future surface actually calls for it.

## Colors

Use substantial, ranked fields of color, with quiet reading areas between them.
The selected palette establishes these roles:

- **Primary: Mustard (#E0B23C).** The dominant expressive marketing field.
- **Secondary: Brick (#B23A2E).** Emphasis and distinct action or story panels.
- **Secondary: Teal (#146B6E).** Directional bars and supporting contrast.
- **Neutral: Cream (#F1E6CF).** Reading areas and light text on the darker fields.
- **Neutral: Ink black (#0E0E0E).** Principal text, silhouettes, and structural marks.

Use ink on mustard or cream, and cream on brick or teal. Verify contrast after
texture, states, and actual type sizes are implemented. Color never carries status
or interactivity alone. Operational theme ramps, state colors, and dark-mode
values are [to be resolved during implementation].

## Typography

Use the bundled **Alkaline and Alkaline Caps** assets for the marketing logo and
display roles. The connected, forward-moving Alkaline lettering carries the main
expressive voice. Use **Barlow** for marketing body text, navigation, and controls;
a different compatible body face requires a concrete reason. Use **Inter** for
Admin and customer UI text.

Display establishes hierarchy in a few readable words. Body text has enough space
for the owner's reasoning and stories. Preserve full words, clear reading order,
and comfortable measures. Keep forms and small labels straightforward. Generated
previews approximate the fonts; the bundled assets govern implementation.

Exact sizes, weights by role, line heights, and responsive scales are
[to be resolved during implementation].

Long-form essays use the marketing body face with a comfortable measure, generous
leading, and a clear hierarchy for headings, links, quotations, lists, and code
where needed. Use Alkaline selectively for article titles and expressive moments;
the prose must remain comfortable to read at length.

## Layout

Marketing alternates expressive asymmetric fields with quieter aligned passages.
Give each region a dominant idea and a clear relationship between its heading,
explanation, imagery, and action. Layout must accommodate personal writing without
forcing it into interchangeable cards or slogans.

On narrow screens, reflow the composition into its natural reading sequence.
Simplify supporting rails, preserve complete words and actions, and move or reduce
secondary imagery. Do not shrink a desktop composition into a phone viewport.
The first homepage composition is recorded in its surface brief, not prescribed
for every page. Operational layouts follow shared Flux conventions for their tasks.

Grid dimensions, spacing scales, and breakpoints are [to be resolved during implementation].

Writing is a first-class marketing destination. Article indexes support finding
and choosing a piece; individual articles favor a continuous reading column with
quiet surroundings. Future video belongs coherently alongside authored writing.
Reading pages share the identity without inheriting the homepage's large panel
composition or repeating its sales invitation through the prose.

## Elevation & Depth

The marketing world is primarily flat. Color boundaries, overlap, and controlled
print grain provide depth. Keep text and controls clear of texture that reduces
legibility. Operational screens use clean surfaces and depth only where it helps
explain a real layer or state. Exact elevation values remain unresolved.

## Shapes

Straight edges, rectangular fields, and a limited vocabulary of angled cuts define
the marketing composition. Directional bars frame content without slicing through
letters or controls. Controls retain recognizable boundaries and generous usable
areas. Operational forms and tables use consistent Flux shapes. Exact corner,
border, and clipping values remain unresolved.

## Do's and Don'ts

- **Do** make the owner's perspective and presence visible throughout the site.
  Follow the first-person voice commitments in `PRODUCT.md`.
- **Do** preserve the chosen palette's energy while giving explanations room.
- **Do** keep Admin and customer interfaces consistent through shared Flux UI Pro
  conventions and Inter.
- **Don't** imply a corporation, an invented delivery team, or anonymous expertise.
- **Don't** carry forward the former Astro identity, marketing, or offer.
- **Don't** invent testimonials, results, endorsements, or client imagery.
- **Don't** treat catalog typography, entertainment motifs, or preview copy as
  authority over the owner's fonts, voice, and product truth.
