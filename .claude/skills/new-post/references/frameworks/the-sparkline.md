# The Sparkline (Nancy Duarte)

The Sparkline alternates between "what is" (current reality) and "what could be" (ideal future) throughout the entire post. The tension between these two states creates emotional energy that pulls the reader forward. Nancy Duarte identified this pattern by analyzing hundreds of great speeches — from Martin Luther King Jr.'s "I Have a Dream" to Steve Jobs' iPhone launch — and documented it in her book *Resonate*. It works for blog posts because the oscillation prevents the reader from settling into passive skimming; each swing back to "what is" re-engages their frustration, and each swing to "what could be" re-engages their hope. The post ends by collapsing the gap, showing a clear path from the current state to the future state.

## The Steps

### 1. What Is (Opening Reality)

Establish the current state. Ground the reader in a shared, concrete reality they recognize and live in every day.

**In a post**: "Right now, deploying our frontend takes 45 minutes. Developers open a PR, wait for CI, wait for staging, wait for approval, wait for the deploy pipeline. Most of that time is waiting."

**Section approach**: One opening section — typically no H2 yet, or a minimal one. Two to three paragraphs. Paint the picture with real numbers, code output, or a description of the current painful workflow. Dense enough to feel real, tight enough not to drag.

**Writing techniques**: An `@aside` with a stark metric ("Our P95 deploy time: 43 minutes") makes the cost concrete. A code block with shell syntax showing the slow, painful process grounds it in reality. Plain, declarative prose works best for grounding narrative — resist the urge to editorialize yet.

**BFM usage**: `@aside` fits well here for a supporting metric or context that would interrupt the narrative flow. Avoid `@details` — nothing should be optional in the reality-setting phase.

### 2. What Could Be (First Contrast)

Swing to the ideal. Don't explain how — just show what the world looks like if the problem were solved.

**In a post**: "Imagine pushing to main and seeing your change live in under 90 seconds. No waiting. No approval bottleneck. Just confidence."

**Section approach**: One H2 section, two to three paragraphs. Aspirational language. Show a mock timeline, describe a cleaned-up experience, or name a single impressive metric. Keep it short — this is a vision, not an explanation.

**Writing techniques**: A blockquote as section break works well for a bold aspirational statement. Keep it imagistic rather than mechanical; the "how" comes later. Single-sentence paragraphs can land hard here.

**BFM usage**: A blockquote (with or without attribution) creates visual contrast with the dense prose of the previous section — the whitespace feels like breathing room.

### 3. What Is (Deeper Problem)

Swing back to reality, but go deeper. Peel back a layer the reader hadn't considered. The gap between where they just were (the ideal) and where they are now should sting.

**In a post**: "But it's worse than slow deploys. Because of the wait time, developers batch changes. Batched changes are harder to review. Harder reviews get rubber-stamped. Rubber-stamped reviews ship bugs."

**Section approach**: One H2 section, three to four paragraphs. Build a causal chain. Each paragraph reveals the next consequence. The reader should arrive at the end of this section feeling worse than they did at the start — productively worse.

**Writing techniques**: A bulleted list with progressive argument builds the chain of consequences step-by-step, letting each item land before the next. An `@aside` can flag the hidden cost in a way that makes the reader pause.

**BFM usage**: `@aside` with `type: warning` framing ("The real cost isn't the deploy time — it's what it does to your review culture") creates a beat of alarm without disrupting the flow.

### 4. What Could Be (Expanded Vision)

Swing back to the ideal, but expand it. Now that the reader sees the deeper problem, show them the deeper benefit.

**In a post**: "With fast deploys, changes are small. Small changes are easy to review. Easy reviews are thorough. Thorough reviews catch bugs before production. The deploy speed isn't the point — the feedback loop is."

**Section approach**: One H2 section, two to three paragraphs. Mirror the causal chain from Step 3 but invert it. Show the virtuous cycle. The structure should echo Step 3 closely enough that the reader feels the symmetry.

