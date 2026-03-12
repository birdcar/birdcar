# The Rashomon for Technical Blog Posts

The Rashomon presents the same event, problem, or decision from multiple contradictory perspectives, forcing the reader to hold competing truths simultaneously. Named after Akira Kurosawa's 1950 film *Rashomon* — where four witnesses give irreconcilable accounts of the same crime — this framework works because software engineering is fundamentally perspectival: the same system looks completely different depending on whether you're the developer, the operator, the user, or the business owner. Instead of pretending there's one objective truth, The Rashomon makes the multiplicity of interpretation its thesis. The reader leaves not with a single answer but with a richer, more empathetic understanding of why reasonable people disagree.

## The Phases

### 1. The Event (Establish the Shared Facts)

Present the event, decision, or artifact that will be examined from multiple angles. Keep it factual, minimal, and deliberately neutral. Don't tip your hand about which perspective you favor — the power of this framework depends on the reader not knowing where you'll land. Think of this as presenting evidence to a jury: just the facts, chronologically ordered, with no editorializing. The reader will form their own initial interpretation, which makes the perspectival shifts more powerful later.

**In a post**: "On March 14th, a pull request was opened. It changed 3 files, added 47 lines of code, and took 11 days to merge. Four reviewers were involved. Here's the PR description, exactly as it was written." Show the actual PR (anonymized if needed). Let the reader sit with it. Don't rush past the artifact — the reader needs time to form their initial read.

**Section approach**: One H2 section, two to three paragraphs. Present the raw artifact: the PR, the architecture diagram, the incident timeline, the metric dashboard. Minimal commentary. Let the reader form initial impressions they'll later question. If possible, show the artifact exactly as it appeared in its original context — a screenshot, the real error output, the Slack thread with timestamps.

**Writing techniques**: A code block or blockquote of the raw artifact grounds the section in reality. Factual prose only — no evaluative language, no `@aside` warnings or tips. A brief bulleted list of the facts (dates, numbers, people involved) helps the reader hold the shared reference as perspectives diverge later.

**BFM usage**: No `@aside` or `@details` here. The neutrality is structural. Any editorializing undermines the perspectives that follow.

### 2. Perspective 1 (The First Account)

Tell the story from the first point of view. This perspective should be coherent, sympathetic, and complete — the reader should think "yes, that makes sense" when this section ends. Present it as truth, not as "one opinion."

**In a post**: "From the developer's perspective: this PR was straightforward. A well-understood pattern, applied to a new service. The 11-day timeline? Review bottlenecks. Three reviewers left nit comments that didn't affect correctness. The fourth reviewer requested a full redesign in the final round."

**Section approach**: One H2 section labeled with the perspective-holder's role, three to five paragraphs. A full narrative arc for this perspective — include evidence from the shared event, interpreted through this lens. Code blocks, timelines, or direct quotes all work. The reader should finish this section thinking this perspective is correct.

**Writing techniques**: A blockquote with a direct quote or paraphrased statement in this person's voice establishes their viewpoint immediately. A code block of the code as this person reads it, annotated for what they see as important. A bulleted list of this perspective's reasoning builds the case progressively. `@aside` for context the perspective-holder would consider obvious but the reader might not share.

**BFM usage**: `@aside` for background that explains why this perspective makes sense. No `@details` here — keep the perspective legible and complete on its surface.

### 3. Perspective 2 (The Contradicting Account)

Now tell the same story from a second point of view that directly contradicts or reframes the first. Use the same evidence where possible — same PR, same timeline, same code — but interpreted differently. The reader should feel the ground shift.

**In a post**: "From the tech lead's perspective: this PR was a red flag. The pattern the developer called 'well-understood' had caused two incidents last quarter. The 'nit comments' were attempts to steer toward a safer approach without blocking. The full redesign request came because the tech lead realized the hints weren't landing."

**Section approach**: One H2 section labeled with the second perspective-holder's role, three to five paragraphs. Mirror the structure of Perspective 1. Explicitly reference moments from Perspective 1 and reframe them — the reader should feel the same evidence tipping differently. Equal length signals equal validity.

**Writing techniques**: A comparative paragraph or table showing how the same piece of evidence (the PR comment, the timeline, the code) reads differently in this account. `@aside` for what this perspective sees as the risk the first perspective missed. A blockquote for direct quotes or paraphrased statements that capture the voice of this perspective-holder.

**BFM usage**: `@aside` for flagging the specific divergence from Perspective 1 without derailing the narrative.

### 4. Perspective 3 (The Unexpected Account)

Introduce a third perspective that the reader hasn't considered. This one often comes from outside the immediate technical context — the customer, the business, the ops team, or even the codebase itself. This perspective should make the first two feel incomplete.

