---
name: new-post
description: >-
  Creates blog posts for birdcar.dev from brain dumps, transcripts, existing drafts, or from scratch.
  Uses 22 narrative frameworks to structure long-form writing in Nick's voice with BFM directives.
  Use when the user asks to "write a blog post", "create a new post", "turn this into a blog post",
  "draft a post about", or wants to structure writing for birdcar.dev.
  Do NOT use for editing existing published posts, light copy edits to existing drafts,
  managing the Astro site, or creating presentations (use the talks repo's new-talk skill for slides).
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
- Generated content should be 90%+ complete — mark genuinely missing content with `[TODO: ...]` only where the input is vague
- For large inputs (>2,000 words), delegate extraction to a Haiku subagent via Agent tool with this prompt: "Extract from the following text: (1) topic area, (2) dominant tone (Educational/Provocative/Storytelling/Technical), (3) key message in one sentence, (4) approximate word count, (5) programming languages mentioned, (6) structural outline (8 bullet points max). Return only these six items."

## Stage 0: Entry Path

Use `AskUserQuestion` to determine input type:

| Option | Description | Next Stage |
|--------|-------------|------------|
| "I have a brain dump or notes" | Messy thoughts, bullet points, voice dictation | Stage 1-B |
| "I have a transcript" | Talk recording, podcast, interview | Stage 1-T |
| "I have an existing draft" | Rough draft that needs restructuring | Stage 1-D |
| "Starting from scratch" | Interactive topic exploration | Stage 1 |

If the user provided source material with the command invocation, infer the entry path from the content type.

## Stage 1-B: Brain Dump Analysis

1. Accept the input (file path via `Read` or inline text)
2. Extract: topic area, tone, key message, estimated word count, code languages, narrative arc, structural outline
3. For inputs >2,000 words, delegate extraction to a Haiku subagent (see Critical Rules for prompt template)
4. Present extracted parameters via `AskUserQuestion` for confirmation
5. Collect missing details: working title (suggest 3 options), target readership, word count tier

## Stage 1-T: Transcript Analysis

1. Collect transcript (file path or inline)
2. Extract same parameters as 1-B, plus: estimate written word count (transcripts run ~130 words/min spoken; blog posts are denser)
3. Present inferences for confirmation
4. Collect: working title, target readership, word count tier

## Stage 1-D: Existing Draft Analysis

1. Read the draft file
2. Identify current structure (or lack thereof), topic, tone, message
3. Use `AskUserQuestion`: "What do you want to change?"
   - Restructure with a narrative framework
   - Tighten the prose
   - Expand sections
   - All of the above
4. Extract parameters from the draft for framework selection

## Stage 1: From Scratch

Use `AskUserQuestion` in batched calls (1-2 rounds):

**Round 1**: Title (working), key message ("What's the one thing readers should remember?"), tone (Educational, Provocative, Storytelling, Technical), readership (Technical practitioners, Leaders/managers, General tech audience, Mixed)

**Round 2**: Word count tier (Short 800-1500, Medium 1500-3000, Long 3000-5000), topic area (Support Engineering, Developer Tools, Product, Technical Deep-dive, Career/Growth, Other), code examples needed (yes/no, which languages)

If user says "I don't know" to key message, ask: "What problem are you reacting to?" or "What surprised you about this topic?"

## Stage 2: Narrative Framework

Read `${CLAUDE_SKILL_DIR}/references/framework-guide.md`.

### Step 2A: Select Framework

Read `${CLAUDE_SKILL_DIR}/references/framework-guide.md` now (required before scoring). Score frameworks against gathered parameters using the auto-suggest algorithm defined in Section 3 of that file. Present top 2 via `AskUserQuestion`:

- Option 1: Best framework + 1-sentence reasoning
- Option 2: Second framework + 1-sentence reasoning
- Option 3: "Show me all frameworks"
- Option 4: "I already know which one I want"

### Step 2B: Map Narrative

Read `${CLAUDE_SKILL_DIR}/references/frameworks/{selected-framework}.md`. Framework filenames use kebab-case: "Three-Act Structure" → `three-act.md`, "Freytag's Pyramid" → `freytags-pyramid.md`, "Story Circle" → `story-circle.md`, "In Medias Res" → `in-medias-res.md`. All others: lowercase, replace spaces with hyphens, drop "The" prefix for files starting with "The" (e.g., "The Spiral" → `the-spiral.md`).

**From brain dump/transcript/draft**: Use the extracted structural outline to map content to framework phases. Present the mapping.

**From scratch**: Map the topic to framework phases. Present a numbered outline with: phase name, 1-2 sentence content description, estimated word count, BFM directive suggestions.

### Step 2C: Review

Use `AskUserQuestion`: "Looks good, generate the post" / "Adjust structure" / "Try a different framework" / "Combine with another framework"

## Stage 3: Content Generation

Read `${CLAUDE_SKILL_DIR}/references/voice.md` and `${CLAUDE_SKILL_DIR}/references/writing-guide.md` before writing. Consult `${CLAUDE_SKILL_DIR}/references/bfm-reference.md` for directive syntax.

### Step 3A: Write the Post

**Slug**: Derive from the working title: lowercase, replace spaces with hyphens, remove special characters and stop words ("a", "the", "of", "in", "and"). Maximum 6 words. Example: "Why Your Support Metrics Are Bullshit" → `support-metrics-are-bullshit`.

Use `Write` to create `src/content/blog/{slug}.md`:

**Frontmatter**: Use the schema defined in `${CLAUDE_SKILL_DIR}/references/writing-guide.md` (Frontmatter section). Always set `draft: true`. Set `date` to today's date in `YYYY-MM-DD` format.

**Content**: Follow the framework mapping from Stage 2. Write in Nick's voice per `voice.md`. Use BFM directives where narratively appropriate. Target the chosen word count tier.

### Step 3B: Content Depth

- **Brain dump / transcript / draft paths**: 90-95% complete. Content drawn from source material.
- **From scratch**: 85-90% complete. `[TODO: ...]` for personal anecdotes and specific examples only.

## Stage 4: Review

Present: word count, framework used, section summary, BFM directives used.

Use `AskUserQuestion`: "Adjust structure" / "Adjust tone" / "Expand sections" / "Cut sections" / "Looks great!"

Iterate on requested changes. After each revision, re-present the `AskUserQuestion` options. On "Looks great!" or after three revision cycles (whichever comes first), confirm the file path and remind the user it's saved as `draft: true`.

## Error Handling

- **"I don't know" to title**: Suggest 3 options derived from the key message
- **"I don't know" to key message**: Ask "What problem frustrates you?" or "What do you wish people understood?"
- **No framework fits**: Try combining two. Fallback: Three-Act Structure
- **Source material is too short**: Ask for more context or switch to "from scratch" path

## References

- `${CLAUDE_SKILL_DIR}/references/voice.md` — Nick's writing voice profile
- `${CLAUDE_SKILL_DIR}/references/writing-guide.md` — Blog pacing, density, and structure guide
- `${CLAUDE_SKILL_DIR}/references/framework-guide.md` — 22 framework auto-suggest algorithm
- `${CLAUDE_SKILL_DIR}/references/bfm-reference.md` — BFM directive catalog
- `${CLAUDE_SKILL_DIR}/references/frameworks/{name}.md` — Individual framework references (22 files)
