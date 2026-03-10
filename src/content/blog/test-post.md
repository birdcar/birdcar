---
title: Testing BFM Features
description: A test post to validate the full Birdcar Flavored Markdown pipeline
date: 2026-03-10
tags:
  - testing
  - bfm
---

# Welcome to the BFM Test Post

This post exercises every major feature of **Birdcar Flavored Markdown** to validate the rendering pipeline.

## GFM Features

### Tables

| Feature | Status | Notes |
|---------|--------|-------|
| Tables | Working | GFM extension |
| Strikethrough | Working | ~~like this~~ |
| Autolinks | Working | https://birdcar.dev |

### Strikethrough

This text has ~~strikethrough~~ formatting applied.

## BFM Directives

@callout type=info
This is an informational callout block. It should render as a styled aside element with appropriate visual treatment.
@endcallout

@callout type=warning
Be careful with this operation — it cannot be undone.
@endcallout

@details summary="Click to expand"
This content is hidden by default and revealed when the user interacts with the summary element. Useful for lengthy code examples or supplementary information.
@enddetails

## Footnotes

Birdcar Flavored Markdown supports footnotes[^1] for supplementary information. They can contain multiple references[^2] throughout the document.

[^1]: This is the first footnote with additional context.
[^2]: Footnotes are rendered at the bottom of the document.

## Mentions and Hashtags

Hey @birdcar, this post is tagged with #testing and #bfm for validation purposes. Mentions like @github should render as inline elements.

## Extended Task Lists

- [ ] Open task — not started yet
- [x] Completed task — already done
- [>] Scheduled task — planned for later
- [<] Migrated task — moved elsewhere
- [-] Irrelevant task — no longer needed
- [o] Event — something that happened
- [!] Priority task — needs attention

## Code Blocks

```typescript
import { remarkBfm } from '@birdcar/markdown';
import { unified } from 'unified';
import remarkParse from 'remark-parse';

const processor = unified()
  .use(remarkParse)
  .use(remarkBfm);

const tree = processor.parse('Hello **world**');
console.log(tree);
```

```python
def greet(name: str) -> str:
    """Generate a greeting for the given name."""
    return f"Hello, {name}!"
```

## Regular Content

This is a regular paragraph to verify that standard markdown rendering works correctly alongside the BFM extensions. It includes **bold**, *italic*, and `inline code` formatting.

Here's a [link to the project](https://github.com/birdcar/birdcar) and an ordered list:

1. First item
2. Second item
3. Third item

> A blockquote for good measure. BFM should handle these gracefully alongside its custom block directives.