**In a post**: "From the customer's perspective: they filed a support ticket on March 8th. They were told it would be fixed 'this sprint.' The 11-day PR review meant the fix shipped after the customer had already migrated to a competitor's product. They never saw the fix."

**Section approach**: One H2 section, two to three paragraphs. This perspective often requires less technical depth but more emotional or business weight. It recontextualizes the entire debate between Perspectives 1 and 2. Keep it shorter than the first two perspectives — the impact lands from concision.

**Writing techniques**: A blockquote for the external voice — customer support ticket language, a business metric, an ops runbook entry — that doesn't sound like an engineer. A bold statement of the consequence that the internal perspectives missed. No code blocks needed here unless the artifact is a log or metric.

**BFM usage**: `@aside` for the structural blind spot this perspective reveals. A blockquote as a section anchor — the customer's words, the support ticket, the churn metric.

### 5. The Reconciliation (Or Deliberate Non-Resolution)

Address the contradictions. This is the phase that separates a Rashomon post from a post that simply presents multiple viewpoints. You must do something with the tension — even if that something is explicitly refusing to resolve it. You have three options, and you should choose deliberately:

- **Reconcile**: Show how all perspectives are true simultaneously and what that teaches us about the system. The synthesis is the insight.
- **Privilege one**: Argue that one perspective is more important than the others, and why. Be explicit that you're making this choice and own the reasoning.
- **Leave unresolved**: Argue that the contradiction itself is the point — these tensions are inherent and must be managed, not solved. Give the reader a framework for navigating the tension rather than a resolution.

**In a post (reconcile)**: "All three perspectives are correct. The developer, the tech lead, and the customer each saw real things. The failure wasn't in any individual's judgment — it was in the system that made their perspectives invisible to each other."

**In a post (leave unresolved)**: "I don't have a resolution for you. The developer was right about the code. The tech lead was right about the risk. The customer was right to leave. These truths coexist. The question is: which truth does your team optimize for?"

**Section approach**: One H2 section, two to four paragraphs. Synthesize or deliberately refuse to synthesize. Either way, give the reader a framework for navigating the tension in their own work. The closing paragraph is the post's thesis — state it plainly.

**Writing techniques**: A comparative table of all perspectives side by side gives the reader a summary they can reference. A bulleted list of questions to ask when the same tensions arise. `@aside` for acknowledgment of the perspectives you chose not to include, and why. Bold the thesis statement of the reconciliation so skimmers can find the payoff.

**BFM usage**: `@aside` for perspectives or nuances deliberately left out, to preempt "but what about..." responses. A comparative table as the anchor artifact for the synthesis.

## Length Tier Mapping

### Short Post (800–1500 words)

Compress to two perspectives:
- **Event** (~150 words): The shared facts, stripped to essentials
- **Perspective 1** (~350 words): The obvious interpretation
- **Perspective 2** (~350 words): The contradicting interpretation
- **Reconciliation** (~200 words): The point of the tension

Two perspectives are enough to create the Rashomon effect. A third perspective in a short post will feel rushed. Choose the two with the sharpest contrast — the ones that most directly contradict each other.

### Medium Post (1500–3000 words)

Use all 5 phases with three perspectives:
- **Event**: ~200 words
- **Perspective 1**: ~550 words
- **Perspective 2**: ~550 words
- **Perspective 3**: ~400 words
- **Reconciliation**: ~400 words

Spend roughly equal words on each perspective to maintain the framework's fairness. If one perspective gets significantly more space, the reader reads it as the "correct" one.

### Long Post (3000–5000 words)

Full depth with four perspectives and extended reconciliation:
- **Event**: ~400 words (more artifacts, more context, more setup)
- **Perspective 1**: ~800 words
- **Perspective 2**: ~800 words
- **Perspective 3**: ~800 words
- **Perspective 4**: ~600 words (a fourth perspective — the codebase, the future maintainer, the auditor)
- **Reconciliation**: ~700 words (deeper analysis, frameworks for navigating tensions)

The long format also lets you revisit earlier perspectives after new ones are introduced — a paragraph noting how Perspective 1 looks different once you've heard Perspective 3. This mirrors the film's structure, where the audience re-evaluates each account as new ones accumulate.

## When to Use

- **Architecture decision posts**: Every architectural choice has winners and losers. Show the same decision from the perspective of developer velocity, operational stability, and user experience.
- **Postmortems and incident reviews**: Incidents look different from each role involved. The Rashomon prevents scapegoating by making all perspectives visible.
- **"Best practices" critiques**: Show how a "best practice" looks from the perspective of a startup, an enterprise, and a legacy system. "Best" is always perspectival.
- **Posts about team dynamics or process**: Code review, on-call, sprint planning — all look different depending on where you sit.
- **When you genuinely believe there's no single right answer**: The Rashomon is honest about ambiguity. Use it when the ambiguity is the message.

## When NOT to Use

