---
name: new-post
description: >-
  Creates blog posts for birdcar.dev from brain dumps, transcripts, existing drafts, or from scratch.
  Uses 25 narrative frameworks to structure long-form writing in Nick's voice with BFM directives.
  Use when the user asks to "write a blog post", "create a new post", "turn this into a blog post",
  "draft a post about", or wants to structure writing for birdcar.dev.
  Do NOT use for editing existing published posts, light copy edits or polish of existing drafts
  (only use for restructuring or rewriting drafts), managing the Astro site, or creating
  presentations (use the talks repo's new-talk skill for slides).
argument-hint: [topic or path to source material]
---

# New Post

Create structured, voice-consistent blog posts for birdcar.dev using narrative frameworks.

## Critical Rules

- Only use these tools: `AskUserQuestion`, `Read`, `Write`, `Agent`. Do not use Bash, WebFetch, or any other tool.
- Use `AskUserQuestion` for all interactive steps — plain text questions can't capture structured responses
- Read `${CLAUDE_SKILL_DIR}/references/voice.md` before generating any content — voice consistency is non-negotiable
- Read `${CLAUDE_SKILL_DIR}/references/writing-guide.md` before generating content — it defines pacing and density rules
- All generated posts MUST have `draft: true` in frontmatter — publishing is a separate decision
- Only insert `[TODO: ...]` markers for personal anecdotes or specific real-world examples that require information only Nick can provide. Everything else should be fully written.
- For large inputs (>2,000 words), delegate extraction to a Haiku subagent via Agent tool with this prompt: "Extract from the following text: (1) topic area, (2) dominant tone (Educational/Provocative/Storytelling/Technical), (3) key message in one sentence, (4) approximate word count, (5) programming languages mentioned, (6) structural outline (8 bullet points max). Return only these six items."
- If the framework file for a selected framework does not exist, fall back to Three-Act Structure and tell the user.
- If you are uncertain whether a passage sounds like Nick, read specific sections from `src/content/blog/your-metrics-are-bullshit.md` or `src/content/blog/joy-matters.md` as voice calibration anchors.

## Stage 0: Entry Path

If the user provided source material (brain dump, transcript, draft, or file path) with the command invocation, **skip this stage** and infer the entry path from the content type.

Otherwise, use `AskUserQuestion` to determine input type:

| Option | Description | Next Stage |
|--------|-------------|------------|
| "I have a brain dump or notes" | Messy thoughts, bullet points, voice dictation | Stage 1-B |
| "I have a transcript" | Talk recording, podcast, interview | Stage 1-T |
| "I have an existing draft" | Rough draft that needs restructuring | Stage 1-D |
| "Starting from scratch" | Interactive topic exploration | Stage 1 |

## Stage 1-B: Brain Dump Analysis

1. Accept the input (file path via `Read` or inline text)
2. Extract: topic area, tone, key message, estimated word count, code languages, narrative arc, structural outline
3. For inputs >2,000 words, delegate extraction to a Haiku subagent (see Critical Rules for prompt template)
4. Present extracted parameters via `AskUserQuestion` for confirmation
5. Collect missing details: working title (suggest 3 options), target readership, word count tier

## Stage 1-T: Transcript Analysis

1. Collect transcript (file path or inline)
2. Extract same parameters as 1-B, plus: estimate written word count (transcripts run ~130 words/min spoken; blog posts are denser)
3. For transcripts >2,000 words, delegate extraction to a Haiku subagent (see Critical Rules)
4. Present inferences for confirmation
5. Collect: working title, target readership, word count tier

## Stage 1-D: Existing Draft Analysis

1. Read the draft file
2. Extract: topic area, tone, key message, current word count, code languages, structural outline
3. For drafts >2,000 words, delegate extraction to a Haiku subagent (see Critical Rules)
4. Identify current structure (or lack thereof)
5. Use `AskUserQuestion`: "What do you want to change?"
   - Restructure with a narrative framework
   - Tighten the prose and sharpen the voice
   - Expand sections
   - All of the above
6. Extract parameters from the draft for framework selection

## Stage 1: From Scratch

Use `AskUserQuestion` in batched calls (1-2 rounds):

**Round 1**: Title (working), key message ("What's the one thing readers should remember?"), tone (Educational, Provocative, Storytelling, Technical, Reflective/Personal), readership (Technical practitioners, Leaders/managers, General tech audience, Mixed)

**Round 2**: Word count tier (Short 800-1500, Medium 1500-3000, Long 3000-5000), topic area (Support Engineering, Developer Tools, Product, Technical Deep-dive, Career/Growth, Culture/Values, Industry Practices, Other), code examples needed (yes/no, which languages)

If user says "I don't know" to key message, ask: "What problem are you reacting to?" or "What surprised you about this topic?"

## Stage 2: Narrative Framework

### Step 2A: Select Framework

Read `${CLAUDE_SKILL_DIR}/references/framework-guide.md` now (required before scoring). Score frameworks against gathered parameters using the auto-suggest algorithm defined in Section 3 of that file. Present top 2 via `AskUserQuestion`:

- Option 1: Best framework + 1-sentence reasoning
- Option 2: Second framework + 1-sentence reasoning
- Option 3: "Show me all frameworks"
- Option 4: "I already know which one I want"

### Step 2B: Map Narrative

Read `${CLAUDE_SKILL_DIR}/references/frameworks/{filename}` using this lookup table:

| Framework | Filename |
|---|---|
| Three-Act Structure | `three-act.md` |
| Freytag's Pyramid | `freytags-pyramid.md` |
| Story Circle | `story-circle.md` |
| Kishotenketsu | `kishotenketsu.md` |
| Sisyphean Arc | `sisyphean-arc.md` |
| Kafkaesque Labyrinth | `kafkaesque-labyrinth.md` |
| Existential Awakening | `existential-awakening.md` |
| Stranger's Report | `strangers-report.md` |
| The Waiting | `the-waiting.md` |
| The Metamorphosis | `the-metamorphosis.md` |
| Catch-22 | `catch-22.md` |
| Comedian's Set | `comedians-set.md` |
| In Medias Res | `in-medias-res.md` |
| The Spiral | `the-spiral.md` |
| The Rashomon | `the-rashomon.md` |
| Reverse Chronology | `reverse-chronology.md` |
| The Sparkline | `the-sparkline.md` |
| Nested Loops | `nested-loops.md` |
| The Petal | `the-petal.md` |
| Converging Ideas | `converging-ideas.md` |
| The False Start | `the-false-start.md` |
| The Socratic Path | `socratic-path.md` |
| The Dialectic | `the-dialectic.md` |
| The Jeremiad | `the-jeremiad.md` |
| The Montaigne | `the-montaigne.md` |

**From brain dump/transcript/draft**: Use the extracted structural outline to map content to framework phases. Present the mapping.

**From scratch**: Map the topic to framework phases. Present a numbered outline with: phase name, 1-2 sentence content description, estimated word count, BFM directive suggestions.

### Step 2C: Review

Use `AskUserQuestion`: "Looks good, generate the post" / "Adjust structure" / "Try a different framework" / "Combine with another framework"

**Combining frameworks**: Pick a primary framework for overall structure and a secondary for a single section that needs a different shape. Note both in the outline and in the Stage 4 summary. Check the Combination Notes in both framework files before combining.

## Stage 3: Content Generation

Read `${CLAUDE_SKILL_DIR}/references/voice.md` and `${CLAUDE_SKILL_DIR}/references/writing-guide.md` before writing. Consult `${CLAUDE_SKILL_DIR}/references/bfm-reference.md` for directive syntax.

### Step 3A: Pre-Generation Voice Checklist

Before writing any prose, internalize these voice gates. Apply them to every section:

1. **Opening test**: Does each section open with an assertion, a narrative beat, or a question? Never a topic sentence, definition, or "In this section..." framing.
2. **Perspective check**: Am I using the right perspective for this section? Second-person "you" for scenarios and reader immersion, first-person "I" for personal experience and direct opinion, "we" for industry-level critique. Switch deliberately between sections.
3. **Header check**: Is every H2 header a statement, question, or provocation? Never a descriptive label. Wrong: "The Problem", "Solution Overview", "Conclusion". Right: "Welcome to the doom cycle", "You're not gonna make the world a better place".
4. **Sentence mode**: Does this section need subordinating structure (analytical, building a case), additive structure (narrative, experience unfolding), or satiric structure (calling bullshit, subverting expectation)? Choose deliberately per section. This is a worldview choice, not just a tonal one.
5. **First/last sentence weight**: The first sentence of each section should establish a world and create hunger for what follows (Fish's "angle of lean"). The last sentence should land with full rhetorical weight even extracted from context.
6. **Shape and ring**: Read key sentences for both logical architecture (shape) and sonic quality (ring). If a sentence has the right argument but sounds flat, rework the rhythm. If it sounds good but means nothing, rework the logic.
7. **The turn**: Does each section have at least one sentence with a pivot, reversal, or subversion? Build turns into sentences, especially at section boundaries.

### Step 3B: Write the Post

**Slug**: Derive a 4-6 word slug that captures the topic. Lowercase, hyphens between words. Examples: "Why Your Support Metrics Are Bullshit" → `support-metrics-are-bullshit`. "Yetto values: Joy Matters" → `joy-matters`.

**Frontmatter**:
```yaml
---
title: "Post Title"
description: "One-line hook for RSS and meta tags — not a summary, but a reason to click"
date: YYYY-MM-DD
tags:
  - tag1
  - tag2
draft: true
---
```

Use existing tags when they fit (support, metrics, culture, hiring, values, data). Set `date` to today. Always `draft: true`.

**Content**: Follow the framework mapping from Stage 2. Apply the voice checklist from Step 3A to every section. Use BFM directives where narratively appropriate. Target the chosen word count tier.

Write the file with `Write` to `src/content/blog/{slug}.md`. All subsequent revisions overwrite the same file path.

### Step 3C: Humanization Audit

After drafting the complete post, run a two-pass audit before presenting to the user. This is mandatory — do not skip it.

**Pass 1 — Guardrail scan**: Re-read the "Don't (AI-Slop Guardrails)" section of `${CLAUDE_SKILL_DIR}/references/voice.md`. Check the draft against every rule:

- Scan for any word or phrase from the blacklist. Replace or restructure every hit.
- Scan for em dashes. Replace with commas, periods, parentheses, or restructure.
- Scan for copula avoidance ("serves as", "stands as", "functions as"). Replace with "is".
- Scan for negative parallelisms ("not just X, it's Y", "not only...but also"). Rewrite directly.
- Scan for tricolon abuse (groups of exactly three). Vary to two or four items.
- Scan for synonym cycling (same concept called different names in adjacent sentences). Pick one term.
- Scan for filler phrases ("in order to", "due to the fact that", "it is important to note"). Cut or compress.
- Check that no section opens with thesis, meta-commentary, or invitation phrasing.
- Check that the closing doesn't mirror the opening or end with an inspirational platitude.
- Check for generic transitions between sections ("Furthermore", "Additionally", "Moving on").

**Pass 2 — Soul check**: Ask yourself: "What makes this sound like an AI wrote it?" Answer honestly, then fix what you find. Look for:

- Every sentence the same length and structure (vary rhythm: short punchy sentences after long narrative ones)
- Neutral reporting without opinions (Nick has opinions — add them)
- No acknowledgment of uncertainty or mixed feelings where they'd be genuine
- No first-person where it would be natural
- No humor, edge, or personality
- Sections that could appear in any blog post about this topic (make them specific to Nick's experience and perspective)

After both passes, the draft is ready for Stage 4.

## Stage 4: Review

Present: word count, framework used, section summary, BFM directives used.

Use `AskUserQuestion`:
- "Adjust structure" — ask which sections to restructure
- "Adjust tone" — re-read the Tone & Register and Sentence Craft sections of `voice.md`, then ask: "More direct and confrontational, or more narrative and observational?"
- "Expand sections" — ask which section(s) to expand via `AskUserQuestion`
- "Cut sections" — ask which section(s) to cut
- "Looks great!"

After each revision, re-run the humanization audit (Step 3C) on changed sections, then re-present the options. On "Looks great!" or after three revision cycles (whichever comes first), confirm the file path and remind the user it's saved as `draft: true`.

## Error Handling

- **"I don't know" to title**: Suggest 3 options derived from the key message
- **"I don't know" to key message**: Ask "What problem frustrates you?" or "What do you wish people understood?"
- **No framework fits**: Try combining two. Fallback: Three-Act Structure
- **Source material is too short**: Ask for more context or switch to "from scratch" path
- **Framework file not found**: Fall back to Three-Act Structure and notify the user

## References

- `${CLAUDE_SKILL_DIR}/references/voice.md` — Nick's writing voice profile (including AI-slop guardrails)
- `${CLAUDE_SKILL_DIR}/references/writing-guide.md` — Blog pacing, density, and structure guide
- `${CLAUDE_SKILL_DIR}/references/framework-guide.md` — 25 framework auto-suggest algorithm
- `${CLAUDE_SKILL_DIR}/references/bfm-reference.md` — BFM directive catalog
- `${CLAUDE_SKILL_DIR}/references/frameworks/{name}.md` — Individual framework references (25 files)
- `src/content/blog/your-metrics-are-bullshit.md` — Voice calibration anchor (long-form jeremiad)
- `src/content/blog/joy-matters.md` — Voice calibration anchor (personal/philosophical montaigne)
