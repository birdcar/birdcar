# Implementation Spec: New Post Skill - Phase 1

**Contract**: ./contract.md
**Estimated Effort**: L

## Technical Approach

Create the core skill infrastructure at `.claude/skills/new-post/`. This phase builds the SKILL.md (the workflow orchestrator), voice.md (derived from analyzing all 5 existing blog posts), writing-guide.md (blog-specific pacing, density, BFM patterns), framework-guide.md (adapted auto-suggest algorithm for blog parameters), and bfm-reference.md (directive catalog with usage guidance).

The pattern to follow is the talks repo's `new-talk` skill structure. The SKILL.md mirrors the staged workflow (Entry → Gathering → Framework Selection → Content Generation → Review) but replaces all slide-specific stages with blog-specific ones. The framework guide reuses the same 22 frameworks, scoring algorithm, and family/complexity/affinity tables — but swaps slide-count targets for word-count tiers and component suggestions for BFM directive suggestions.

The voice.md is the novel artifact. It's derived from close analysis of all 5 existing blog posts and captures Nick's writing style as a reference the skill instructs the agent to follow during content generation.

## Feedback Strategy

**Inner-loop command**: `cat .claude/skills/new-post/SKILL.md | head -5` (verify files exist and have content)

**Playground**: Manual review — skill files are markdown reference documents, not executable code.

**Why this approach**: These are authored reference documents. Validation is structural (files exist, cross-references are correct, no broken internal links) and qualitative (content quality).

## File Changes

### New Files

| File Path | Purpose |
|-----------|---------|
| `.claude/skills/new-post/SKILL.md` | Main skill workflow — staged orchestration for blog post generation |
| `.claude/skills/new-post/references/voice.md` | Nick's writing voice profile derived from existing blog posts |
| `.claude/skills/new-post/references/writing-guide.md` | Blog-specific pacing, density, section structure, BFM patterns |
| `.claude/skills/new-post/references/framework-guide.md` | Auto-suggest algorithm adapted for blog parameters (word-count tiers, reading experience) |
| `.claude/skills/new-post/references/bfm-reference.md` | BFM directive catalog with usage guidance and examples |

### Modified Files

None.

### Deleted Files

None.

## Implementation Details

### 1. Voice Reference (`voice.md`)

**Pattern to follow**: Ghostwriter plugin's `voice.md` concept (voice profile for consistent writing)

**Overview**: Analyze all 5 existing blog posts to extract Nick's writing voice characteristics. This file is a reference document the SKILL.md instructs the agent to read before generating content.

**Key characteristics to extract and document**:

- **Perspective**: Second-person "you" to pull readers into scenarios; first-person for personal experience and opinions
- **Tone**: Direct, conversational, unafraid of strong opinions. Not academic — writes like talking to a smart colleague
- **Rhetorical devices**: Italics for emphasis and internal monologue (*The queue continues to explode.*), rhetorical questions as transitions, parallel structure for building momentum, em dashes for parenthetical asides
- **Structure**: Opens with narrative/anecdote before introducing the argument. Long-form paragraphs that build momentum rather than bullet-heavy lists. Section headers that are punchy statements, not descriptive labels ("Welcome to the doom cycle", not "The Problem with Metrics")
- **Language**: Informal register, occasional profanity when it serves emphasis ("Your metrics are bullshit"), contractions throughout, pop culture and literary references
- **Argumentation**: Builds cases through extended scenarios the reader recognizes from their own experience. Cites academic/authoritative sources (Goodhart's Law, Campbell's Law) but explains them conversationally. Acknowledges counterarguments genuinely before addressing them
- **BFM patterns**: Uses `@aside` for epigraphs and editorial context. Blockquotes for authoritative citations with attribution

**Implementation steps**:

1. Read all 5 blog posts (already done during ideation — content is known)
2. Write `voice.md` with sections: Overview, Perspective & Point of View, Tone & Register, Rhetorical Devices, Structural Patterns, Language & Vocabulary, Argumentation Style, BFM Usage Patterns
3. Include 2-3 direct examples from existing posts for each characteristic
4. Add a "Don't" section covering anti-patterns (corporate speak, hedging, passive voice, generic transitions)

### 2. Writing Guide (`writing-guide.md`)

**Pattern to follow**: `talks/.claude/skills/new-talk/references/design-guide.md` (adapted from slide design to blog writing)

