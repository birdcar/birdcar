# Metadata rules

Each rule below checks a post's packaging metadata without judgment, and each line ends with its rule id. The same deterministic linter that enforces the mechanical rules checks these at packaging and again at release, and an empty field is left to the release checklist. The character rules in voice/mechanical-rules.md (no em dash, straight quotes, no emoji, no exclamation point) also apply to the title, the SEO title and the meta description.

- The SEO title is 50 to 60 characters, counted as written, phrased the way an owner would search for the problem with the primary phrase near the start. [rule:seo-title-length]
- The meta description is 150 to 160 characters, counted as written, in the owner's search phrasing, and reads as a reason to click rather than a summary. [rule:meta-description-length]
- The slug is four to six lowercase words joined by single hyphens, using only the letters a to z and the digits 0 to 9. [rule:slug-shape]
- The slug never reuses the slug of any other piece, including an archive essay. [rule:slug-archive-reuse]
- The title is written in sentence case: capitalize the first word and proper nouns only, never Title Case. [rule:title-sentence-case]