**Writing techniques**: A bulleted list mirroring the previous causal chain creates a visual echo — the reader sees the parallel without being told to notice it. Comparative paragraphs work well for side-by-side "current chain vs. ideal chain" if word budget allows.

**BFM usage**: Avoid `@aside` here — keep the positive vision uninterrupted. Let it breathe.

### 5. What Is (The Obstacle)

Swing back one more time. Name the specific thing standing in the way. This is the crux — the obstacle the reader can actually act on.

**In a post**: "The bottleneck isn't our CI pipeline. It's the manual approval gate we added after that one incident two years ago. We've been optimizing around a policy nobody's revisited."

**Section approach**: One H2 section, two to three paragraphs. Be specific. Name the thing. Show evidence. This is the most important "what is" beat because it converts frustration into a target.

**Writing techniques**: A code block walking through the pipeline config and highlighting the bottleneck makes the problem concrete. An `@aside` can hold root cause context that would slow the main narrative.

**BFM usage**: `@aside` or `@details` for technical specifics about the obstacle that readers who aren't hitting this exact bottleneck can skip.

### 6. The New Bliss (Collapse the Gap)

The final swing — but this time, instead of returning to "what is," you show the path. The gap between is and could-be collapses. The reader leaves with a clear trajectory.

**In a post**: "Here's what we did: replaced the manual gate with automated canary analysis. Deploy time dropped to 80 seconds. Review quality went up because PRs were smaller. Incidents went down. Here's how you can do the same thing."

**Section approach**: Two to three H2 sections. Show the solution, the results, and the actionable steps separately. The closing paragraph is the post's thesis stated plainly — what changed, why it mattered, what the reader can do. End with a direct call to action or a principle the reader can carry with them.

**Writing techniques**: A code block showing the new workflow with explanatory paragraphs. A bold summary paragraph or short bulleted list for the concrete actions. The closing paragraph should stand alone as a summary.

**BFM usage**: `@details` for extended implementation specifics. `@aside` for caveats or alternative approaches. The final paragraph should be plain prose — no directives.

## Length Tier Mapping

### Short Post (800–1500 words)

One full oscillation plus the collapse:
- **What Is** (~150 words): One sharp problem statement
- **What Could Be** (~150 words): One clear vision
- **What Is — Obstacle** (~200 words): Name the one thing in the way
- **New Bliss** (~400 words): Solution, results, takeaway

Skip the deepening oscillations. One contrast is enough to create tension in a short post. The obstacle step can fold into the opening "what is" if the word budget is tight.

### Medium Post (1500–3000 words)

Two full oscillations plus the collapse:
- **What Is — Opening** (~250 words): Establish shared reality
- **What Could Be — First** (~200 words): Initial vision
- **What Is — Deeper** (~400 words): Peel back the layer
- **What Could Be — Expanded** (~300 words): Expanded vision
- **What Is — Obstacle** (~250 words): Name the blocker
- **New Bliss** (~600 words): Solution, results, actions

### Long Post (3000–5000 words)

Three or more oscillations plus the collapse:
- **What Is — Opening** (~400 words): Rich context with data and anecdote
- **What Could Be — First** (~300 words): Vision with concrete examples
- **What Is — Deeper** (~600 words): Second-order problems, code walkthroughs
- **What Could Be — Expanded** (~500 words): Systemic benefits, case studies
- **What Is — Obstacle** (~500 words): Root cause analysis, evidence
- **What Could Be — Aspirational** (~400 words): Industry-level vision
- **New Bliss** (~900 words): Full solution walkthrough, results, actions, reader exercise

Each additional oscillation deepens the emotional investment. Don't add oscillations for length — add them when there are genuine layers to peel.

## When to Use

