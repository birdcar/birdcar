# Birdcar.dev — UI Kit

A click-through prototype of the personal site at **birdcar.dev**, built from the brand foundations in `../../README.md` and `../../colors_and_type.css`.

## Files

- `index.html` — the entry point. Loads all components and renders an interactive site with working navigation.
- `Masthead.jsx` — left-aligned brand + nav.
- `Footer.jsx` — double-rule footer with email, GitHub, RSS.
- `Button.jsx` — primary, secondary, link variants.
- `PostListItem.jsx` — one row of the writing index.
- `pages/Home.jsx` — landing page.
- `pages/About.jsx` — bio.
- `pages/Work.jsx` — kinds of systems Birdcar builds.
- `pages/Writing.jsx` — full blog index, sectioned by topic.
- `pages/Post.jsx` — a single blog post (long-form prose).
- `pages/Contact.jsx` — email-forward + short form.

## Notes

- All copy is in the established voice (sentence case, first person, no hype, no emoji).
- Blog post titles and dates are plausible placeholders. Replace with real content.
- The "view post" CTA on the Writing page navigates to the same single Post page (Drake export notes) regardless of which row is clicked — it's a stand-in.
