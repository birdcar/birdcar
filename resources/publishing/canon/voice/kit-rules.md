# Distribution kit rules

Each rule below checks a distribution kit piece without judgment, and each line ends with its rule id. The deterministic linter that enforces voice/mechanical-rules.md checks these whenever a kit piece is drafted, saved or approved, and a hit holds the piece until it is fixed or overridden with a reason. Lengths are counted in characters as written; a line break counts as one character. The mechanical rules also apply to every kit piece, except the heading rules and the blog post's own Walkthrough link.

- A LinkedIn post's text is at most 3,000 characters, and the target phrase (the search query the flagship post answers) appears in its first 210 characters, the part LinkedIn shows before "more". [rule:linkedin-length]
- A LinkedIn post carries at most three plain hashtags, all at the very end of its text, after the last sentence. [rule:linkedin-hashtags]
- A LinkedIn post's text carries no link or URL, and its first comment carries the flagship post's URL. [rule:linkedin-link-in-comment]
- Every Bluesky post is at most 300 characters, counting its i/n number. [rule:bluesky-post-length]
- Every Bluesky post carries its number as i/n at its start or end, where i is its place in the thread and n is the number of posts. [rule:bluesky-numbering]
- An email blurb's subject line is at most 50 characters. [rule:email-subject-length]
- An email blurb's body is two to four sentences. [rule:email-blurb-length]
- No kit piece links to /walkthrough; the Walkthrough invitation lives in the post. [rule:kit-no-walkthrough-cta]
- Each kit piece carries the flagship post's URL exactly once: in the LinkedIn first comment, the last Bluesky post or the email body. [rule:kit-links-post]
