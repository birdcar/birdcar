# Copilot Instructions — birdcar.dev

This file mirrors the Design Context from `.impeccable.md` in the project root. That file is the source of truth; update it first, then reflect changes here.

## Design Context

### Users
Mixed technical audience — practicing engineers, engineering leads, and technically-adjacent readers who follow Nick's writing on software craft, systems, and tooling. Context of use: desktop and mobile, reading-first (long-form posts are the core product), occasional drive-by from a link in chat or an RSS reader. The job to be done is "read a coherent, trustworthy piece of writing by a specific engineer" — not "browse content." The site is secondarily a portfolio artifact, but audience and voice optimize for readers, not recruiters.

### Brand Personality
**Three words:** dry, opinionated, crafted.

**Voice:** direct, self-deprecating without being cute, technically confident without posturing. Short sentences do a lot of work. Eyebrow labels lowercase, headlines use italics for emphasis on a single key word ("That post is *hiding*", "Hi, I'm *birdcar*"). Acknowledges the reader as a peer — never explains what they already know, never condescends. Willing to take a stance ("Not on social. Sorry.") without apologizing for it.

**Emotional goals:** the reader should feel they've landed somewhere specific, built by a specific person, with a point of view. Not neutral, not generic, not trying to be liked by everyone. Confidence without volume.

**Voice posture:** directionally locked, but sharpenable. Existing About/404 copy is the reference register. When writing new copy, match that tone; when editing existing copy, a sharper edit is welcome if the existing line is flabby, performative, or fishing for likes.

### Aesthetic Direction
**Current visual system (inherited, not sacred):**
- Catppuccin Mocha (dark) / Latte (light), mode-aware via `prefers-color-scheme`
- Typography: Archivo (headings, sans), Literata (body, serif), JetBrains Mono (code)
- 8px spacing grid, 1.25 typographic scale, 42rem reading column inside a 72rem max container
- Subtle geometric SVG hero decoration (dots, crosses, lines) with reduced-motion guards
- Shiki `catppuccin-mocha` for code blocks
- Accent bar + floating keybind-driven search

**Catppuccin is the starting point, not a constraint.** Nick chose it because he likes it and it's familiar, not because the site requires it. A better-fitting palette is on the table if the rationale is strong (legibility, distinctiveness, emotional fit). Don't swap it on a whim, but don't protect it reflexively either.

**Anti-references — what this must NOT become:**
- Corporate SaaS marketing sites. No gradient heroes, no "unlock your productivity" copy, no social-proof carousels, no vague value props. This isn't selling anything.
- Aggressive minimalism or monochrome-for-taste. Color and personality are load-bearing. Stripping them out in the name of sophistication is the wrong answer.

**Visual tone:** editorial, not appy. Reading-first, with interaction chrome (search, keybinds, view transitions) kept quiet until invoked. Ornament exists but is restrained, faded, and mask-gradient-ed out of the reading zone.

### Design Principles

1. **The reading experience is the product.** Every visual decision should answer "does this help someone read a 2,000-word post?" before it answers anything else. Long line lengths, busy chrome, and decorative ornament inside the content column all fail this test.

2. **Specificity over neutrality.** Generic templates, SaaS polish, and "clean" minimalism are cheap. A site that's obviously *one person's* site — with typography, color, and copy choices that wouldn't ship at a company — is the brief. When torn between a safe choice and a specific one, pick specific.

3. **WCAG AA is the floor, everywhere, no exceptions.** Every color pair in text and interactive UI passes 4.5:1 (3:1 for large text and non-text elements). Every interaction works without a mouse. Every motion respects `prefers-reduced-motion`. Semantic HTML first, ARIA only when markup can't say it. Decorative SVGs (the hero ornament) are `aria-hidden` and fall under "decoration" — contrast rules don't apply, but they still respect reduced-motion.

4. **Ornament fades; content doesn't.** Decorative elements (hero dots, accent bar, background geometry) use opacity and mask-gradients to recede. They earn their place by framing, never by competing. If ornament ever has to fight the content for attention, delete the ornament.

5. **Voice is design.** Copy is not a later layer. Eyebrows, labels, button text, empty states, and console easter eggs all carry the same register as long-form posts. Don't let UI chrome drift into generic product-copy ("Click here to continue"). Write it the way Nick would write it — short, specific, occasionally dry.

6. **Nick is not a designer.** When presenting design options, lead with a clear recommendation and the one tradeoff that matters. Don't hand over a neutral menu of equivalents. If there's a right answer, say so.
