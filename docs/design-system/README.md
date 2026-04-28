# Design system bundle

The design source for [birdcar.dev](https://birdcar.dev). Originally exported from [claude.ai/design](https://claude.ai/design); committed here as the canonical design reference.

This is the source bundle, not the production code. Anything that has a natural production home lives in the repo proper:

- **Tokens** (color, type, spacing, motion): `src/styles/global.css`
- **Components**: `src/components/`, `src/layouts/`, `src/pages/`
- **BFM writing system spec**: `docs/writing/WRITING.md`
- **Strategic context** (users, brand, principles): `PRODUCT.md` (repo root)
- **Visual system summary**: `DESIGN.md` (repo root)

What's here is the design-time material that doesn't have a production analog: the design conversation transcript that captured intent, the project README that established the system, the preview cards that document each token, and the JSX UI kit that was the design source for the implemented Astro pages.

## What's in this directory

```
docs/design-system/
├── README.md                  ← this file
├── chats/                     ← Claude Design conversation transcript
└── project/
    ├── README.md              ← original design system spec (history)
    ├── SKILL.md               ← agent skill manifest from claude.ai/design
    ├── assets/                ← wordmark SVGs (design source, not used in production)
    ├── preview/               ← per-token preview cards (HTML)
    └── ui_kits/birdcar-dev/   ← JSX UI kit (design source for the Astro implementation)
```

## When to read which file

- **Implementing or critiquing UI?** Read `PRODUCT.md` and `DESIGN.md` at the repo root. They are the working contract.
- **Trying to understand why a design choice was made?** `chats/chat1.md` is where the iteration lives.
- **Writing a long-form post and need the BFM directive reference?** `docs/writing/WRITING.md`.
- **Starting a fresh design surface from scratch?** `project/README.md` is the original system spec; the preview cards under `project/preview/` show each token in isolation.

## What's been removed since export

- **`project/colors_and_type.css`** — duplicated the production tokens. Source of truth is `src/styles/global.css`.
- **`project/screenshots/`** — verification screenshots from the design conversation, not part of the system per the original bundle's own README.
- **`project/WRITING.md`** — relocated to `docs/writing/WRITING.md` to live alongside other writing-system documentation.
