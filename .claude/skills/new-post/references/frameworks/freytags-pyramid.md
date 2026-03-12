# Freytag's Pyramid for Technical Blog Posts

Freytag's Pyramid comes from Gustav Freytag's 1863 analysis of dramatic structure. It breaks narrative into five phases: Exposition, Rising Action, Climax, Falling Action, and Denouement. What makes it distinct from simpler frameworks is the explicit *descent* after the peak — the falling action and resolution that give the reader time to process the climax and understand its implications. For technical posts, this works best when your writing has a clear dramatic peak: a breakthrough, a reveal, a "the data showed us something we didn't expect" moment. The pyramid structure ensures you build to that peak deliberately and don't just drop the insight and move on.

## The Five Phases

### 1. Exposition (Context and Stakes)

Lay the groundwork. Who are the characters (team, users, system)? What's the world like? What are the stakes? Exposition is NOT the problem — it's the context that makes the problem meaningful. The reader should leave this phase knowing what's at risk and why it matters.

**In a post**: "Our product serves 40,000 support teams. When they open the dashboard, they need to see their queue in under 2 seconds. For the last year, we've been at 1.4 seconds. Tight, but safe. Then we launched the new analytics feature."

**Section approach**: 1 H2 section, 2-4 paragraphs. Establish the system, the team, and the stakes. Use real numbers. Make the reader feel the weight of what could go wrong. Paragraph density is medium — specific enough to feel grounded, concise enough to get moving.

**Writing techniques**:
- Opening paragraph that establishes the system and the people who depend on it
- `@aside` for key context (team size, system scale, SLAs) when it would interrupt the narrative flow as inline text
- Blockquote for a customer or stakeholder voice that establishes why this matters

**BFM usage**: An `@aside` in the exposition works well for background metrics or system context that grounds the reader without being part of the story yet. Keep it to one.

### 2. Rising Action (Escalating Tension)

Introduce complications that build toward the climax. Each beat should raise the stakes. The reader should feel increasing tension — things are getting harder, the problem is deeper than expected, the constraints are tightening. Rising action is where you earn the climax. If you rush this, the peak feels unearned. If you linger, the reader gets impatient.

**In a post**: "After the analytics launch, dashboard load time crept to 2.1 seconds. We optimized the queries — back to 1.8. Then traffic grew 30% over Black Friday. We hit 3.2 seconds. We added caching — 2.4 seconds. Then a customer reported the dashboard was blank for 8 seconds. Our caching layer had a thundering herd problem we'd never seen at this scale."

**Section approach**: 2-4 H2 sections structured as escalating beats, or one long H2 section with H3 subheadings per beat. Each beat: new complication, attempted fix, new (worse) problem. High paragraph density with data and code woven in. The pacing should feel like it's accelerating — shorter paragraphs as the situation worsens.

**Writing techniques**:
- Bulleted list with progressive argument to build up the cascading problems (one per paragraph for maximum weight)
- Code blocks with explanatory paragraphs for the fixes applied at each stage
- Comparative paragraphs for "what we expected" vs. "what happened"
- `@aside` for each major escalation point when a single striking number deserves its own space
- `@details` for the technical implementation of each fix — the reader needs to know it was tried, but the full code can collapse

**BFM usage**: Rising Action is the one place multiple `@aside` blocks might be justified — each escalation beat could carry one. But discipline still applies; aim for 1-2 total across this phase, not one per beat.

### 3. Climax (The Peak Moment)

The single highest point of tension, insight, or revelation. Everything before leads here; everything after flows from here. In a technical post, the climax is usually one of: the root cause revealed, the key insight discovered, the approach that finally worked, or the data that changed the team's understanding. This should be the most memorable moment of the post. Give it room to breathe.

**In a post**: "At 3am on a Saturday, staring at the flame graph, we saw it. The analytics feature wasn't just slow — it was recomputing the entire dashboard state on every request. Every optimization we'd done was treating symptoms. The architecture itself was the bug."

**Section approach**: 1 H2 section, 2-4 paragraphs. Less is more. One paragraph for the reveal itself — direct, clean, high-impact. One or two paragraphs to let the reader absorb the implications. Consider ending this section before the next H2 break; the white space is part of the impact.

