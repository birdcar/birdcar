# Framework Guide

Decision engine for selecting a narrative framework for a blog post. Used during Stage 2 of the new-post skill.

## 1. Quick Reference Table

| # | Name | Family | One-line Description | Best For |
|---|------|--------|---------------------|----------|
| 1 | Three-Act Structure | Foundational | Setup, confrontation, resolution in three clean beats | `general`, `educational`, `any-length` |
| 2 | Freytag's Pyramid | Foundational | Five-phase arc with rising action, climax, and falling action | `deep-dive`, `postmortem`, `long` |
| 3 | Story Circle (Dan Harmon) | Foundational | Eight-step hero's journey adapted for technical writing | `journey`, `transformation`, `medium` |
| 4 | Kishotenketsu | Foundational | Four-act structure with twist instead of conflict | `surprising-insight`, `reframing`, `no-villain` |
| 5 | Sisyphean Arc (Camus) | Existential | Recurring struggle reframed as meaningful through persistence | `ops`, `support`, `reliability` |
| 6 | Kafkaesque Labyrinth (Kafka) | Existential | Navigating absurd bureaucratic or systemic complexity | `enterprise`, `legacy-systems`, `process-critique` |
| 7 | Existential Awakening (Sartre) | Existential | Confronting radical freedom and the weight of choosing your tools | `career`, `architecture-decisions`, `greenfield` |
| 8 | Stranger's Report (Camus) | Existential | Detached, observational analysis of a system's inner contradictions | `audit`, `code-review`, `incident-review` |
| 9 | The Waiting (Beckett) | Absurdist | Tension and meaning found in the space where nothing happens | `async`, `queues`, `distributed-systems` |
| 10 | The Metamorphosis (Kafka) | Absurdist | Waking up to discover everything has fundamentally changed | `migration`, `breaking-changes`, `rewrite` |
| 11 | Catch-22 (Heller) | Absurdist | Exposing circular logic and no-win constraints in systems | `tech-debt`, `tradeoffs`, `impossible-requirements` |
| 12 | Comedian's Set | Absurdist | Setup-punchline rhythm with callbacks and escalating bits | `short`, `high-energy`, `entertainment` |
| 13 | In Medias Res | Non-linear | Open in the middle of the action, then rewind to explain | `incident`, `demo-first`, `hook-heavy` |
| 14 | The Spiral | Non-linear | Revisit the same concept at increasing depth each pass | `layered-concept`, `progressive-disclosure` |
| 15 | The Rashomon | Non-linear | Same event told from multiple perspectives | `architecture`, `cross-team`, `empathy`, `tradeoffs` |
| 16 | Reverse Chronology | Non-linear | Start with the outcome and work backward to the cause | `postmortem`, `debugging`, `root-cause` |
| 17 | The Sparkline (Duarte) | Rhetorical | Alternate between "what is" and "what could be" to build desire | `vision`, `product`, `persuasion` |
| 18 | Nested Loops | Rhetorical | Layer stories inside stories, resolving them in reverse order | `storytelling`, `long`, `multiple-anecdotes` |
| 19 | The Petal | Rhetorical | Multiple independent stories that all support one central thesis | `examples-heavy`, `diverse-audience`, `medium` |
| 20 | Converging Ideas | Rhetorical | Separate threads that merge into a single conclusion | `interdisciplinary`, `synthesis`, `multi-topic` |
| 21 | The False Start | Rhetorical | Begin with a conventional approach, then reveal why it fails | `refactoring`, `paradigm-shift`, `myth-busting` |
| 22 | The Socratic Path | Rhetorical | Drive the post through questions the reader is already asking | `educational`, `interactive` |

## 2. Auto-Suggest Decision Matrix

### Tone to Family Mapping

| Tone | Primary Family | Secondary Family |
|------|---------------|-----------------|
| Educational | Foundational | Rhetorical |
| Provocative | Existential | Absurdist |
| Storytelling | Foundational | Non-linear |
| Technical | Non-linear | Foundational |
| Educational + Storytelling | Foundational | Non-linear |
| Provocative + Storytelling | Existential | Absurdist |

### Length Tier to Complexity Filter

| Length Tier | Max Complexity | Excluded Frameworks |
|-------------|---------------|-------------------|
| Short (800-1500) | Low | Freytag's Pyramid, Story Circle (full 8-step), Nested Loops, The Spiral, Reverse Chronology |
| Medium (1500-3000) | Medium | Nested Loops (risky, needs tight control) |
| Long (3000-5000) | High | Comedian's Set (hard to sustain in prose), In Medias Res (impact fades at length) |

Complexity ratings:
- **Low**: Three-Act, Kishotenketsu, In Medias Res, Comedian's Set, The False Start, The Petal
- **Medium**: Story Circle, Sparkline, Socratic Path, Converging Ideas, Catch-22, Sisyphean Arc, Stranger's Report, The Metamorphosis, The Waiting
- **High**: Freytag's Pyramid, Nested Loops, The Spiral, The Rashomon, Reverse Chronology, Kafkaesque Labyrinth, Existential Awakening

### Readership Adjustment

