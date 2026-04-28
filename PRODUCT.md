# Product

## Register

brand

## Users

**Primary audience.** A non-technical small-business owner (CPA, mortgage broker, realtor, property manager, or local agency operator running a $1–20M business) who just got a referral and is checking that Birdcar is real and competent. They will not read long copy. They are deciding, in roughly thirty seconds, whether to email Nick. They need to feel: this person is legitimate, careful, and sends emails back. Their context of use is desktop or phone, on a coffee break, between two other tasks they would rather not be doing.

**Secondary audience.** Peers in dev / AI / solutions-engineering who already read the writing, plus the occasional drive-by from a link in Slack, RSS, or a Mastodon thread. They should feel at home; the technical content reinforces credibility for the primary audience without alienating them. The job to be done is reading a coherent, trustworthy piece of writing by a specific engineer, not browsing content.

The site is, in priority order: (1) a referral-trust artifact for the consulting practice, (2) a long-form writing surface, (3) a portfolio. Audience and voice optimize for the first two.

## Product Purpose

Birdcar is the personal-site and consulting-practice brand for Nick Cannariato, a Fort Worth-based product engineer and back-office automation consultant. One person, two adjacent identities: a product engineer and technical writer (currently a solutions engineer at WorkOS), and a part-time consultant building internal tools and back-office automations for small businesses ($100k–$2M revenue range, project-based, 4–12 weeks, fixed scope).

The site exists to pass a single thirty-second referral test: a CPA who got Nick's name from a friend should land on the home page and feel like emailing him. Everything else follows from that.

## Brand Personality

**One line.** Competent operator who writes well.

**Three words.** Dry, opinionated, crafted.

**Voice.** First person singular ("I build internal tools"). Direct second person to the reader ("You probably got my name from someone"). Never "we." No royal we. No third-person bio voice on the site itself; that's for someone else's blog post about Nick.

**Sentence shape.** Short. Then occasionally a longer one that earns the space because it's making a real point. Cut every adverb that isn't load-bearing. Cut "really," "very," "just," "simply." Cut "I think" unless the disclaimer is doing real work.

**Casing.** Sentence case for everything: headings, buttons, navigation, page titles. Title Case Looks Like A Brochure. The exception is proper nouns and the brand mark `Birdcar` (capital B).

**Banned hype words.** *leverage, empower, unlock, transform, seamless, robust, cutting-edge, innovative, AI-powered, world-class, best-in-class, solutions, synergy.* If a sentence stops working when you remove the hype word, the sentence wasn't working.

**Concrete over abstract.** Not "I help businesses streamline operations." Yes "I built a CPA firm a tool that pulls 80 client returns out of Drake into a single review queue." Always reach for the specific example.

**Opinions, lightly held.** Have a take. *"Most small-business software is sold by salespeople to people who don't have time to evaluate it. The result is usually fine and rarely good."* Don't hedge into nothing.

**Dryness, sparingly.** A small amount of dry humor is on-brand. *"I am, against my better judgment, on LinkedIn."* Seasoning, not the meal.

**Emoji.** No. Not in copy, not in headings, not in nav. The one allowable exception is a literal use inside a code block or a quoted message.

**No em dashes.** Use commas, colons, semicolons, periods, or parentheses. Also not `--`. The em dash is the un-edited-writing tell; cutting it is a discipline.

**Metadata is content.** Post dates are visible and matter. Mono dates are intentional; they signal "this is a record." "5 min read" not "a quick read."

**Emotional goal.** The reader should feel they have landed somewhere specific, built by a specific person, with a point of view. Confidence without volume.

## Anti-references

- **Corporate SaaS marketing sites.** No gradient heroes, no "unlock your productivity" copy, no social-proof carousels, no vague value props.
- **Developer-cutesy or startup-bro.** No emoji rockets in headings. No "🚀 Built with X" footer flexes. No `~/$` terminal prompts as decoration.
- **Generic editorial template.** The system has specific tokens and specific rules; "tasteful minimal blog" without those tokens is the wrong outcome.
- **Catppuccin / dark-mode coding-blog aesthetic.** The previous site used Catppuccin Mocha + Archivo + Literata; that whole register is retired. Don't reach for it.

**Reference touchstones for spirit, not for copying.** Craig Mod's writing site, Robin Sloan, Simon Willison's blog, Pinboard, Nat Eliason. Editorial, dense with real content, restrained color, generous typography.

## Design Principles

1. **The referral test.** Every page above the fold answers: would a CPA who got Nick's name from a friend feel like emailing him after thirty seconds here? If a piece of chrome, copy, or ornament does not help that decision, it earns its place some other way or it is cut.

2. **The reading experience is the product.** Long-form writing is the second-most-important thing on the site. Body and prose run on a single 720px (~76ch) column so head and body left rails align across every page; figures, code blocks, and embeds fit inside that same column rather than breaking out. Line height is 1.55; paragraph rhythm is 1em. The width sits in the upper-acceptable band of digital reading research (Butterick 45–90ch, WCAG AAA ≤80ch) — wider than print convention on purpose, because the alternative was 130px+ of asymmetric inset on chrome pages that hurt cross-page reading more than the extra characters on a line. If a visual decision makes a 2,500-word post harder to read, undo it. See `DESIGN.md` for the full rationale.

3. **Specificity over neutrality.** A site that is obviously *one person's* site, with typography, color, and copy choices that would not ship at a company, is the brief. When torn between a safe choice and a specific one, pick specific. The clay accent, the warm paper, mono dates, and the Phosphor-over-Lucide call are all examples of specificity earning its keep.

4. **Hairlines, not boxes; ink, not chrome.** Hierarchy is communicated by typography, spacing, and 1px rules, not by cards, shadows, or fills. If a thing needs a box to feel important, the typography is wrong. The rule applies to writing surfaces (post lists, work patterns, tag indices) and to UI affordances (form fields, the filter bar, callouts). Width-tier figures inside posts may break out of the prose column, but they still carry only a baseline rule, never a full box.

5. **Voice is design.** Eyebrows, button labels, empty states, 404 copy, console easter eggs, and footer microcopy carry the same register as long-form posts. Do not let UI chrome drift into generic product-copy ("Click here to continue"). Write it the way Nick would write it: short, specific, sentence-case, occasionally dry. The 404's "That post is hiding. And winning." is the bar.

6. **Nick is not a designer.** When presenting options, lead with a clear recommendation and the one tradeoff that matters. Do not hand back a neutral menu of equivalents. If there is a right answer, say so. The design system has hard rules for a reason; push back on them only with a real argument, not a preference.

## Accessibility & Inclusion

WCAG AA is the floor. Every color pair in text and interactive UI passes 4.5:1 (3:1 for large text and non-text elements). The `--ink` / `--paper` and `--clay` / `--paper` pairs both exceed AA at body size; verify any new pairing before shipping. Every interaction works without a mouse. Visible focus rings (`outline: 2px solid var(--clay)`) on every interactive element. Every motion respects `prefers-reduced-motion`. Semantic HTML first, ARIA only when markup cannot say it. Decorative ornament (the double-rule, the portrait fallback) is `aria-hidden`.
