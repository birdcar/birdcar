# In Medias Res for Technical Blog Posts

In Medias Res ("into the middle of things") drops the reader directly into the most compelling moment of your story, then rewinds to fill in context. Originating from Homer's epic poetry tradition — both the *Iliad* and the *Odyssey* open mid-action — the technique exploits a fundamental cognitive hook: when people witness a crisis without context, they cannot help but ask "how did we get here?" That question keeps them engaged through setup material that would otherwise feel slow. For technical posts, this means opening with the production incident, the failing test, the angry Slack thread, or the dashboard on fire — then rewinding to explain the architecture, decisions, and assumptions that led to that moment.

## The Phases

### 1. The Drop (Crisis Moment)

Open with maximum tension. No preamble, no "in this post I'll cover." The reader lands in the middle of something going wrong (or surprisingly right). The Drop works because of information asymmetry — the reader sees the outcome but lacks the context. That gap is what generates the tension that will carry them through the rest of the post.

**In a post**: "It's 2:47am. PagerDuty fires. The payments service is returning 500s for every request. Grafana shows a cliff — requests per second dropped from 12,000 to zero in under 90 seconds." Then show the actual dashboard screenshot or terminal output. The more specific and real the artifact, the stronger the hook. Avoid hypotheticals here — the reader needs to feel that this actually happened.

**Section approach**: This is a single dense opening section — one to two paragraphs, no H2 yet. Hit hard immediately. A dramatic artifact (screenshot, terminal output, real error message) belongs here. No byline context, no "welcome to my blog" — save the author framing for after the hook or skip it entirely. The first sentence is load-bearing.

**Writing techniques**: Open with a blockquote of the actual alert, error message, or Slack notification to make the crisis visceral. A code block with shell syntax showing the error output grounds the moment in reality. Keep prose tight — short sentences, present tense if possible. `@aside` directives are wrong for this phase; every word should serve the tension.

### 2. The Freeze (Build Curiosity)

Pause the action. Acknowledge the reader's confusion. Explicitly name the question you want them to hold.

**In a post**: "We'll come back to this moment. But to understand why this happened — and why the fix was the opposite of what you'd expect — we need to go back three months."

**Section approach**: A short transitional section, one paragraph. This is where you can introduce yourself if you skipped it at the top — the delayed introduction is a signature move of In Medias Res. The reader has already decided to trust you because you opened with something real, not a credentials paragraph.

**Writing techniques**: A single bold question or statement as its own paragraph creates visual pause. A brief author introduction here (one sentence) reads as confident, not defensive. The pivotal question should be stated explicitly: *"To understand why this happened, we need to go back."*

### 3. The Rewind (Context & Setup)

Now deliver the exposition that the reader is primed to receive. Architecture decisions, team context, constraints, the codebase's history. This is the material that would be boring as an opener but is now charged with dramatic irony — the reader knows something goes wrong. Every piece of context you present, the reader is silently evaluating: "is THIS the thing that caused it?"

That dramatic irony is your most powerful tool in this phase. Lean into it. When you explain a decision that seems reasonable, the reader is already suspicious. When you show code that looks fine, they're scanning for the flaw. This makes technical exposition genuinely engaging in a way that's nearly impossible with linear storytelling.

**In a post**: "Three months ago we migrated from a monolith to microservices. Here's the architecture we chose, and here's the key assumption we made about database connection pooling. This assumption was reasonable — here's why everyone on the team agreed." Present each decision charitably. The reader should think "I would have made the same choice."

**Section approach**: Two to four H2 sections, each covering one layer of context. This is the longest part of the post. Each section should introduce the system or decision at a level of detail the reader needs for the Catch-Up. Paragraph density can be higher here — the reader is engaged and willing to follow. Progressive disclosure works well: introduce the system piece by piece, letting the reader build a mental model they'll need later.

**Writing techniques**: Code blocks with explanatory paragraphs for walking through the original implementation. A comparative table or side-by-side paragraphs for before/after architecture. Progressive argument building — introduce each assumption as a numbered or bulleted list, so the reader tracks the chain. `@aside` directives work well here for technical context that's necessary but would interrupt narrative flow. `@details` for implementation specifics the reader can expand if they want depth.

### 4. The Catch-Up (Rejoin the Crisis)

Accelerate back to the opening moment. Connect the dots between what you just explained and the crisis the reader witnessed. The "aha" hits here — the reader now understands what they were looking at. This is the most satisfying moment of the post: the gap between "what happened" and "why it happened" closes. Pacing should build — start deliberate, then accelerate as the connections become obvious.

**In a post**: "So when traffic spiked on launch night, every service tried to open new database connections simultaneously. The connection pool wasn't shared — it was per-pod. 47 pods times 20 connections each. The database had a limit of 100. That's what the dashboard was showing us."