**Writing techniques**:
- Blockquote for the single-sentence insight — let it stand alone with full typographic weight
- One clear image or data visualization description that made everything click (describe what it shows if you can't embed it)
- Short, declarative paragraphs — the climax is not the place for complex sentence structure

**BFM usage**: A blockquote is the primary tool here. Do not wrap the climactic insight in an `@aside` — asides are tangential, and this is the center of everything.

### 4. Falling Action (Working Through Implications)

The tension releases, but the post isn't over. Falling action is where you show what happened *after* the insight — the implementation, the results, the things that went right and wrong as you applied the solution. This phase is what separates a dramatic reveal from a complete narrative. Without it, the reader has a great "aha" but no idea what to do with it.

**In a post**: "Once we understood the architectural problem, we had a choice: incremental migration or full rewrite. We chose incremental. Week 1: separated the analytics computation into an async pipeline. Week 2: added pre-computed snapshots. Week 3: rolled out to 10% of customers. Load times dropped to 800ms. Then we hit a data consistency issue we hadn't anticipated..."

**Section approach**: 2-3 H2 sections with high paragraph density. Walk through the implementation with real detail. Show the timeline, the decisions, the unexpected bumps. This is where the reader learns HOW, not just WHAT. H3 subheadings for major implementation milestones work well here.

**Writing techniques**:
- Code blocks with explanatory paragraphs for the actual implementation
- `@details` for the key architectural changes that matter to deep readers but would interrupt the narrative for casual readers
- Comparative paragraphs for the tradeoff decisions made during implementation
- `@aside` for unexpected complications during rollout
- Bold summary paragraph for what the implementation required

**BFM usage**: `@details` belongs in Falling Action — the technical depth readers want is here. An `@aside` for a non-obvious gotcha during rollout is appropriate. This is the most technically dense section; use `@details` to keep the narrative moving.

### 5. Denouement (New Normal and Takeaways)

The story lands. Show the new state of the world — what changed, what the reader should take away, and what comes next. The denouement should echo the exposition: revisit the same metrics, the same stakes, the same system, and show how they've transformed. This creates closure.

**In a post**: "Dashboard load time is now 600ms — better than before the analytics feature launched. But more importantly, we now have an architecture that separates read and compute paths. Here are the three principles we extracted from this experience, and how you can evaluate whether your system has the same vulnerability."

**Section approach**: 1-2 H2 sections. Revisit the opening metrics (1-2 paragraphs), state the principles or takeaways (a bold summary paragraph or short bulleted list), and give actionable next steps. The closing paragraph should feel like a send-off, not a summary — echo the opening in language or image, then show what's different.

**Writing techniques**:
- Bold summary paragraph for the extracted principles
- Short bulleted list for actionable steps ("run this command on your repo to see your dependency depth")
- Comparative paragraphs or table for before/after comparison echoing the exposition
- Closing paragraph (not inside any BFM directive) that echoes the opening and shows the transformed world

**BFM usage**: The denouement should close cleanly. Avoid adding new `@aside` blocks here — the reader is landing, and sidebars interrupt that rhythm.

## Length Tier Mapping

### Short (800-1500 words)

Freytag's Pyramid is hard to compress — the five phases need room. For short posts, collapse to three phases:
- **Exposition + Rising Action** (200-350 words): Context and one escalation beat
- **Climax** (150-250 words): The core insight, stated powerfully
- **Denouement** (200-350 words): What it means and what to do about it

Skip Falling Action entirely. The reader can infer the implementation. Focus on the "aha" moment.

### Medium (1500-3000 words)

The ideal length for Freytag's Pyramid:
- **Exposition** (200-350 words): Establish context and stakes
- **Rising Action** (500-900 words): 2-3 escalating complications
- **Climax** (150-300 words): The peak insight
- **Falling Action** (400-700 words): Implementation and results
- **Denouement** (250-450 words): Takeaways and new normal

### Long (3000-5000 words)

Full depth on every phase:
- **Exposition** (400-600 words): Rich context, multiple stakeholders, system architecture overview
- **Rising Action** (900-1500 words): 3-4 escalation beats, each with code and data
- **Climax** (250-400 words): Build to it slowly. Give the insight room. A short section that lands hard
- **Falling Action** (800-1400 words): Full implementation walkthrough, data from production rollout
- **Denouement** (500-800 words): Principles extracted, retrospective insights, actionable exercises

## When to Use

- **Posts with a definitive "aha!" moment** — a root cause discovery, a counterintuitive finding, a breakthrough that reframed the problem
- **Incident retrospectives and postmortems** — the natural escalation of an incident maps perfectly to rising action, and the root cause is the climax
- **Performance optimization stories** — the escalating "we tried X, it wasn't enough" pattern is pure rising action
- **Posts where the emotional arc matters** — if you want the reader to feel the tension and the relief, Freytag gives you the tools
- **Research or investigation posts** — "we looked into X and here's what we found" maps to rising action (investigation) + climax (finding)

## When NOT to Use

- **Posts with no clear peak** — if your post is a survey of techniques, a tutorial, or a comparison of tools, there's no natural climax. Don't force one
- **Posts where the solution matters more than the journey** — if the reader just needs to know "use tool X," the rising action feels like filler. Use Three-Act instead
- **Multi-topic posts** — Freytag assumes one arc with one peak. If you have three separate topics, you'd need three separate pyramids, which gets structurally complex
- **Posts where you can't reveal the climax late** — some readers (executives, time-pressed engineers) need the conclusion first. Freytag delays the payoff by design. If your reader won't wait, use an inverted structure

## Example Mapping

### Product: "How We Cut Dashboard Load Time by 60%"

| Phase | Content | Words |
|-------|---------|-------|
| **Exposition** | Product context: 40K support teams, 2-second SLA, current performance at 1.4s. The analytics feature ships. Stakes: customer retention depends on dashboard performance. | ~350 |
| **Rising Action** | Beat 1: Load time creeps to 2.1s after launch, query optimization gets it to 1.8s. Beat 2: Black Friday traffic spike pushes it to 3.2s, caching added, down to 2.4s. Beat 3: Customer reports 8-second blank screen — thundering herd problem in the cache layer. Each beat includes metrics and code. | ~800 |
| **Climax** | Flame graph reveals the analytics feature recomputes full dashboard state on every request. Every prior fix was treating symptoms. The architecture is the bug. Single blockquote with the core insight. | ~250 |
| **Falling Action** | Incremental migration: async analytics pipeline, pre-computed snapshots, phased rollout. Data consistency issue surfaces during 10% rollout — how it was handled. Final metrics: 600ms load time. | ~550 |
| **Denouement** | Before/after comparison echoing the exposition. Three architectural principles extracted. "How to audit your own system for this pattern." | ~350 |

### Developer Tools: "The Debugging Session That Changed Our Architecture"

| Phase | Content | Words |
|-------|---------|-------|
| **Exposition** | Internal developer platform serving 800 engineers. Build times averaging 3 minutes. "Fast enough" — until the monorepo crossed 2 million lines. | ~300 |
| **Rising Action** | Beat 1: Build times hit 7 minutes. Added parallel compilation — 5 minutes. Beat 2: Developers start skipping local builds, pushing untested code. CI queue backs up. Beat 3: A bad merge takes down staging for 6 hours. The build system can't isolate affected targets. | ~700 |
| **Climax** | Tracing a single build reveals that 80% of compilation time is spent rebuilding unchanged transitive dependencies. The dependency graph has become a dependency web. The dependency graph visualization — described or embedded — makes it undeniable. | ~250 |
| **Falling Action** | Implemented content-addressable caching with strict module boundaries. Enforced dependency depth limits in CI. Build times: 90 seconds for 95th percentile. Migration took 8 weeks and broke 140 build targets that had hidden circular dependencies. | ~650 |
| **Denouement** | Build time graph from 7 minutes to 90 seconds over 3 months. "Three rules for dependency hygiene." Call to action: "Run this command on your repo to see your dependency depth." | ~350 |

## Combination Notes

**Freytag's + Three-Act**: Freytag's Pyramid is a more detailed version of Three-Act. Exposition = Act I, Rising Action + Climax = Act II, Falling Action + Denouement = Act III. If you outline in Three-Act and find your Act II has a strong peak, switch to Freytag to give it proper shape.

**Freytag's + Story Circle**: The Story Circle's Steps 3-5 (Go, Search, Find) map to Rising Action + Climax. If your rising action feels like a flat list of attempts, use Story Circle's "Search" step to add a sense of exploration and adaptation before the "Find" moment (climax).

**Freytag's + Kishōtenketsu**: Kishōtenketsu's "twist" (Ten) and Freytag's "climax" serve similar structural roles but different emotional ones. Freytag's climax resolves tension; Kishōtenketsu's twist recontextualizes without conflict. If your peak moment is more "surprising reframe" than "dramatic resolution," the twist model may fit better even within a Freytag-shaped post.

**Multiple Pyramids**: For long posts covering multiple topics, consider stacking 2-3 mini-pyramids, each with their own rising action and climax. Connect them with brief bridging sections that show how each insight led to the next investigation.
