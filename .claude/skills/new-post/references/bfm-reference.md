# BFM Directive Reference

Birdcar Flavored Markdown (BFM) is a CommonMark superset that includes GFM extensions. These directives and features are available in the blog's markdown parser.

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
-- The Tyranny of Metrics
@endaside
```

```markdown
@aside title="About this post"
Nick here: BCC is a series of anonymized posts enabling Support Professionals to publish their thoughts...
@endaside
```

## @callout / @endcallout

Styled callout block with semantic type for alerts, tips, and warnings.

**Syntax**:
```markdown
@callout type=info
Informational content here.
@endcallout
```

**Types**: `info`, `warning`, `tip`

**When to use**:
- `info`: Background context the reader should know
- `warning`: Gotchas, caveats, or things that could go wrong
- `tip`: Practical advice or shortcuts

**When NOT to use**:
- As decoration (every callout should earn its place)
- More than 2-3 per post (they lose impact when overused)
- For content that belongs in the main argument flow

## @details / @enddetails

Collapsible content block (renders as `<details>/<summary>`).

**Syntax**:
```markdown
@details summary="Click to expand"
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

## @figure / @endfigure

Image with caption rendered as a semantic `<figure>` element.

**Syntax**:
```markdown
@figure src="image-url-or-path" alt="Alt text description"
Caption text goes here. Supports **markdown** formatting.
@endfigure
```

**When to use**:
- Screenshots, diagrams, or photos that support the argument
- Any image that needs a caption for context

**When NOT to use**:
- Decorative images with no informational value
- When a simple inline `![alt](src)` suffices (no caption needed)

## @math / @endmath

LaTeX math block rendered via KaTeX or similar.

**Syntax**:
```markdown
@math
E = mc^2
@endmath
```

With optional label for referencing:
```markdown
@math label=quadratic
x = \frac{-b \pm \sqrt{b^2 - 4ac}}{2a}
@endmath
```

**When to use**:
- Mathematical formulas or equations that are central to the argument
- When plain text can't clearly express the notation

**When NOT to use**:
- Simple expressions that read fine as inline text (e.g., "n squared" doesn't need a math block)

## @embed / @endembed

Embedded external content (YouTube, etc.) rendered as a responsive iframe.

**Syntax**:
```markdown
@embed https://www.youtube.com/watch?v=VIDEO_ID
Optional caption or description.
@endembed
```

**When to use**:
- Referencing a specific video, talk, or external media that supports the argument

**When NOT to use**:
- As filler (link to it instead if the reader doesn't need to watch it inline)

## @query / @endquery

Dynamic content block that pulls in posts matching filter criteria.

**Syntax**:
```markdown
@query tag=bfm sort=date-desc limit=5
@endquery
```

**Parameters**: `tag`, `sort` (`date-desc`, `date-asc`), `limit`

**When to use**:
- "Related posts" sections
- Curated reading lists filtered by tag

**When NOT to use**:
- In the middle of a narrative (breaks the flow)

## @toc / @endtoc

Auto-generated table of contents from the post's headings.

**Syntax**:
```markdown
@toc depth=2
@endtoc
```

**Parameters**: `depth` (heading level depth, e.g., 2 = H2 only, 3 = H2 + H3)

**When to use**:
- Long posts (3,000+ words) where navigation helps the reader
- Reference-style posts with many sections

**When NOT to use**:
- Short or medium posts (the structure should be self-evident)
- Posts with fewer than 4 sections

## Footnotes

Standard footnote syntax for supplementary information.

**Syntax**:
```markdown
Main text with a reference[^1] and another[^label].

[^1]: Footnote content here.
[^label]: Another footnote with a descriptive label.
```

**When to use**:
- Citations or source references
- Tangential asides too small for an `@aside` block
- Definitions or clarifications that would clutter the paragraph

**When NOT to use**:
- For content the reader needs to follow the argument (put it inline)
- Excessively (more than 5-6 per post gets distracting)

## Mentions and Hashtags

Inline mentions of people and platforms, and hashtag references.

**Syntax**:
```markdown
@birdcar                          <!-- simple mention -->
@github:octocat                   <!-- platform-specific mention -->
@twitter:birdcar
@bluesky:birdcar.dev
@mastodon:birdcar@fosstodon.org
@npm:unified
@linkedin:birdcar

#testing                          <!-- hashtag -->
```

**Supported platforms**: `github`, `twitter`, `bluesky`, `mastodon`, `npm`, `linkedin`

**When to use**:
- Crediting or referencing specific people or projects
- Linking to relevant tags

## Extended Task Lists

GFM task lists with additional status markers.

**Syntax**:
```markdown
- [ ] Open task
- [x] Completed task
- [>] Scheduled task
- [<] Migrated task
- [-] Irrelevant/cancelled task
- [o] Event or occurrence
- [!] Priority/urgent task
```

**When to use**:
- **Actionable takeaways** at the end of a post or section, where plain bullets feel too flat and numbered lists feel too prescriptive
- **`[!]` Priority items** for the one or two things the reader absolutely must remember
- **`[ ]` Open tasks** for concrete "do this Monday" action items
- **`[x]` Completed items** in narrative context to show progress or resolution (e.g., in a retrospective or before/after section)
- **`[-]` Cancelled items** for approaches you tried and abandoned (good in debugging or postmortem posts)
- **`[>]` Scheduled items** for future work or deferred decisions
- Project status updates or progress tracking posts

**When NOT to use**:
- As a substitute for prose argument (weave points into the text narratively when possible)
- For simple lists where plain bullets or numbered items work fine
- More than one task list per post (dilutes impact)

**Blog examples**:
```markdown
## What to do Monday

- [!] Stop measuring individual ticket counts. Measure team-level distributions instead.
- [ ] Audit your current SLIs. Are they measuring what customers care about?
- [ ] Ask your team: "What would you fix if you had two hours outside the queue?"
- [-] Don't bother with CSAT benchmarks from other companies. Your customers are yours.
```

## GFM Features

BFM includes all GitHub Flavored Markdown extensions.

**Tables**:
```markdown
| Column 1 | Column 2 |
|----------|----------|
| Cell     | Cell     |
```

**Strikethrough**: `~~deleted text~~`

**Autolinks**: URLs are automatically linked (e.g., `https://birdcar.dev`)

## Standard Markdown

Most content should use standard CommonMark:
- `**bold**` for emphasis on key terms
- `*italics*` for emphasis, internal monologue, and book/article titles
- `## Headings` for section breaks (H2 for main sections, H3 for subsections)
- `` `inline code` `` for technical terms, commands, filenames
- Fenced code blocks with language identifiers for code examples
- `---` for thematic breaks between major sections

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