**Section approach**: One H2 section, two to three paragraphs. Build momentum. Each paragraph should connect one piece of backstory to the crisis. The reader should feel the pieces clicking into place. Consider returning to the exact artifact from the Drop (the dashboard image, the terminal output) and annotating or re-describing it with the context they now have.

**Writing techniques**: A numbered list tracing the chain of events connects backstory to crisis efficiently. A blockquote echoing the original error message — now explained — creates satisfying closure. Short paragraphs accelerate pace; this is not where you introduce new concepts.

### 5. The Resolution (Continue Forward)

Now continue past the opening moment. What happened next? How was it fixed? What was learned? This section has earned full attention because the reader is emotionally invested in the outcome. The Resolution has two jobs: satisfy the narrative tension (what happened?) and deliver the transferable value (what can the reader use?).

**In a post**: "Here's what we did in the next 4 hours to restore service, and here's the architectural change we shipped the following week that made this class of failure impossible. The connection pooler now lives here, and here's what the config looks like."

**Section approach**: Two to three H2 sections. Split the immediate fix (what we did that night) from the systemic fix (what we changed permanently). End by connecting the resolution back to something the reader can use — a pattern, a checklist, a tool, a principle. The closing paragraph is the equivalent of the post's thesis, stated plainly now that the reader has earned it.

**Writing techniques**: Code block showing the fix, with explanatory paragraphs. A before/after table or comparative paragraphs for the architectural change. A bold summary paragraph for lessons learned — this is the skimmable takeaway. `@aside` for related reading or alternative approaches. The closing paragraph should stand alone as a summary of the post's argument.

## Length Tier Mapping

### Short Post (800–1500 words)

Compress to three beats:
- **Drop** (~150 words): The crisis moment — make it vivid and specific
- **Rewind** (~700 words): One key piece of context that explains the crisis
- **Resolution** (~400 words): The fix and the lesson

Skip the Freeze and Catch-Up phases. Go directly from crisis to "here's why" to "here's how we fixed it." The rewind is just one flashback, not a full history. You can still do the delayed introduction — open with the crisis, then say "I'm [name], and this happened to my team" as part of the single rewind beat.

### Medium Post (1500–3000 words)

Use all 5 phases:
- **Drop**: ~200 words (open with impact)
- **Freeze**: ~100 words (introduce yourself, frame the question)
- **Rewind**: ~1000 words (full context, architecture, decisions)
- **Catch-Up**: ~400 words (connect backstory to crisis)
- **Resolution**: ~600 words (fix, results, takeaways)

### Long Post (3000–5000 words)

Full depth everywhere. Expand the Rewind into multiple chapters with their own mini-tensions:
- **Drop**: ~300 words (extended crisis with multiple artifacts — dashboard, Slack, logs)
- **Freeze**: ~200 words (thorough author framing, preview of the journey)
- **Rewind**: ~2000 words (multiple flashback layers at different time scales)
- **Catch-Up**: ~800 words (detailed reconstruction, annotated code walkthrough of the failure)
- **Resolution**: ~1000 words (immediate fix, systemic fix, metrics, reader exercises)

Add multiple flashback layers in the Rewind (decisions at different time scales — last week, last month, last year). Include a detailed code walkthrough during the Catch-Up reproducing the failure logic. Extend the Resolution with implementation details, before/after metrics, and a "what we'd do differently" reflection before the close.

## When to Use

- **Incident retrospectives and postmortems**: The natural structure of "something broke, here's why" maps perfectly. The reader already expects a timeline; In Medias Res just starts at the most interesting point instead of the beginning.
- **Debugging stories**: Start with the symptom, rewind to the cause. The reader experiences the same "working backwards" process the debugger did.
- **Migration and refactoring posts**: Start with the successful (or failed) outcome, then explain the journey. Migrations are inherently long stories; the drop keeps the reader engaged through the exposition.
- **Posts where the setup is inherently dry**: Architecture decisions, config choices, and infrastructure posts become engaging when the reader knows a crisis is coming.
- **When you have a specific, vivid moment** to anchor the post. The Drop only works if it's concrete and dramatic — real dashboards, real error messages, real Slack threads.

## When NOT to Use

- **Tutorial or educational posts**: If there's no crisis or dramatic moment, forcing one feels artificial. Use The Spiral instead.
- **Announcement or launch posts**: The reader wants to hear the news, not wait through a manufactured flashback. Don't make people sit through a crisis arc to find out about your new feature.
- **Posts where the "crisis" is mild**: "Our build was slow" is not a compelling Drop. The opening moment needs genuine stakes — financial impact, data loss, customer churn, or at minimum, significant engineering time burned.
- **Highly abstract or conceptual posts**: In Medias Res requires a concrete event. If your post is about type theory or design principles without a grounding incident, choose another framework.
- **When the reader needs sequential understanding first**: Some topics (cryptography, distributed consensus) require building blocks in order. Jumping ahead creates confusion, not curiosity.

