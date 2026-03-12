# Implementation Spec: New Post Skill - Phase 2

**Contract**: ./contract.md
**Depends on**: Phase 1 (framework-guide.md and writing-guide.md must exist for cross-reference consistency)
**Estimated Effort**: L (bulk, but mechanical — 22 files following a template pattern)

## Technical Approach

Adapt all 22 narrative framework reference files from the talks repo for blog writing. Each framework file in the talks repo follows a consistent structure: description, structural steps with slide-specific guidance (components, slide counts, speaker notes), duration mapping, when to use/not use, example mapping, and combination notes.

The transformation is mechanical and repeatable: replace slide-specific guidance with blog-specific guidance (section structure, paragraph density, BFM directive placement, word count distribution). The narrative theory, when-to-use guidance, and combination notes carry over largely unchanged since they're medium-agnostic.

Use a template transformation pattern: define the transformation rules once, apply them to all 22 files. A subagent team can parallelize this — each agent gets a batch of frameworks, the template rules, and the source files from the talks repo.

## Feedback Strategy

**Inner-loop command**: `ls .claude/skills/new-post/references/frameworks/ | wc -l` (should be 22)

**Playground**: Manual review — these are reference documents.

**Why this approach**: Bulk file creation with a consistent transformation. Validation is count-based (all 22 exist) and spot-check quality (sample 2-3 for correct adaptation).

## File Changes

### New Files

| File Path | Purpose |
|-----------|---------|
| `.claude/skills/new-post/references/frameworks/three-act.md` | Three-Act Structure adapted for blog posts |
| `.claude/skills/new-post/references/frameworks/freytags-pyramid.md` | Freytag's Pyramid adapted for blog posts |
| `.claude/skills/new-post/references/frameworks/story-circle.md` | Story Circle adapted for blog posts |
| `.claude/skills/new-post/references/frameworks/kishotenketsu.md` | Kishōtenketsu adapted for blog posts |
| `.claude/skills/new-post/references/frameworks/sisyphean-arc.md` | Sisyphean Arc adapted for blog posts |
| `.claude/skills/new-post/references/frameworks/kafkaesque-labyrinth.md` | Kafkaesque Labyrinth adapted for blog posts |
| `.claude/skills/new-post/references/frameworks/existential-awakening.md` | Existential Awakening adapted for blog posts |
| `.claude/skills/new-post/references/frameworks/strangers-report.md` | Stranger's Report adapted for blog posts |
| `.claude/skills/new-post/references/frameworks/the-waiting.md` | The Waiting adapted for blog posts |
| `.claude/skills/new-post/references/frameworks/the-metamorphosis.md` | The Metamorphosis adapted for blog posts |
| `.claude/skills/new-post/references/frameworks/catch-22.md` | Catch-22 adapted for blog posts |
| `.claude/skills/new-post/references/frameworks/comedians-set.md` | Comedian's Set adapted for blog posts |
| `.claude/skills/new-post/references/frameworks/in-medias-res.md` | In Medias Res adapted for blog posts |
| `.claude/skills/new-post/references/frameworks/the-spiral.md` | The Spiral adapted for blog posts |
| `.claude/skills/new-post/references/frameworks/the-rashomon.md` | The Rashomon adapted for blog posts |
| `.claude/skills/new-post/references/frameworks/reverse-chronology.md` | Reverse Chronology adapted for blog posts |
| `.claude/skills/new-post/references/frameworks/the-sparkline.md` | The Sparkline adapted for blog posts |
| `.claude/skills/new-post/references/frameworks/nested-loops.md` | Nested Loops adapted for blog posts |
| `.claude/skills/new-post/references/frameworks/the-petal.md` | The Petal adapted for blog posts |
| `.claude/skills/new-post/references/frameworks/converging-ideas.md` | Converging Ideas adapted for blog posts |
| `.claude/skills/new-post/references/frameworks/the-false-start.md` | The False Start adapted for blog posts |
| `.claude/skills/new-post/references/frameworks/socratic-path.md` | Socratic Path adapted for blog posts |

### Modified Files

None.

### Deleted Files

None.

## Implementation Details

### Framework Transformation Template

**Pattern to follow**: `talks/.claude/skills/new-talk/references/frameworks/three-act.md` → `.claude/skills/new-post/references/frameworks/three-act.md`

**Overview**: Each framework file undergoes the same structural transformation. Define the rules here; apply them to all 22.

**Source files**: `/Users/birdcar/Code/birdcar/talks/.claude/skills/new-talk/references/frameworks/`

**Transformation rules**:

1. **Opening description**: Keep as-is. The narrative theory is medium-agnostic.