| Readership | Formality | Effect |
|------------|-----------|--------|
| Technical practitioners | Low-Medium | All families viable. Existential and Absurdist resonate (shared context). |
| Leaders/managers | Medium-High | Favor Rhetorical and Foundational. Back claims with data. |
| General tech audience | Medium | Favor Foundational and Rhetorical. Minimize assumed context. |
| Mixed/broad | Medium | Favor low-complexity frameworks. Every section must stand alone. |

### Topic Type Signals

| Topic Type | Strong Signal Frameworks | Rationale |
|------------|------------------------|-----------|
| Support Engineering | Sisyphean Arc, Catch-22, Story Circle | Recurring struggle, impossible constraints, transformation arcs |
| Developer Tools | The False Start, Sparkline, In Medias Res | "Old way was wrong", vision-casting, demo-first hooks |
| Product | Sparkline, Converging Ideas, The Petal | Persuasion, synthesis, multi-example proof |
| Technical Deep-dive | The Spiral, Freytag's Pyramid, Story Circle | Layered depth, classical arc, transformation journey |
| Career/Growth | Existential Awakening, Three-Act, Nested Loops | Freedom of choice, clean arc, layered personal stories |
| Incident/Postmortem | Reverse Chronology, In Medias Res, Stranger's Report | Work backward from outcome, start in the action, detached analysis |

### Code-Heavy Adjustment

When `code_heavy` is true, deprioritize frameworks that lack natural code insertion points:

| Framework | Code Affinity | Notes |
|-----------|--------------|-------|
| Story Circle | High | Code fits naturally in Search, Find, Return |
| The Spiral | High | Code deepens on each pass |
| Three-Act | High | Code in Act 2 (confrontation) |
| Sparkline | Medium | Code in "what could be" segments |
| In Medias Res | High | Open with the code that broke/worked |
| Freytag's Pyramid | High | Code throughout rising/falling action |
| Comedian's Set | Low | Code breaks comedic rhythm |
| Nested Loops | Low | Story layering conflicts with code focus |
| Kishotenketsu | Medium | Code in twist (ten) phase |
| Existential Awakening | Low | Abstract framework, code feels forced |
| The Waiting | Medium | Code for the "waiting" visualization |
| The Metamorphosis | Medium | Before/after code comparisons |
| Catch-22 | Medium | Code that demonstrates the circular constraint |
| Kafkaesque Labyrinth | Low | Narrative-driven, code is secondary |
| Sisyphean Arc | Medium | Code in the recurring cycle |
| Stranger's Report | Medium | Code as evidence in the report |
| The Rashomon | High | Same code from different perspectives |
| Reverse Chronology | High | Walk backward through code changes |
| The Petal | Medium | Code examples as individual petals |
| Converging Ideas | Medium | Code threads that merge |
| The False Start | High | Show the wrong code, then the right code |
| The Socratic Path | High | Code answers each question |

## 3. Scoring Algorithm

Start every framework at zero. Apply modifiers in order:

1. **Family Affinity (0-3 pts)**: Primary family = 3, secondary family = 1, others = 0
2. **Length Filter (pass/fail)**: Eliminate frameworks exceeding complexity ceiling for the chosen length tier
3. **Topic Signal Boost (+3 pts)**: If framework appears in strong signal list for the topic type
4. **Readership Modifier (+1 or -1 pt)**: +1 if family is favorable for readership, -1 if risky
5. **Code Affinity (+2/0/-2 pts)**: Only when code_heavy is true. High = +2, Medium = 0, Low = -2
6. **Tiebreaker**: Prefer lower complexity, then earlier in the table

Present the top 2 scoring frameworks. For each: name, family, 2-3 sentence explanation of why it fits, and a sketch of how the topic maps to the framework's structure.

If top score is ≤3, flag weak match and ask the user to describe their post's "shape" in their own words.

## 4. Framework Compatibility Notes

### Universal Openers
**In Medias Res** works as an opening for nearly any framework. Start with a dramatic moment, then transition to the chosen framework's structure.

### Strong Combinations
- **Sparkline + Sisyphean Arc**: "What is / what could be" oscillation maps onto recurring cycles
- **The Spiral + Socratic Path**: Each spiral pass answers a deeper question
- **The Petal + Converging Ideas**: Independent examples that merge in conclusion
- **Three-Act + The False Start**: Act 1 is the false start, the break becomes the Act 1/2 boundary

### Combinations to Avoid
- **Nested Loops + The Spiral**: Confusion about going deeper vs resolving outer stories
- **Reverse Chronology + Kishotenketsu**: Running backward removes the twist's surprise
- **Kafkaesque Labyrinth + Socratic Path**: Disorientation vs clarity work against each other

## 5. Family Overviews

**Foundational**: Reliable, clear structures audiences instinctively understand. Use when structure should disappear and content dominate.

**Existential**: Give language to the absurdity of working in technology. Treat struggle as a condition to navigate, not a problem to solve.

**Absurdist**: Find humor and irony in contradictions. Name the elephant and refuse to pretend it's not there.

**Non-linear**: Break chronological order to serve comprehension. Rearrange time, perspective, or depth.

**Rhetorical**: Persuasion engines. Move readers from one position to another through deliberate structural choices.
