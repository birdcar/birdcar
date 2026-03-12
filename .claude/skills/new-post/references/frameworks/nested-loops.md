# Nested Loops

Nested Loops layers three or more narratives inside each other like Russian nesting dolls. You open Story A, pause it to open Story B, pause that to open Story C — the innermost story — which carries the core message. Then you close C, close B, and close A in reverse order. Each outer story provides context, emotional framing, or stakes for the story inside it. The technique comes from oral storytelling traditions (notably *One Thousand and One Nights*) and works for blog posts because the open loops create cognitive tension — the reader's brain wants closure on each unfinished story, which keeps attention locked even during abstract or technical middle sections. The innermost story lands hardest because the reader has been primed by the outer layers.

## The Steps

### 1. Open Story A (The Frame)

Begin the outermost story. Establish characters, stakes, and a situation — then leave it unresolved. This is the emotional hook.

**In a post**: "Last March, our CEO pulled me into a room and said, 'We're losing our biggest customer. You have two weeks.' I want to tell you how that ended. But first, I need to tell you about something that happened six months earlier."

**Section approach**: The opening section — no H2 yet, or a minimal one. Two to three paragraphs. Set the scene vividly. The cliffhanger should feel natural, not gimmicky. Leave the situation unresolved at the end of this section with a deliberate pivot sentence.

**Writing techniques**: Plain prose for narrative. A blockquote for a key line of dialogue makes it vivid. `@aside` for context-setting that would interrupt the scene (company size, team structure) without defusing the tension.

**BFM usage**: `@aside` works well for factual context the reader needs but that would slow the story — keep the cliffhanger clean.

### 2. Open Story B (The Context)

Pause Story A and begin a second story. This one provides context or a parallel perspective that the reader will need to understand the core message.

**In a post**: "Six months before that meeting, we'd made a decision about our API versioning strategy. At the time, it seemed obvious. Here's what we chose — and why."

**Section approach**: One H2 section, four to six paragraphs. This is where technical content begins. The reader accepts it because they're still waiting for Story A's resolution. Move through the context steadily — the open loop from Story A is doing the work of keeping them engaged.

**Writing techniques**: Code blocks with explanatory paragraphs for the technical decision. Comparative paragraphs or a table for the options that were on the table. A code walkthrough walking through the chosen approach.

**BFM usage**: `@details` for implementation specifics that only some readers need. `@aside` for historical context or related decisions.

### 3. Open Story C (The Core Message)

Pause Story B and open the innermost story. This is the post's key insight, lesson, or technical revelation. It should be the most focused, concrete, and memorable part.

**In a post**: "But to understand why that versioning choice mattered, I need to show you what happens inside a single API request when versions collide."

**Section approach**: Two to four H2 sections — this is the heart of the post. Code walkthroughs, deep technical content, step-by-step analysis. Spend the most word budget here. Paragraph density can be highest here because the reader is most engaged.

**Writing techniques**: Code blocks with granular explanatory paragraphs for the deep dive. A code block with shell syntax for demonstrating the failure mode. An `@aside` for the critical insight. Progressive argument building via numbered or bulleted lists for sequences of events.

**BFM usage**: `@aside` for critical "this is the important thing" framing. `@details` for optional depth on implementation specifics.

### 4. Close Story C (The Lesson)

Resolve the innermost story. State the insight explicitly. This is the moment the reader has been waiting for without knowing it.

**In a post**: "The version collision doesn't just break the request — it corrupts the cache for every subsequent request. One bad call poisons thousands. That's the bug."

**Section approach**: One H2 section, two to three paragraphs. Clear, declarative resolution. This should feel like a revelation. Short sentences. Let each one land before moving to the next.

**Writing techniques**: A bold summary paragraph or short bulleted list for the technical takeaway. A blockquote for a crisp one-liner distilling the insight — this is the sentence the reader will remember.

**BFM usage**: `@aside` for implications or related patterns that extend the lesson without cluttering the main resolution.

### 5. Close Story B (The Connection)

Return to Story B with new understanding. The reader now sees the earlier decision differently.

**In a post**: "Now you see why our versioning choice mattered. We'd picked the approach that made version collisions possible. Not inevitable — but possible. And at scale, possible means guaranteed."

**Section approach**: One H2 section, two to four paragraphs. Recontextualize the earlier decision. Show the causal chain from decision to consequence. The reader should feel the click of pieces fitting together.

**Writing techniques**: A bulleted list with progressive argument for the causal chain. Comparative paragraphs revisiting the earlier decision with new knowledge — the reader should see it differently the second time. An `@aside` for the hindsight insight ("This is the kind of decision that seems obvious in retrospect and invisible at the time").

**BFM usage**: `@aside` for the meta-lesson about how this class of decision gets made.

### 6. Close Story A (The Resolution)

Return to the outermost story and close it. The resolution should now feel inevitable — the reader has all the context they need.

**In a post**: "So when the CEO said 'two weeks,' I knew exactly where to look. We shipped the fix in four days. The customer stayed. But the real win wasn't the fix — it was finally understanding why our 'obvious' decisions aren't."

**Section approach**: One H2 section, two to four paragraphs. Resolve the emotional hook. Connect the personal story to the universal lesson. The final paragraph is the post's thesis — stated plainly now that the reader has earned it.

