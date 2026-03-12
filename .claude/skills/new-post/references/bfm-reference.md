# BFM Directive Reference

Birdcar Flavored Markdown (BFM) is a CommonMark superset. These directives are available in the blog's markdown parser.

## @aside / @endaside

Block-level container for tangential content rendered as a styled aside.

**Syntax**:
```markdown
@aside title="Title Text"
Content here. Supports **markdown** formatting.
@endaside
```

**When to use**:
- Epigraphs at the top of a post: `@aside title="Epigraph"`
- Editorial context or attribution: `@aside title="Originally published"` or `@aside title="About this post"`
- Tangential context that would break the narrative flow if inline

**When NOT to use**:
- More than 2 per post (dilutes impact)
- For content that's central to the argument (put it in the main text)
- As a substitute for section breaks

**Examples from existing posts**:
```markdown
@aside title="Epigraph"
Those who believe that what you cannot quantify does not exist also believe that what you *can* quantify, *does*.
— The Tyranny of Metrics
@endaside
```

```markdown
@aside title="About this post"
Nick here: BCC is a series of anonymized posts enabling Support Professionals to publish their thoughts...
@endaside
```

## Blockquotes

Standard CommonMark blockquotes. Used for authoritative citations.

**Syntax**:
```markdown
> Quote text here.
>
> -- Author Name (Year. Publication Title)
```

**When to use**:
- Academic or authoritative citations that support the argument
- Memorable quotes from named sources
- Setting up a concept before explaining it conversationally

**When NOT to use**:
- For emphasis (use italics or bold instead)
- For your own words (use regular paragraphs)

## @details / @enddetails

Collapsible content block (renders as `<details>/<summary>`).

**Syntax**:
```markdown
@details title="Click to expand"
Hidden content here.
@enddetails
```

**When to use**:
- Deep-dive technical content that would interrupt narrative flow
- Extended code examples that support but aren't central to the argument
- Background context for readers who want more depth

**When NOT to use**:
- For content the reader must see to follow the argument
- As a substitute for editing (if it's not worth reading, cut it)

## Standard Markdown

Most content should use standard CommonMark:
- `**bold**` for emphasis on key terms
- `*italics*` for emphasis, internal monologue, and book/article titles
- `## Headings` for section breaks (H2 for main sections, H3 for subsections)
- `` `inline code` `` for technical terms, commands, filenames
- Fenced code blocks with language identifiers for code examples
- `---` for thematic breaks between major sections
