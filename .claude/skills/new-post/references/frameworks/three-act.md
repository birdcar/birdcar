# Three-Act Structure for Technical Blog Posts

The Three-Act Structure is the oldest and most universal narrative framework, traceable to Aristotle's *Poetics*. It divides any story into Setup, Confrontation, and Resolution. Its power is its simplicity — readers intuitively expect this shape, so it requires almost no structural overhead to follow. For technical posts, it works as a reliable baseline when you have a clear problem and a clear solution but don't need the granularity of more elaborate frameworks.

## The Acts

### Act I: Setup (The World and the Problem)

Establish the reader's current reality, then introduce the problem that disrupts it. By the end of Act I, the reader should understand *what exists* and *why it's insufficient*. The transition into Act II is the "inciting incident" — the moment that makes the status quo untenable.

**In a post**: "Your team ships features weekly. Deploys are automated, tests pass, PRs get reviewed. Life is decent. But last quarter, three customer-facing incidents shipped past every safeguard you had. The process that felt solid has a crack in it."

**Section approach**: 1-2 H2 sections with medium paragraph density. Open with the familiar world (1-2 paragraphs establishing shared context), build tension toward the problem (2-4 paragraphs showing symptoms), and close with a sharp inciting statement — the stat, the failure, the moment the status quo broke. Pacing should feel steady; don't rush past the shared context.

**Writing techniques**:
- Opening hook paragraph that grounds the reader in the familiar world
- Blockquote from a named authority or memorable source to establish "this is a known thing"
- Bulleted list with progressive argument to build up symptoms of the problem
- `@aside` for the inciting incident when it's a single striking data point or moment that deserves to breathe outside the paragraph flow

**BFM usage**: An `@aside` with the inciting statistic or failure moment works well here when it would feel buried in prose. Keep it to one — this act shouldn't feel fragmented.

### Act II: Confrontation (Attempts and Obstacles)

This is the longest section — typically half the post. You explore approaches, encounter obstacles, and deepen the reader's understanding. Act II is where credibility is built. You show that you didn't just stumble onto a solution; you earned it through investigation. Structure Act II as a series of escalating attempts: each one gets closer, each one reveals a new constraint.

**In a post**: "First, we added more integration tests. Coverage went up, but incident rate didn't budge — the bugs were in the interactions between services, not within them. So we tried contract testing. Better, but the contracts drifted from reality within weeks. Then we instrumented production traffic and replayed it against staging. That caught real issues, but the infrastructure cost was brutal."

**Section approach**: 2-4 H2 sections, each following a mini attempt-obstacle-learning cycle. High paragraph density — this is where code, data, and analysis live. Use H3 subheadings within each attempt to separate the attempt itself from the obstacle and the learning. Don't rush: readers who made it into Act II are engaged and want the depth.

**Writing techniques**:
- Code blocks with explanatory paragraphs for each attempted solution
- Comparative paragraphs or a table for "what we expected" vs. "what happened" contrasts
- `@aside` for key learnings from each attempt when they stand on their own
- `@details` for extended code examples that support the argument but would interrupt narrative flow
- Bold summary paragraph at the close of each attempt: what this approach taught you before moving to the next

**BFM usage**: `@details` belongs in Act II more than anywhere — the implementation depth readers want is here, but not every reader needs all of it. Reserve `@aside` for the "here's the thing nobody tells you about approach X" moments. Max 2 asides total across the whole post.

### Act III: Resolution (Solution and Impact)

Deliver the solution that emerged from the confrontation, then show its real-world impact. Act III must do three things: present the solution clearly, acknowledge its tradeoffs honestly, and send the reader home with something actionable. A weak Act III — where the solution feels rushed or the ending is just a list of links — undercuts everything that came before.

**In a post**: "The answer wasn't more testing — it was changing *when* we tested. We moved validation into the deployment pipeline itself, using production traffic shadows against canary instances. Incident rate dropped 70% in one quarter. The tradeoff: deploy times went from 4 minutes to 11, and we needed dedicated infrastructure. Here's how you evaluate whether this tradeoff makes sense for your team."

**Section approach**: 2-3 H2 sections. Present the solution (1-2 sections with code as needed), acknowledge tradeoffs honestly (comparative paragraphs or a table work well), and close with a bold summary paragraph or short bulleted list of actionable steps. The final paragraph should feel like a send-off, not a summary.

**Writing techniques**:
- Blockquote for the core insight when it can be stated as a single memorable sentence
- Code block with explanatory paragraphs for the solution's implementation
- Comparative paragraphs or table for tradeoff analysis (gains vs. costs)
- Short bulleted list for "what you can do this week" action items
- Closing paragraph (not a heading) that echoes the opening world and shows how it's changed

**BFM usage**: A blockquote works well for the central insight of Act III — the sentence that distills what was learned. The closing paragraph should stand on its own without an `@aside` wrapper.

## Length Tier Mapping

### Short (800-1500 words)

Ruthlessly compress:
- **Act I** (150-250 words): One or two paragraphs for the world, one for the problem, one sentence inciting incident
- **Act II** (400-750 words): One attempt only — show the most instructive failure, then pivot to the solution
- **Act III** (200-400 words): Solution, one piece of evidence, one takeaway