## Example Mapping

### "How a Typo Took Down DNS for 3 Hours" — Debugging Story

| Phase | Words | Content |
|-------|-------|---------|
| Drop | ~150 | "Our monitoring goes blank. Not red — blank. Grafana can't resolve our metrics endpoint. Because nothing can resolve anything. DNS is down." Show the empty dashboard. |
| Freeze | ~75 | "A single character in a BIND config file did this. Let me show you which character and how it got there." |
| Rewind | ~900 | The DNS infrastructure. The config management pipeline. The PR that updated a TTL value — and the trailing dot that was accidentally deleted from an FQDN. What a trailing dot means in DNS. Why the config parser didn't catch it. |
| Catch-Up | ~300 | "Without the trailing dot, `api.example.com` became `api.example.com.example.com`. Every internal service resolution failed. Cascading timeouts took down everything that depended on DNS — which was everything." |
| Resolution | ~500 | Config validation in CI that parses BIND zones before merge. The one-line test that would have caught this. The argument for infrastructure-as-code over manual config. |

### "The Deploy That Deleted Production" — Incident Retrospective

| Phase | Words | Content |
|-------|-------|---------|
| Drop | ~200 | "Friday 5:02pm. `kubectl apply` completes. Every pod in the production namespace terminates. Not restarts — terminates. Here's the Slack thread." |
| Freeze | ~100 | "To understand how a routine deploy did this, we need to rewind to a Helm chart change made 6 weeks ago." |
| Rewind | ~1000 | The Helm chart refactor. The label selector change. The review that approved it. The staging environment that didn't catch it (and why). The CD pipeline's assumptions about immutable selectors. |
| Catch-Up | ~400 | "So when the new chart applied, Kubernetes saw pods with labels that didn't match any deployment. Orphaned pods get garbage collected. Every single one." |
| Resolution | ~600 | Admission controller that blocks label selector changes. Pre-deploy diff tooling. The 3 questions to add to every Helm chart review. |

### "Why We Mass Reverted Our React Migration" — Architecture Decision Post

| Phase | Words | Content |
|-------|-------|---------|
| Drop | ~200 | "Here's the PR: +0 -47,000 lines. Title: 'Revert: React migration.' Merged by the CTO at 11pm on a Tuesday." Show the actual PR (line counts, merge timestamp, approvals). |
| Freeze | ~100 | "This is a story about what happens when a migration is technically correct but organizationally wrong. Let me show you how we got to that PR." |
| Rewind | ~1200 | The decision to migrate. The proof of concept that worked beautifully. The team structure that made incremental migration impossible. The performance regression nobody measured until month 3. The sprint retros that kept saying "next sprint will be better." |
| Catch-Up | ~500 | "The CTO opened that PR after the third sprint where zero features shipped because every team was blocked on migration bugs. The 47,000 lines were technically fine. The velocity cost was not." |
| Resolution | ~700 | How they re-attempted the migration 6 months later with a strangler fig pattern. What changed organizationally. The migration checklist they use now: technical readiness, team readiness, rollback plan, success metrics. |

## Combination Notes

**In Medias Res + Story Circle**: Use In Medias Res for the opening (Steps 1-2 of Story Circle become the Drop and Freeze), then follow the Story Circle from Step 3 onward. The rewind maps to Go/Search, the catch-up to Find, and the resolution to Take/Return/Change.

**In Medias Res + The Spiral**: Open with a crisis that only makes sense at depth. Each pass through The Spiral reveals a deeper layer of why it happened. First pass: the surface cause. Second pass: the architectural flaw. Third pass: the organizational incentive that created the flaw.

**In Medias Res + Reverse Chronology**: These are cousins — both play with timeline. Use In Medias Res when you want to start at the climax and flash back. Use Reverse Chronology when you want to start at the end and systematically peel back every layer. They can combine: open In Medias Res at the crisis, then Reverse Chronology through the causes.

**In Medias Res + The Rashomon**: Drop into the crisis, then rewind and retell the backstory from multiple perspectives. Each perspective in the Rewind reveals different context about the same crisis. The Catch-Up becomes the reconciliation point where all perspectives converge on the same moment.

**Common pitfall — the underwhelming Drop**: If your opening doesn't generate genuine curiosity, the entire framework collapses. Test your Drop on someone unfamiliar with the story. If they don't immediately ask "what happened?" or "how did that happen?", strengthen it or choose a different framework. The Drop is load-bearing — everything else depends on it.

**Common pitfall — the too-long Rewind**: The Rewind should feel like it's building toward the Catch-Up, not like a separate post. If you find yourself spending more than 40% of the total word count in the Rewind, you're losing the narrative momentum that the Drop created. Trim context aggressively — only include backstory that's necessary to understand the crisis.