- **When there IS a clear right answer**: If you know which perspective is correct and the others are simply wrong, don't use The Rashomon — it will make your argument weaker, not stronger. Just make the argument directly.
- **Tutorials or how-to posts**: The reader wants to learn how to do something. Multiple contradictory perspectives on "how to set up CI" is confusing, not enlightening.
- **When the perspectives aren't genuinely contradictory**: If all perspectives basically agree, the framework collapses. You need real tension between the accounts.
- **When one perspective is obviously straw-manned**: The reader will detect if you've made one perspective intentionally weak to make another look good. Every perspective needs to be presented at its strongest.
- **Short posts where you can't give each perspective equal depth**: A rushed Rashomon feels unfair to the perspectives that got less space.

## Example Mapping

### "The PR That Took 11 Days" — Code Review Culture Post

| Phase | Words | Content |
|-------|-------|---------|
| Event | ~150 | The PR: 47 lines, 3 files, 4 reviewers, 11 days. Show the PR description and timeline. |
| Perspective 1 (Developer) | ~500 | Straightforward change. Clear pattern. Reviewers bikeshedding. Morale cost of slow reviews. Data: average review time across the team. |
| Perspective 2 (Tech Lead) | ~500 | Pattern had caused incidents. Nit comments were diplomatic steering. The redesign request prevented a repeat outage. Data: incident history from the same pattern. |
| Perspective 3 (Customer) | ~400 | Filed ticket March 8th. Fix merged March 25th. Churned March 20th. Customer lifetime value lost: $48,000. Data: support ticket timeline. |
| Reconciliation | ~400 | The review process optimized for code quality (tech lead's perspective) at the cost of delivery speed (developer's perspective) and customer retention (customer's perspective). No villain — a system that made these perspectives invisible to each other. |

### "Is Kubernetes Overkill?" — Infrastructure Decision Post

| Phase | Words | Content |
|-------|-------|---------|
| Event | ~200 | A 4-person startup adopted Kubernetes for their first production workload. Show the architecture and team size. |
| Perspective 1 (CTO) | ~500 | Future-proofing. Hiring advantage. Standardized deployment pipeline from day 1. The 2 weeks of setup pays off in months. |
| Perspective 2 (Solo Ops Engineer) | ~500 | 2 weeks of setup became 2 months of maintenance. 60% of on-call pages are K8s issues, not app issues. Team can't debug without the one person who understands networking policies. |
| Perspective 3 (The App) | ~400 | 4 containers, 2 services, 1 database. The app doesn't know or care it's on K8s. It would run identically on a single VM with Docker Compose. The complexity is invisible from inside the container. |
| Reconciliation | ~400 | Deliberately unresolved. All three are right. The question isn't "is K8s overkill" but "which costs are you willing to pay and which perspectives are you willing to deprioritize?" |

### "The Bug That Was a Feature" — Product/Engineering Tension Post

| Phase | Words | Content |
|-------|-------|---------|
| Event | ~200 | A user reports that clicking "Delete" on a shared document removes it for all collaborators, not just themselves. Show the bug report, the code, the product spec. |
| Perspective 1 (User) | ~450 | "I deleted MY copy. Why did it delete everyone's? I lost my team's work." The mental model of personal ownership vs. shared state. |
| Perspective 2 (Developer) | ~450 | "The spec says 'delete removes the document.' There's one document. The code is correct. This is working as designed." The implementation matches the specification exactly. |
| Perspective 3 (Product Manager) | ~400 | "The spec was ambiguous because we hadn't decided on the collaboration model. The developer picked one interpretation. The user expected the other. Neither is wrong — the spec is wrong." |
| Reconciliation | ~400 | The bug isn't in the code or the user's expectations — it's in the gap between the product model and the user's mental model. Three tools for closing that gap: user research before spec writing, spec reviews that include edge cases, and UI copy that makes the system model explicit. |

## Combination Notes

**The Rashomon + Story Circle**: Use the Story Circle as the macro structure, with The Rashomon nested in the Search/Find steps (Steps 4-5). The hero's journey encounters multiple contradictory truths about the problem before finding resolution.

**The Rashomon + In Medias Res**: Open In Medias Res with the shared event (the crisis, the decision, the outcome). Then each "rewind" section is a different perspective's account of how we got there. The reader sees the same backstory refracted through different lenses.

**The Rashomon + The Spiral**: Each pass through The Spiral is a different perspective. Pass 1: the simple explanation (one perspective). Pass 2: a contradicting perspective complicates it. Pass 3: a third perspective adds nuance. The depth and the multiplicity increase together.

**The Rashomon + Reverse Chronology**: Start from the shared outcome and work backwards, but each layer backwards is told from a different perspective. The developer's "last week" vs. the ops team's "last week" vs. the customer's "last week" leading to the same moment.