In a short post, Act II collapses almost entirely. You state the problem, show the key insight, and land the solution. This is fine — the Three-Act Structure degrades gracefully because even at minimum, Setup-Confrontation-Resolution gives the reader a satisfying arc.

### Medium (1500-3000 words)

The natural home for Three-Act:
- **Act I** (300-500 words): Full setup with relatable context and a well-developed inciting incident
- **Act II** (800-1500 words): 2-3 attempts with real code, data, or examples. Each attempt deepens understanding
- **Act III** (400-700 words): Solution with evidence, honest tradeoffs, and actionable steps

### Long (3000-5000 words)

Act II expands significantly:
- **Act I** (500-800 words): Rich context-setting. Multiple stakeholder perspectives. Data on the problem's scope
- **Act II** (1800-2800 words): 3-4 full attempt-obstacle-learning cycles. Deep code walkthroughs. Multiple data points. Each attempt is its own H2 section with H3 subsections
- **Act III** (700-1200 words): Solution deep-dive, production results over time, tradeoff analysis, multiple levels of "try this" (beginner, intermediate, advanced)

## When to Use

- **Any post where you're not sure what framework to use** — Three-Act is the safest default
- **Problem/solution posts** — the structure maps directly: problem is Act I, investigation is Act II, solution is Act III
- **Postmortems and incident reviews** — what happened, what we tried, what we learned
- **Tool or library introductions** — the pain before, the search for a tool, the tool in action
- **Posts with a single clear throughline** — Three-Act rewards linear narratives

## When NOT to Use

- **Posts with no clear "problem"** — exploratory or educational posts where you're teaching a concept (not solving a problem) can feel forced into Three-Act. Consider Kishōtenketsu instead
- **Posts with multiple distinct insights** — if you have 3-4 independent points to make, Three-Act forces you to serialize them into a single arc, which can feel artificial. A modular structure may work better
- **Posts where the journey matters more than the destination** — if the process of exploration is the point (not the solution), Story Circle gives you more steps to develop that journey
- **Very dramatic reveals** — if your post builds to a single explosive "aha!" moment, Freytag's Pyramid gives you better tools for building to and descending from a climax

## Example Mapping

### Support Engineering: "Reducing Escalation Response Time by 60%"

| Act | Content | Words |
|-----|---------|-------|
| **I: Setup** | "Your team handles 200 escalations/month. Average resolution: 4.2 hours. Customers are frustrated, engineers are burned out. Last month, a P1 sat for 6 hours because three engineers worked it independently without knowing." | ~350 |
| **II: Confrontation** | Attempt 1: Better runbooks — helped new hires, didn't move the needle for experienced engineers. Attempt 2: Centralized triage queue — reduced duplicate work but created a bottleneck. Attempt 3: Pattern tagging + routing rules — promising but tags were inconsistent. Show real ticket data, routing logic code, dashboard analysis. | ~1100 |
| **III: Resolution** | Automated pattern detection on incoming tickets using embedding similarity. Routes to the engineer who resolved the most similar past tickets. Resolution time dropped to 1.6 hours. Tradeoff: requires 3 months of historical ticket data, cold-start problem for new issue types. "Start by tagging your last 100 resolved tickets with root cause categories." | ~600 |

### Developer Tools: "Why We Rewrote Our CLI in Rust"

| Act | Content | Words |
|-----|---------|-------|
| **I: Setup** | "Our CLI is used by 12,000 developers daily. It's written in Node.js, it works, and nobody loves it. Startup time is 1.8 seconds. On CI, that's 14 minutes of wasted compute per day across all pipelines." | ~300 |
| **II: Confrontation** | Attempt 1: Optimize the Node.js code — got startup to 900ms, hit a floor. Attempt 2: Bundle with pkg — faster cold start but binary was 120MB. Attempt 3: Rewrite critical path in Rust with Node.js shell — two codebases, two bug surfaces. Include profiling data, bundle analysis, and the hybrid architecture explanation. | ~1000 |
| **III: Resolution** | Full Rust rewrite with backwards-compatible command interface. Startup: 40ms. Binary: 8MB. But: 4-month rewrite, team had to learn Rust, plugin ecosystem had to be rebuilt. "Here's the decision matrix we used — and here's when a rewrite is NOT the answer." | ~550 |

## Combination Notes

**Three-Act + Story Circle**: Three-Act is essentially a compressed Story Circle. If you outline in Three-Act and find Act II feels flat, expand it using Story Circle's Steps 3-6 (Go, Search, Find, Take) to add texture to the confrontation.

**Three-Act + Freytag's Pyramid**: If your Act II has a natural climax — a moment where everything clicks — consider using Freytag's rising/falling action to structure Act II internally. Act II becomes its own mini-pyramid.

**Three-Act + Kishōtenketsu**: Kishōtenketsu's "twist" (Ten) can replace Act II's final obstacle. Instead of the last attempt failing, it reframes the entire problem. This works when your solution came from a perspective shift rather than iteration.

**Nested Three-Act**: For long posts, each major section of Act II can itself follow a mini three-act structure: setup the attempt, show the obstacle, resolve with a learning. This creates a satisfying rhythm without requiring a different framework.