- **Product or feature launch posts**: The contrast between current pain and future state is the core of any launch narrative.
- **Change advocacy**: Convincing readers to adopt a new tool, process, or architecture. The oscillation builds dissatisfaction with the status quo.
- **Inspirational or opinion pieces**: The Sparkline is inherently motivational. It works when you want the reader to feel something, not just learn something.
- **Post-incident retrospectives**: Contrast "what happened" with "what should have happened" to build the case for systemic change.

## When NOT to Use

- **Pure tutorials or how-to posts**: If the reader already wants to learn the thing, you don't need to sell them on why. Use Story Circle or Socratic Path instead.
- **Posts with no clear "better future"**: If you're exploring a problem without a solution, the oscillation will feel like teasing without payoff.
- **Highly technical deep-dives**: The emotional oscillation can feel manipulative if the reader wants code, not motivation. Save it for the framing and use a different structure for the technical core.

## Example Mapping

### "Kill the Approval Gate" — A DevOps Post

| Step | Words | Content |
|------|-------|---------|
| What Is (Opening) | ~200 | "Our deploy takes 45 minutes. Here's the timeline." |
| What Could Be (First) | ~150 | "What if it took 90 seconds? Here's what that looks like." |
| What Is (Deeper) | ~350 | "Slow deploys cause batching. Batching causes rubber-stamping. Rubber-stamping causes incidents." |
| What Could Be (Expanded) | ~250 | "Fast deploys → small PRs → real reviews → fewer bugs. The virtuous cycle." |
| What Is (Obstacle) | ~250 | "The manual approval gate from 2022. Nobody's questioned it." |
| New Bliss | ~600 | "Automated canary analysis. 80-second deploys. Smaller PRs. Better reviews. Here's how." |

### "Why Your Team Should Adopt TypeScript" — A Persuasion Post

| Step | Words | Content |
|------|-------|---------|
| What Is (Opening) | ~200 | "You ship JavaScript. It works. But every quarter, the same class of bug." |
| What Could Be (First) | ~150 | "What if the compiler caught those bugs before CI even ran?" |
| What Is (Deeper) | ~350 | "It's not just runtime errors. It's the onboarding cost, the implicit knowledge, the 'ask Sarah' problem." |
| What Could Be (Expanded) | ~250 | "Types as documentation that never drifts. New devs productive in days, not weeks." |
| What Is (Obstacle) | ~250 | "The migration cost feels impossible. 200k lines of JS." |
| New Bliss | ~600 | "Incremental adoption with allowJs. Start with the API layer. Here's a 3-sprint plan." |

## Oscillation Design

The quality of a Sparkline post depends on the transitions between "what is" and "what could be." Three techniques for effective oscillation:

- **Emotional contrast**: Pair a frustrating "what is" section (real metrics, error logs) with an aspirational "what could be" section (described clean state, green outcomes). The contrast amplifies the emotional swing.
- **Concrete to concrete**: Never oscillate between abstract states. "Our deploy is slow" is weaker than "Our deploy takes 45 minutes." "Fast deploys" is weaker than "90-second deploys." Concrete numbers in both states make the gap tangible.
- **Escalating stakes**: Each "what is" should go deeper than the last. The first is surface-level pain. The second reveals structural causes. The third names the root obstacle. If the stakes don't escalate, the oscillation feels repetitive rather than progressive.

## Combination Notes

- **Sparkline + Story Circle**: Use the Sparkline's oscillation for the first half (Steps 1-4 of the Story Circle: You, Need, Go, Search), then shift into Story Circle's linear resolution for the second half (Find, Take, Return, Change). This gives you emotional momentum early and structured payoff late.
- **Sparkline + False Start**: Open with a False Start that establishes a misleading "what could be," then restart with the real Sparkline. The false start makes the genuine oscillation hit harder.
- **Sparkline + Converging Ideas**: Use the Sparkline as the macro structure, but make each "what is" or "what could be" beat come from a different thread (frontend, backend, infra). The convergence happens naturally when the gap collapses.
- **Watch out**: Don't combine with Nested Loops — the oscillation and the nesting create competing structural rhythms that confuse the reader's sense of where they are in the post.
