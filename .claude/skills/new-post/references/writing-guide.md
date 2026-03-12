# Blog Writing Guide

Pacing, density, and structural rules for birdcar.dev blog posts. Read alongside `voice.md` before generating content.

## Core Philosophy

Blog posts are self-contained arguments. Every section earns its place. Unlike slides (which scaffold spoken words), a blog post must stand alone — the reader has no speaker to fill gaps.

## Word Count Tiers

### Short (800–1,500 words)
- **Sections**: 2–4 (H2 headers)
- **Depth**: Focused single-point argument
- **Anecdotes**: One max, kept brief
- **Structure**: Hook → argument → prescription → close
- **Best for**: Provocative takes, focused rebuttals, single-concept explanations

### Medium (1,500–3,000 words)
- **Sections**: 4–7
- **Depth**: Multi-faceted argument with examples and evidence
- **Anecdotes**: 2–3 extended scenarios
- **Structure**: Narrative opening → problem framing → evidence/examples → prescription → close
- **Best for**: Most posts. Enough room to build a case without exhausting the reader.

### Long (3,000–5,000 words)
- **Sections**: 6–10
- **Depth**: Comprehensive treatment with multiple evidence types
- **Anecdotes**: Multiple extended scenarios, academic citations, concrete data
- **Structure**: Narrative opening → multi-section argument with alternating narrative/analytical → extended prescription → close
- **Best for**: Definitive pieces on a topic. "Your metrics are bullshit" is ~4,000 words.

## Section Structure

### Openings
Every post opens with one of these patterns:
- **Extended scenario**: Drop the reader into a vivid situation they recognize ("You're in support at Big Tech Company...")
- **Provocative statement**: Lead with the thesis, stated boldly ("I think about death kind of a lot")
- **Personal anecdote**: A specific moment that frames the argument ("I was walking around Chicago with Brian...")
- **Direct challenge**: Address the reader's assumptions head-on

Never open with: a definition, "In this post I will...", background context, or a summary.

### Body Sections
- **3–6 sentences per paragraph** for narrative. Shorter for punchlines.
- **Alternate narrative and analytical sections**: Story → argument → story → prescription. Never stack 3+ analytical sections.
- **Section headers are punchy**: Statements, questions, or provocations — not descriptive labels.
- **Each section should have a clear job**: introduce a concept, present evidence, tell a story, prescribe an action.

### Closings
- **Circle back**: Return to the opening scenario with new resolution
- **Direct challenge**: Tell the reader what to do ("If you take nothing else from this...")
- **Earned authority**: Restate the thesis with the weight of everything you've argued
- Never close with: "In conclusion", a vague call to action, or a question left hanging

## Pacing

### Visual Rhythm
Vary paragraph density across sections:
```
Dense narrative paragraph (5-8 sentences)
Short punchline paragraph (1-2 sentences)
Dense analytical paragraph (4-6 sentences)
*Italicized emphasis beat*
Dense narrative paragraph
```

### Breathing Room
- Use `---` thematic breaks between major sections sparingly (1-2 per post max)
- Use `@aside` for tangential content that would break flow
- Short italicized lines serve as rhythm breaks: "*The queue continues to explode.*"

### Code in Posts
- Only when the argument requires it
- Keep under 15 lines per block
- Always explain what the code demonstrates — don't assume the reader will parse it
- Use fenced code blocks with language identifiers

## BFM Directive Placement

| Directive | Where | Frequency |
|-----------|-------|-----------|
| `@aside title="Epigraph"` | Very top, before any content | 0–1 per post |
| `@aside title="About..."` | Top, after epigraph if present | 0–1 per post |
| `@callout` | Inline where reader needs an alert, tip, or warning | 0–3 per post |
| `@details` | Mid-post, for optional deep-dives | 0–2 per post |
| `@figure` | Wherever an image needs a caption | As needed |
| `@math` | Wherever formulas support the argument | Rare in blog posts |
| `@embed` | Where external media is referenced | 0–1 per post |
| `@toc` | Top of long posts (3,000+ words) | 0–1 per post |
| `@query` | End of post for related reading | 0–1 per post |
| Footnotes | Inline references with definitions at bottom | 0–6 per post |
| Blockquotes | Anywhere evidence supports the argument | As needed |

## Frontmatter

Every post needs:
```yaml
---
title: "Post Title"
description: "One-line summary for RSS and meta tags"
date: YYYY-MM-DD
tags:
  - tag1
  - tag2
draft: true
---
```

**Tags**: Use existing tags when they fit (support, metrics, culture, hiring, values, data). Create new ones only when no existing tag applies.

**Description**: Write for RSS readers — it should make someone click. Not a summary, but a hook.