**Overview**: Blog-specific guide covering pacing, section density, and BFM usage patterns. Replaces the slide design guide's component selection, visual rhythm, and animation sections.

**Key sections to include**:

- **Core philosophy**: Blog posts are self-contained arguments. Every section should earn its place. The voice.md defines *how* to write; this guide defines *what* to write and *how much*.
- **Word count tiers**: Short (800-1500), Medium (1500-3000), Long (3000-5000). For each tier: section count, section depth, example structure.
  - Short: 2-4 sections, focused single-point argument, one anecdote max
  - Medium: 4-7 sections, multi-faceted argument with examples, 2-3 anecdotes
  - Long: 6-10 sections, comprehensive treatment, multiple anecdotes, academic/source citations
- **Section structure**: How to open sections (hook or transition, not topic sentence), paragraph density (3-6 sentences for narrative, shorter for punchlines), when to break for a new section
- **Pacing**: Alternate between narrative (story/scenario) and analytical (argument/evidence) sections. Never stack 3+ analytical sections. End sections with forward momentum.
- **BFM usage guide**: When to use each directive — `@aside` for epigraphs, editorial notes, tangential context; `@details` for collapsible deep-dives; blockquotes for citations. Include concrete examples.
- **Opening patterns**: Start with a scenario (like "Your metrics are bullshit"), a provocative statement (like "Joy Matters"), a personal anecdote (like "All Hands Support"), or a challenge to the reader (like "Stop Giving Me Take Homes")
- **Closing patterns**: Circle back to the opening scenario with resolution, issue a direct challenge to the reader, or restate the thesis with earned authority
- **Code in posts**: When to include code blocks (only when the argument requires it), keep under 15 lines, always explain what the code shows

### 3. Framework Guide (`framework-guide.md`)

**Pattern to follow**: `talks/.claude/skills/new-talk/references/framework-guide.md` (direct adaptation)

**Overview**: Reuses the same 22 frameworks, families, scoring algorithm, and compatibility notes. Adapts the parameter inputs and filtering for blog posts instead of presentations.

**Key adaptations**:

- **Quick Reference Table**: Keep all 22 frameworks. Replace "Best For" tags with blog-appropriate tags where needed (e.g., `lightning` → `short`, `standard` → `medium`, `extended` → `long`)
- **Tone to Family Mapping**: Same mapping, works for both media
- **Duration to Complexity Filter → Length Tier to Complexity Filter**: Map word-count tiers to complexity ceilings:
  - Short (800-1500): Max Low complexity. Same exclusions as Lightning talks.
  - Medium (1500-3000): Max Medium complexity. Same as Standard talks.
  - Long (3000-5000): Max High complexity. Same as Extended talks.
- **Audience → Readership adjustment**: Replace presentation audiences (Conference, Meetup, etc.) with blog audiences:
  - Technical practitioners (developers, SREs, support engineers)
  - Leaders/managers
  - General tech audience
  - Mixed/broad audience
- **Topic Type Signals**: Same mappings — these are Nick's topics regardless of medium
- **Code-Heavy Adjustment**: Same logic but with blog context (code blocks in posts vs code slides)
- **Scoring algorithm**: Identical 6-step procedure with adapted parameter names
- **Compatibility notes**: Same combinations work in prose as in presentations

### 4. BFM Reference (`bfm-reference.md`)

**Overview**: Catalog of BFM directives available in the blog's markdown parser, with usage examples and guidance on when each directive serves a narrative purpose.

**Implementation steps**:

