# Story Circle for Technical Blog Posts

The Story Circle (adapted from Dan Harmon's narrative framework) maps naturally to technical writing. Every good post takes the reader on a journey from where they are to somewhere new.

## The 8 Steps

### 1. You (Comfort Zone)

Establish where the reader is right now. Ground them in familiar territory.

**In a post**: "You're a developer who deploys to production. You write tests. Things mostly work."

**Section approach**: 1 H2 section, 1-2 paragraphs. Use relatable scenarios. Keep it brief — the reader knows their own world. Opening paragraph density is low; you're establishing shared ground, not front-loading information.

**Writing techniques**: Opening hook paragraph in second person that lands the reader in the familiar world. Blockquote from a recognizable authority or a shared frustration, if the moment earns it.

**BFM usage**: Minimal. This section should feel light and conversational. An `@aside` here would add weight the section doesn't need yet.

### 2. Need (Desire)

Introduce the gap, problem, or desire. Why should they care?

**In a post**: "But when things break at 2am, you're flying blind. Logs are scattered, metrics don't correlate, and the on-call runbook is from 2019."

**Section approach**: 1 H2 section, 2-4 paragraphs. Make the pain concrete. Use specific examples, not abstract problems. Pacing is short and punchy — the need should land fast. End with the sharpest version of the problem.

**Writing techniques**: Bulleted list with progressive argument to build up pain points. `@aside` for a stark, memorable problem statement — a statistic or a quote that makes the need undeniable.

**BFM usage**: An `@aside` works well here when you have a single striking data point that shouldn't be buried in prose. One only.

### 3. Go (Unfamiliar Situation)

The first step into the unknown. What did you (or the industry) try first?

**In a post**: "So we started looking at observability platforms. We tried the obvious stuff first."

**Section approach**: 1 H2 section, 2-4 paragraphs. This is the "we ventured forth" moment. Keep it moving — the reader should feel the shift into exploration without being slowed down by too much detail. This is a transition, not the core.

**Writing techniques**: Code block for initial attempts when showing the first approach is more credible than describing it. Comparative paragraphs for contrasting the approach taken vs. the alternatives considered.

**BFM usage**: Keep BFM light here. This section is setting up the search, not doing the teaching.

### 4. Search (Adaptation)

The deep dive. What approaches were tried? What was learned along the way?

**In a post**: "We evaluated three approaches. Each taught us something, but none were quite right."

**Section approach**: 2-3 H2 sections (or one with H3 subsections per approach), 3-6 paragraphs per section. This is where the post's depth lives. Multiple examples, code blocks, and analysis belong here. Highest paragraph density in the post. Don't rush — readers in Search are engaged and want the texture.

**Writing techniques**: Code blocks with explanatory paragraphs for each approach evaluated. `@details` for extended code examples that support but don't drive the argument. Bold summary paragraph at the end of each approach: what it taught you before moving to the next.

**BFM usage**: `@details` is at home in Search — this is the technical deep end. Reserve `@aside` for the "here's what we didn't expect to learn" moments. Max 1-2 asides across the whole Search section.

### 5. Find (Discovery)

The breakthrough moment. The "aha!" that changes everything.

**In a post**: "Then we realized the problem wasn't our tools — it was our mental model."

**Section approach**: 1 H2 section, 2-3 paragraphs. This should feel like a revelation. The section should be shorter than Search — its brevity is part of its impact. Consider ending with the insight as a blockquote rather than embedded in a paragraph.

**Writing techniques**: Blockquote for the key insight — let it stand at full typographic weight. Short, declarative paragraphs leading up to and following the insight.

**BFM usage**: A blockquote is the primary tool. Do not wrap the discovery in an `@aside` — asides are tangential, and this is the center of the post.

### 6. Take (Consequence)

Nothing is free. What are the tradeoffs, costs, or challenges of the solution?

**In a post**: "This approach works, but it requires rethinking how we structure our services."

**Section approach**: 1 H2 section, 2-4 paragraphs. Honest tradeoff assessment builds credibility. Don't skip this. Medium paragraph density — you're not expanding, you're being honest. A table or comparative paragraphs work well.

**Writing techniques**: Comparative paragraphs or a table for pros/cons. `@aside` for gotchas that aren't immediately obvious from the solution itself. Bold summary paragraph for the tradeoff summary.

**BFM usage**: An `@aside` for a non-obvious caveat ("the thing nobody mentions") is appropriate here. One maximum.

### 7. Return (Familiar)

Bring it back to the reader's world. How do they apply this?

**In a post**: "Here's how you can start with this approach in your own codebase, today."

**Section approach**: 1 H2 section, 2-4 paragraphs. Practical and actionable. The register should shift slightly — from narrative to guidance. Use second person again to mirror Step 1.

**Writing techniques**: Short bulleted list for action items the reader can take immediately. Code block for "getting started" commands or starter code. The voice should return to the direct address of Step 1 — you're back in the reader's world, not the writer's story.

**BFM usage**: Keep it clean here. This section should feel like a clear handoff, not a sidebar.

### 8. Change (Transformed)

The reader is different now. Land the key takeaway.

**In a post**: "You came in thinking about monitoring. You're leaving thinking about observability as a design principle."

**Section approach**: 1 H2 section, 1-3 paragraphs. Circle back to Step 1 but show what's changed. End with the key message. The closing paragraph should echo the opening in language or image — the reader should feel the arc complete.

**Writing techniques**: Bold summary paragraph for final takeaways that can't be compressed further. Closing paragraph that explicitly mirrors the opening of Step 1.

**BFM usage**: No BFM directives in the final section. Land cleanly.

## Length Tier Mapping

### Short (800-1500 words)

Compress to three beats:
- **Need** (150-250 words): State the problem sharply
- **Find** (300-600 words): Show the solution with one clear example
- **Change** (150-250 words): Key takeaway + call to action

Skip You, Go, Search, Take, Return. No room for the journey — go straight to the point.

### Medium (1500-3000 words)

Use all 8 steps but keep Steps 1, 7, 8 tight:
- Steps 1-2 (200-400 words): Establish + problem
- Steps 3-4 (600-1200 words): Exploration + depth
- Steps 5-6 (350-700 words): Solution + tradeoffs
- Steps 7-8 (250-500 words): Application + conclusion

### Long (3000-5000 words)

Full depth on every step. Expand:
- Multiple examples in Search (Step 4) — 3-4 H2 sections
- Full implementation detail in Find (Step 5) with code
- Tiered action items in Return (Step 7) — beginner, intermediate, advanced
- Extended Change (Step 8) that revisits specific language from Step 1

## When to Break the Framework

**Deep-dive technical posts**: Expand Steps 3-5 into a full investigation arc. Compress Steps 1, 7, 8 to paragraphs rather than full sections.

**Tutorial or how-to format**: Replace Steps 3-5 with Concept → Example → Exercise loops. Multiple mini story circles.

**Announcement or launch posts**: Steps 1-2 are the current state, Step 5 is the announcement, Steps 6-8 are adoption guidance. Steps 3-4 are "how we got here" — optional backstory that can be compressed or skipped.

**Problem-focused posts (debugging, postmortems)**: Expand Steps 2-4 (the problem space). Steps 5-6 become the resolution. Step 7 is "how to prevent this."

## Example Mapping

### Support Engineering Post

| Step | Content |
|------|---------|
| You | "You handle escalations every day" |
| Need | "But complex issues take too long to resolve" |
| Go | "We tried adding more docs, more training" |
| Search | "What actually slows resolution? Data from 500 tickets" |
| Find | "Pattern recognition beats documentation" |
| Take | "Requires investment in tooling and taxonomy" |
| Return | "Three changes you can make this sprint" |
| Change | "From ticket-taker to pattern-spotter" |

### Technical Deep-Dive

| Step | Content |
|------|---------|
| You | "You use TypeScript every day" |
| Need | "But your types don't catch the bugs that matter" |
| Go | "What if types could encode business rules?" |
| Search | "Branded types, template literals, conditional types" |
| Find | "Combining these creates a compile-time safety net" |
| Take | "More complex types, steeper learning curve" |
| Return | "Start with these 3 patterns in your codebase" |
| Change | "From 'types as documentation' to 'types as validation'" |
