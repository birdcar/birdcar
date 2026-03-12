# New Post Skill Contract

**Created**: 2026-03-12
**Confidence Score**: 96/100
**Status**: Approved

## Problem Statement

Writing blog posts from scratch is slow. The birdcar.dev blog (Astro, BFM markdown) has strong narrative voice and structure, but getting from raw idea to publishable draft requires manually choosing a narrative structure, organizing thoughts, and writing in BFM format. The talks repo already solves this problem for presentations with a "new-talk" skill that uses 22 narrative frameworks to transform brain dumps or transcripts into structured Slidev decks.

The same frameworks apply to blog writing — the topics overlap (support engineering, developer tools, career/growth) — but presentations and blog posts are fundamentally different media. Slides are sparse scaffolding for spoken words; blog posts are self-contained long-form arguments. A direct port of the talks skill would produce the wrong kind of output.

## Goals

1. **Reuse all 22 narrative frameworks** from the talks repo, adapted with blog-specific structural guidance (section mapping, paragraph structure, BFM directive placement) instead of slide-specific guidance (component selection, slide counts, speaker notes)
2. **Support 4 entry paths**: brain dump/notes, transcript, from scratch, and existing draft — each producing a 90%+ complete blog post
3. **Generate full blog files** at `src/content/blog/{slug}.md` with correct Astro frontmatter (`title`, `description`, `date`, `tags`, `draft: true`) and BFM content
4. **Offer tiered word count targets** (short: 800-1500, medium: 1500-3000, long: 3000-5000) that influence framework selection, section depth, and pacing guidance
5. **Actively use BFM directives** (`@aside`, `@details`, etc.) where they serve the narrative — epigraphs, tangential context, collapsible deep-dives
6. **Write in Nick's voice** by analyzing existing blog posts to generate a voice reference file that captures his writing style — direct and conversational, second-person "you" to pull readers in, parenthetical asides, italics for emphasis and internal monologue, rhetorical questions as transitions, unafraid of strong opinions and profanity, narrative anecdotes before arguments, long-form paragraphs that build momentum

## Success Criteria

- [ ] Skill SKILL.md follows the staged workflow pattern (entry path → info gathering → framework selection → content generation → review)
- [ ] All 22 framework reference files are adapted for blog writing (section structure, paragraph guidance, BFM usage — not slides/components)
- [ ] Framework guide's auto-suggest algorithm works with blog-specific parameters (tone, length tier, topic, code-heaviness)
- [ ] Each entry path (brain dump, transcript, from scratch, existing draft) has explicit steps and produces a complete `.md` file
- [ ] Generated posts include correct Astro frontmatter with `draft: true`
- [ ] Generated posts use BFM directives (`@aside`, `@details`) where narratively appropriate
- [ ] The skill uses `AskUserQuestion` for all interactive steps (no bare text questions)
- [ ] A blog-specific writing guide replaces the slide design guide (covering voice, pacing, section density, BFM usage patterns)
- [ ] A `voice.md` reference file captures Nick's writing voice, derived from analysis of all existing blog posts, and the skill instructs the agent to follow it

## Scope Boundaries

### In Scope

- SKILL.md with staged workflow adapted for blog posts
- All 22 framework references rewritten for blog structure (not slides)
- Framework guide with blog-specific auto-suggest algorithm
- Blog writing guide (pacing, density, BFM patterns)
- Voice reference file (`voice.md`) derived from analyzing existing blog posts
- BFM directive reference (what directives exist, when to use each)
- File scaffolding (create `src/content/blog/{slug}.md` with frontmatter)
- 4 entry paths: brain dump, transcript, from scratch, existing draft

### Out of Scope

- Slide/presentation output — that's the talks repo's job
- Theme/design changes to the blog — the skill generates content, not styling
- Publishing workflow (removing `draft: true`, building, deploying)
- Image generation or selection — posts are text-focused
- SEO optimization beyond the `description` frontmatter field

### Future Considerations

- Integration with the talks skill (turn a talk into a post or vice versa)
- Voice/tone auto-calibration (re-analyze posts periodically to update voice.md)
- Series support (multi-part posts with cross-references)

## Execution Plan

### Dependency Graph

```
Phase 1: Core Skill Infrastructure (SKILL.md, voice.md, writing-guide.md, framework-guide.md, bfm-reference.md)
  └── Phase 2: Framework References (all 22 adapted framework files)
```

### Execution Steps

**Strategy**: Sequential (Phase 2 depends on Phase 1 for cross-reference consistency)

1. **Phase 1 — Core Skill Infrastructure** _(blocking)_
   ```
   /execute-spec docs/ideation/new-post-skill/spec-phase-1.md
   ```

2. **Phase 2 — Framework References** _(blocked by Phase 1)_
   ```
   /execute-spec docs/ideation/new-post-skill/spec-phase-2.md
   ```
   Note: Phase 2 can internally parallelize across family batches (5 batches of 4-6 frameworks each) using subagents, but must run after Phase 1 completes.