1. Check the blog repo for BFM parser config or documentation to find all supported directives
2. If not documented in-repo, reference the [BFM spec](https://github.com/birdcar/markdown-spec) and existing post usage
3. Document each directive with: syntax, when to use it, when NOT to use it, example from existing posts

**Known directives from existing posts**:
- `@aside title="..."` / `@endaside` — Used for epigraphs, editorial context, attribution notes
- Standard blockquotes (`>`) — Used for authoritative citations with `-- Author` attribution

### 5. SKILL.md (Main Workflow)

**Pattern to follow**: `talks/.claude/skills/new-talk/SKILL.md` (direct structural adaptation)

**Overview**: The orchestrator. Defines the staged workflow with all 4 entry paths, framework selection, content generation, and review stages.

**Stages**:

**Stage 0: Entry Path** — Use `AskUserQuestion` to determine input type:
- "I have a brain dump or notes" → Stage 1-B
- "I have a transcript" → Stage 1-T
- "I have an existing draft" → Stage 1-D
- "Starting from scratch" → Stage 1

**Stage 1-B: Brain Dump Analysis** — Accept messy input, extract topic/tone/key message/length estimate. For large brain dumps (>2000 words), use a Haiku subagent for extraction. Confirm inferences with user. Collect missing details (title, target readership).

**Stage 1-T: Transcript Analysis** — Same as talks skill's Stage 1-T but estimate word count for written form (transcripts are ~130 words/min spoken, blog target is different). Extract structural outline. Confirm inferences.

**Stage 1-D: Existing Draft Analysis** — Read the draft, identify its current structure (or lack thereof), extract topic/tone/message. Ask user what they want to change: restructure with a framework, tighten the prose, expand sections, or all of the above.

**Stage 1: From Scratch** — Interactive gathering: title, key message, tone, readership, word count tier, topic area, code-heaviness. Same batched `AskUserQuestion` pattern as talks skill.

**Stage 2: Narrative Framework** — Read `references/framework-guide.md`. Score frameworks against gathered parameters. Present top 2 recommendations. Map the selected framework to blog sections (not slides). Review and adjust.

**Stage 3: Content Generation** — Read `references/voice.md` and `references/writing-guide.md` before writing.
- Create file at `src/content/blog/{slug}.md`
- Write frontmatter: title, description, date (today), tags (inferred from topic), draft: true
- Write full blog post following the framework mapping, voice profile, and writing guide
- Use BFM directives where narratively appropriate (consult `references/bfm-reference.md`)
- Content completeness: 90%+ for transcript/brain dump/draft paths, 85%+ for from-scratch
- Mark genuinely missing content with `[TODO: ...]`

**Stage 4: Review and Iterate** — Present summary (word count, framework, sections). Use `AskUserQuestion` for feedback: adjust structure, adjust tone, expand/cut sections, looks great.

**Key differences from the talks skill**:
- No scaffolding script — just write the file directly
- No design/variant stage — blog has no theme variants
- No dev server verification — markdown doesn't need rendering to validate
- Voice reference is mandatory reading before generation
- Content completeness target is higher (90%+ vs 60-80%)

**Implementation steps**:

1. Write SKILL.md frontmatter (name, description, trigger/avoid conditions)
2. Write Stage 0 (entry path selection)
3. Write Stage 1-B (brain dump), Stage 1-T (transcript), Stage 1-D (existing draft), Stage 1 (from scratch)
4. Write Stage 2 (framework selection) — reference `framework-guide.md`
5. Write Stage 3 (content generation) — reference `voice.md`, `writing-guide.md`, `bfm-reference.md`
6. Write Stage 4 (review and iterate)
7. Write error handling section

## Testing Requirements

### Manual Testing

- [ ] SKILL.md references all 4 reference files by correct relative paths
- [ ] Framework guide's quick reference table lists all 22 frameworks
- [ ] Framework guide's scoring algorithm is adapted for blog parameters (length tiers, readership)
- [ ] Voice.md includes concrete examples from existing blog posts
- [ ] Writing guide covers all 3 word count tiers with structural guidance
- [ ] BFM reference documents all directives found in existing posts
- [ ] No cross-references to slide-specific concepts (components, speaker notes, slide counts) remain

## Validation Commands

```bash
# Verify all expected files exist
ls -la .claude/skills/new-post/SKILL.md
ls -la .claude/skills/new-post/references/voice.md
ls -la .claude/skills/new-post/references/writing-guide.md
ls -la .claude/skills/new-post/references/framework-guide.md
ls -la .claude/skills/new-post/references/bfm-reference.md

# Check for accidental slide/presentation references in non-framework files
grep -ri "slide\|presentation\|speaker note\|slidev\|component" .claude/skills/new-post/SKILL.md .claude/skills/new-post/references/writing-guide.md .claude/skills/new-post/references/voice.md || echo "No slide references found (good)"

# Verify framework guide has all 22 frameworks
grep -c "^|" .claude/skills/new-post/references/framework-guide.md
```

## Open Items

- [ ] Confirm full list of BFM directives supported by the blog's parser (may need to check the markdown-spec repo)
- [ ] Determine if any additional entry paths would be useful (e.g., "from a talk" to reuse slides as source material — deferred to future considerations)

---

_This spec is ready for implementation. Follow the patterns and validate at each step._