2. **Structural steps** (the core of each framework): For each step/phase/act:
   - **Keep**: The step name, purpose description, and "In a talk" example (rename to "In a post" and adjust for written form)
   - **Replace** "Slide approach" with **"Section approach"**: How many sections this step maps to, section depth (paragraphs per section), and pacing notes
   - **Replace** "Components" with **"Writing techniques"**: Which BFM directives, rhetorical devices, and structural patterns serve this step. Examples:
     - `AnimatedList` → bulleted list with progressive argument building
     - `CodeWalkthrough` → code block with surrounding explanatory paragraphs
     - `Callout type="warning"` → `@aside` directive with editorial framing
     - `QuoteBlock` → blockquote with attribution
     - `layout: section` → `## Section Header` (a new H2 as a breathing point)
     - `layout: quote` → full blockquote section (blockquote as its own section break)
     - `SpeakerCard` → no equivalent (author is implicit in a blog)
     - `TerminalDemo` → code block with shell syntax highlighting
     - `KeyPoints` → bold summary paragraph or short bulleted list
     - `layout: two-col` / `TwoColumn` → comparative paragraphs or a simple table
     - `layout: cover` → opening paragraph (the hook)
     - `layout: end` → closing paragraph (the send-off)
   - **Add** "BFM usage": Where `@aside`, `@details`, or blockquotes naturally fit in this step

3. **Duration Mapping → Length Tier Mapping**: Replace Lightning/Standard/Extended with Short/Medium/Long:
   - **Short (800-1500 words)**: Replace slide counts with word count budgets per step. Compress advice same as Lightning.
   - **Medium (1500-3000 words)**: Replace slide counts with word counts. This is the natural home for most frameworks.
   - **Long (3000-5000 words)**: Replace slide counts with word counts. Expansion advice.
   - **Word count budgets**: Distribute proportionally as the slide counts were, roughly:
     - If a talk framework allocates 20% of slides to Act I, allocate ~20% of words to Act I equivalent sections

4. **When to Use / When NOT to Use**: Keep as-is. These are medium-agnostic judgments.

5. **Example Mapping**: Adapt the table:
   - Replace "Slides" column with "Words" column (approximate word budget per step)
   - Replace slide-specific language in "Content" column with prose-specific language
   - Keep the scenario/topic — they're Nick's actual domains

6. **Combination Notes**: Keep as-is. Framework combinations work the same in prose as in presentations.

**Implementation steps**:

1. Read each source framework file from the talks repo
2. Apply the 6 transformation rules above
3. Write the adapted file to `.claude/skills/new-post/references/frameworks/{name}.md`
4. Repeat for all 22 frameworks

**Recommended execution approach**: Process frameworks in family batches for consistency:
- Batch 1: Foundational (4 frameworks) — Three-Act, Freytag's, Story Circle, Kishōtenketsu
- Batch 2: Existential (4 frameworks) — Sisyphean Arc, Kafkaesque Labyrinth, Existential Awakening, Stranger's Report
- Batch 3: Absurdist (4 frameworks) — The Waiting, The Metamorphosis, Catch-22, Comedian's Set
- Batch 4: Non-linear (4 frameworks) — In Medias Res, The Spiral, The Rashomon, Reverse Chronology
- Batch 5: Rhetorical (6 frameworks) — Sparkline, Nested Loops, The Petal, Converging Ideas, The False Start, Socratic Path

Each batch can be parallelized across subagents. Each subagent reads the source files for its batch, applies the transformation rules, and writes the output files.

## Testing Requirements

### Manual Testing

- [ ] All 22 framework files exist in `.claude/skills/new-post/references/frameworks/`
- [ ] File names match the talks repo exactly (for cross-reference consistency with framework-guide.md)
- [ ] Spot-check 3 frameworks (one from each complexity level: Low, Medium, High) for correct adaptation
- [ ] No slide-specific terms remain (slides, speaker notes, v-click, layout:, components)
- [ ] Length tier mapping is present (Short/Medium/Long with word count budgets)
- [ ] Writing techniques replace component suggestions
- [ ] BFM usage notes are included where applicable

## Validation Commands

```bash
# Verify all 22 framework files exist
ls .claude/skills/new-post/references/frameworks/ | wc -l
# Expected: 22

# Verify file names match the talks repo
diff <(ls /Users/birdcar/Code/birdcar/talks/.claude/skills/new-talk/references/frameworks/ | sort) <(ls .claude/skills/new-post/references/frameworks/ | sort)
# Expected: no differences

# Check for leftover slide-specific terms
grep -rli "slide\b\|speaker note\|v-click\|layout:" .claude/skills/new-post/references/frameworks/ || echo "No slide references found (good)"

# Check that length tiers are present in adapted files
grep -rl "Short.*800\|Medium.*1500\|Long.*3000" .claude/skills/new-post/references/frameworks/ | wc -l
# Expected: 22 (every framework should have length tier mapping)
```

---

_This spec is ready for implementation. Follow the transformation template and validate at each step._
