# Blog Voice Guide

This file contains blog-specific voice rules for birdcar.dev posts. It layers on top of Nick's base voice profile.

**Base voice**: Load `~/.config/bat-kol/style.md` first for Nick's core writing style (sentence craft, word choice, structural preferences, anti-slop rules). Everything in that file applies to blog posts. This file adds blog-specific patterns.

**Anti-AI rules**: The writer-editor agent handles humanization using `humanizer-rules.md`. You don't need to memorize the blacklist during generation, but you should internalize the general principle: be specific, be direct, be human.

## Perspective & Point of View

Blog posts switch perspective deliberately between sections:

- **Second-person "you"** for scenarios and reader immersion: "You're in support at Big Tech Company. You got this job after leaving Starbucks five years ago..."
- **First-person "I"** for personal experience, opinions, and direct address: "I think about death kind of a lot", "I find myself nodding along and doubting myself"
- **Collective "we"** when speaking as an industry: "We look at first reply times, ticket resolution times..."

Switch between perspectives within a post. Second-person for scenarios, first-person for commentary, "we" for shared frustrations. Each switch should be deliberate, not accidental.

## Tone & Register

Blog posts use a specific register that's distinct from Slack, email, or GitHub:

- **Informal**: Contractions always. Conversational asides. Occasional profanity when it serves emphasis ("Your metrics are bullshit").
- **Not academic**: Even when citing academic sources (Goodhart's Law, Campbell's Law), explain them conversationally. Quote the source, then translate: "Or more simply:"
- **Confident opinions**: "It's an astounding theft", "This is exploitative bullshit", "Joy matters, and increasing it is enough of a goal in and of itself"
- **Genuinely acknowledges counterarguments**: "It pains me to admit that I think Brian is *ultimately* right here", "I hear you all nodding your heads while saying, 'Yes but...'"
- **Never hedges**: No "I think perhaps maybe we should consider..." Instead: "I am instead suggesting that we look at data within a larger context."

## Sentence Craft (Blog Extensions)

The bat-kol style.md covers the Fish framework broadly. These rules extend it for long-form blog writing:

### The Three Sentence Modes

Vary deliberately between these modes within a post. The choice is a worldview choice about the relationship between the writer's mind and reality:

- **Subordinating**: One idea governs; others depend from it. Creates hierarchy, control, argument. Use for analytical sections. "If you've ever wondered why your support team seems to be drowning despite hitting every metric your VP cares about, the answer is that those metrics were never designed to measure what matters."
- **Additive**: Clause added to clause, building through accumulation. Creates rhythm, momentum, the feel of experience unfolding. Use for narrative sections and scenarios. "You open the queue. You see forty tickets. You start triaging. You realize half of them are the same bug."
- **Satiric**: Structure sets up an expectation, then deflates or subverts it. Creates ironic distance. Use for calling bullshit. "They called it a 'customer success platform.' It succeeded at exactly one thing: generating dashboards."

### The Turn

Every admirable sentence has a pivot. Nick's version: "Like many catastrophes, it comes in the guise of good news: a $100M A-round!" The turn on "good news" reframes the A-round as catastrophe before the reader has time to celebrate.

Build turns into sentences, especially at section boundaries.

### First and Last Sentences

The highest-leverage positions in any section:

- **First sentences** establish a world and create hunger for what follows. Fish's "angle of lean": a first sentence inclines toward elaborations it anticipates. "I think about death kind of a lot" leans toward everything that follows without announcing it.
- **Last sentences** must land, not just stop. They should carry full rhetorical weight even extracted from context. A last sentence should work as a final statement for someone who hasn't read the rest.

### Enacting Form

When describing chaos, let the sentence feel chaotic (additive accumulation, list structure). When describing clarity, let the sentence be clean (short, subordinating). When describing a doom cycle, let the sentence structure repeat and escalate. The form should mirror the content.

## Rhetorical Devices

- **Italics for emphasis and internal monologue**: "*The queue continues to explode.*"
- **Repetition for rhythm**: Repeating a phrase to build momentum. "*The queue continues to explode*" appears three times, each time the context worsens.
- **Rhetorical questions as transitions**: "How much more horrifying is it, then, that our tools often aggravate rather than alleviate this reality?"
- **The callback**: Returning to an earlier scenario or phrase with new meaning: "You tell them they need to 'do more with less.'" (echoing the opening)

## Blog Structural Patterns

- **Open with narrative, not thesis**: Start with a scenario, anecdote, or provocative observation. The argument comes after the reader is hooked.
- **Long-form paragraphs that build momentum**: 4-8 sentences, each adding weight. Short paragraphs for punchlines.
- **Section headers are punchy statements**: "Welcome to the doom cycle", "You're not gonna make the world a better place", "Are you the fuel? Or the operator?"
- **Sections alternate narrative and analytical**: Story → argument → story → prescription. Never stack 3+ analytical sections.
- **Closing sections circle back**: Return to the opening scenario with resolution, or issue a direct challenge.

## Argumentation Style

- **Extended scenarios** the reader recognizes: Build a multi-paragraph story that mirrors lived experience before naming the problem
- **Academic citations** explained conversationally: Quote the source formally, then translate into plain language
- **Genuine counterargument engagement**: Not strawmen. Acknowledge the strongest version of the opposing view.
- **"Yes, but" structure**: Name the obvious objection, then reframe it.
- **Concrete prescriptions**: End with specific, actionable steps. No vague advice.

## BFM Usage Patterns

- **`@aside title="Epigraph"`** at the top of a post for a thematic quote that sets the tone
- **`@aside title="About this post"`** for editorial context, attribution, or publication history
- **Standard blockquotes** (`>`) for authoritative citations with `-- Author (Year. Title)` attribution
- **Extended task lists** for actionable takeaways where bullet points would be too flat:
  - `- [!] Priority item` for critical takeaways
  - `- [ ] Action item` for concrete things the reader should do
  - `- [x] Completed/resolved item` for showing progress in narrative
- Use `@aside` sparingly (one at the top of a post is common, more than two is unusual)
- Use `@callout` for warnings or tips that would break narrative flow (0-3 per post)
- Use `@details` for optional deep-dives (0-2 per post)

## Voice Calibration Anchors

When uncertain whether a passage sounds like Nick, read specific sections from these existing posts:

- `src/content/blog/your-metrics-are-bullshit.md` (long-form jeremiad, extended scenario, academic citations)
- `src/content/blog/joy-matters.md` (personal/philosophical montaigne, mortality framing, company values)
