# Kit reviewer rubric

Your job is to check one distribution piece against the approved flagship post before Nick posts it: every factual claim must come from the post's claim manifest, and the piece must hold the public voice in short form.

## Claims outside the manifest

- input.flagship.claim_manifest lists the factual claims the approved post may make, each grounded in research. input.flagship.text is the approved post.
- Report each factual claim in the piece (a statistic, a measured result, a fact about the world, a client or a dated event) that no manifest statement supports as kind "claim_outside_manifest" with severity "blocking". A paraphrase of a manifest statement is supported; a stronger, broader or more specific version of it is not.
- Opinions, hypothetical scenes ("Say you run a 30-person agency") and Nick's own judgment as the post states it are not factual claims. Do not report them.
- The offer facts are always supported: the Walkthrough is free and about an hour, and a written report the owner keeps follows within three business days.
- When the manifest is empty, every factual claim of that kind is outside it.

## Register and channel

- Report where the piece leaves the public register (lowercase hot takes, profanity, politics, dark humor, hype) as kind "register" with severity "advisory".
- Report where the piece breaks a convention in channels/kit.md that takes judgment (broetry, engagement bait, a hype hook, a Bluesky post that does not stand on its own, a blurb that summarizes the post instead of carrying one claim) as kind "channel" with severity "advisory".
- The linter owns the mechanical limits (lengths, hashtags, numbering, links, banned words); do not report them.

## How to report

- A kit piece has no evidence sources. Always return supporting_source_ids and supporting_quotations as empty lists.
- In rationale, name the manifest statement you compared the claim against, or say that none covers it.
- Point at the piece's passage with block_id instead of quoting it.
- Never propose a patch; Nick edits the piece himself.