**Writing techniques**: A bold summary paragraph for final takeaways. A blockquote for a closing reflection — the sentence that captures the post's meaning. The closing paragraph should stand alone as a complete summary.

**BFM usage**: `@aside` for related reading. Avoid `@details` here — the close should be clean and unhurried.

## Length Tier Mapping

### Short Post (800–1500 words)

Use only 2 loops (A and B). Three is too many for a short post:
- **Open A** (~150 words): Quick emotional hook with a cliffhanger
- **Open + Close B** (~700 words): The core technical content as a complete inner story
- **Close A** (~250 words): Resolution and takeaway

The inner story IS the post. The outer story is just the hook and landing.

### Medium Post (1500–3000 words)

Full 3-loop structure:
- **Open A** (~250 words): Establish the frame story
- **Open B** (~400 words): Context and technical setup
- **Open + Close C** (~900 words): Deep technical core
- **Close B** (~300 words): Recontextualize the decision
- **Close A** (~350 words): Emotional resolution and takeaways

### Long Post (3000–5000 words)

3-4 loops with expanded inner stories:
- **Open A** (~400 words): Rich frame story with multiple characters and stakes
- **Open B** (~600 words): Detailed context with code walkthrough
- **Open C** (~300 words): Setup for the core
- **Core D / Close D** (~1400 words): Extended technical deep-dive with annotated code
- **Close C** (~400 words): First-level implications
- **Close B** (~600 words): Systemic analysis
- **Close A** (~600 words): Full resolution, broader lessons

Adding a 4th loop is only worth it if each layer genuinely adds a distinct perspective. Don't nest for nesting's sake.

## When to Use

- **Postmortem and incident posts**: The natural structure of "what happened → why it happened → the root cause → what we learned" maps perfectly to nested loops. Each layer peels back a cause.
- **Posts where context is essential but boring alone**: Technical context (API design, architecture decisions) becomes compelling when the reader is waiting for an outer story to resolve.
- **Persuasion through discovery**: When you want the reader to feel like they uncovered the insight themselves, nesting stories creates a breadcrumb trail.
- **Posts with a personal + technical dual thread**: The outer loop is personal/emotional; the inner loop is technical. They reinforce each other.

## When NOT to Use

- **Posts where the reader needs information fast**: Nested Loops delays the core message by design. If the reader is there for a specific answer (a how-to, a tutorial), the delayed resolution feels like stalling.
- **When you don't have 3+ genuine stories**: Forcing nested structure on a single narrative creates artificial detours. If there's only one story, use Story Circle.
- **When combined with Sparkline**: The oscillation of Sparkline and the nesting of Nested Loops create competing rhythms. Pick one.

## Example Mapping

### "The Versioning Incident" — A Postmortem Post

| Loop | Action | Words | Content |
|------|--------|-------|---------|
| A | Open | ~200 | "CEO says we're losing our biggest customer. Two weeks." |
| B | Open | ~400 | "Six months ago, we chose our API versioning strategy. Here's what we picked." |
| C | Open | ~200 | "What happens inside a single request when versions collide?" |
| C | Close | ~400 | "One bad call poisons the cache. That's the bug." |
| B | Close | ~300 | "Our 'obvious' versioning choice made this possible. At scale, possible = guaranteed." |
| A | Close | ~400 | "Fixed in 4 days. Customer stayed. The lesson: audit your 'obvious' decisions." |

### "How I Learned to Stop Worrying and Love the Monorepo" — An Architecture Post

| Loop | Action | Words | Content |
|------|--------|-------|---------|
| A | Open | ~200 | "My team was mass-quitting. The reason surprised me." |
| B | Open | ~400 | "Our microservices architecture, 18 months in. 47 repos." |
| C | Open + Close | ~900 | "Tracing a single feature across 6 repos. The dependency graph. The deploy coordination. The ownership ambiguity." |
| B | Close | ~400 | "The architecture wasn't the problem — the repo boundary was. Same architecture, one repo." |
| A | Close | ~400 | "Nobody quit. The frustration was the tooling friction, not the work. Monorepo migration took 3 sprints." |

## Closure Techniques

The close of each loop must feel satisfying. Three approaches:

- **Echo**: Repeat a phrase or image from the opening. "Remember what the CEO said? Here's what I said back." The callback rewards close reading.
- **Reframe**: Return to the same situation but with changed meaning. "Six months earlier, we called it 'the obvious choice.' Now we call it 'the expensive assumption.'"
- **Resolve**: Answer the literal question or cliffhanger from the opening. "Four days. The fix was four days. The customer stayed."

The outermost loop's closure carries the most emotional weight. Invest in its landing — it is both the end of the story and the beginning of the reader's takeaway.

## Combination Notes

- **Nested Loops + False Start**: Powerful combination. Use a False Start as Story A — open with the "wrong" story, interrupt it, then nest the real stories inside. When you close Story A at the end, the reader sees why the initial story was wrong.
- **Nested Loops + Socratic Path**: Make the innermost story a Socratic sequence of questions. The outer stories provide context; the inner story is a guided discovery.
- **Nested Loops + Petal Structure**: Use Nested Loops as the macro structure but make Story C a Petal — multiple mini-stories around the core insight. Works in long posts where the core message has multiple facets.
- **Avoid**: Don't combine with Sparkline (competing rhythms) or Converging Ideas (the merging of separate threads conflicts with the opening/closing symmetry of loops).
