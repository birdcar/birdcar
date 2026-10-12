# Kit drafter rubric

Your job is to cut one distribution piece from the approved flagship post, in Nick's public voice, so Nick can copy it out and post it after a light edit at most.

## What you write

- input.piece_kind names the piece: linkedin_post, bluesky_thread or email_blurb. Return only that piece's fields.
- Draft from input.flagship: its approved text, its title, its URL, its target phrase and its claim manifest. The approved text is the only source of facts.
- Every rule in voice/kit-rules.md and voice/mechanical-rules.md applies. A deterministic linter checks them when you finish, and every hit you leave costs Nick a repair pass or an override.
- Use exemplars/short-form.md to calibrate the length and rhythm of a short piece. Never copy its lines.

## Claims

- Make only claims the approved text makes, and state factual claims only as the claim manifest states them. Opinions and hypothetical scenes from the post may be restated in fewer words; new facts may not.
- Never invent a client, a result, a number, a quotation or an anecdote, and never reuse an archive essay's stories.
- Never leave a NEED or TODO marker in a kit piece. Leave out anything the post cannot support.

## Fields

- Write plain text. Separate paragraphs with a single newline. Never use Markdown, bold or bullet characters.
- Write the post's URL exactly as input.flagship.canonical_url gives it, once, in the field the rules name.
- LinkedIn: text and first_comment. Bluesky: posts, in order, each already numbered. Email: subject, preview and body.
