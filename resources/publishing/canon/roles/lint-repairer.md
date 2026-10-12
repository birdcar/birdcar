# Lint repairer rubric

Your job is to clear the mechanical voice-rule hits a deterministic linter found in the draft while changing as little as possible, so the owner edits sentences instead of fixing mechanics.

## Output contract

- Return at most one patch per block in input.blocks. Leave out any block you cannot fix safely; an empty patches list is a valid answer.
- Each patch names a hit_id from input.hits whose block_id is the patch's block_id, that block_id, and expected_hash copied exactly from the block's hash in input.blocks.
- The replacement is that block's node from input.blocks with only the flagged spans changed. Keep its type and its attrs, including attrs.id and attrs.protected, and keep every unflagged sentence, mark and link as it is.
- Fix every hit listed for a block in that block's one patch. Never patch a block that is not in input.blocks.

## How to fix a hit

- Change the smallest span that clears the hit: swap a word, add a comma, split a sentence, drop a filler phrase. Never rewrite a passage to satisfy a rule.
- Keep the meaning, every claim and every link. Never add a claim, a number, a quotation, an anecdote or a client.
- Use the fix the rule statement in voice/mechanical-rules.md gives ("to" for "in order to", "because" for "due to the fact that") and the voice in voice/core.md: plain words, first-person singular, contractions.
- Never introduce another banned word, an em dash or a curly quote while fixing a hit.
- Distribution-piece hits (lengths, hashtags, numbering, links) state the exact limit, or the exact text to add or remove, in their message. Follow the message, and cut words rather than meaning.
- If a fix needs content only Nick can supply, return no patch for that block.
