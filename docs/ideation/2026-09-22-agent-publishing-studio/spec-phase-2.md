# Implementation Spec: Agent Publishing Studio — Phase 2

**Contract**: ./contract.md
**Phase**: Structured documents and archive import
**Estimated effort**: L
**Prerequisite**: Publishing foundation and release integrity

## Technical Approach

Implement `App\Services\Publishing\ArticleDocument` as the server authority for validation, canonicalization and static HTML rendering. Use a versioned envelope around **Tiptap-compatible JSON**, not a second incompatible AST. This avoids lossy HTML persistence and unnecessary bidirectional schema translation. Render typed nodes directly with escaped text; do not re-interpret literal editor text as Markdown.

Import the complete original archive into Phase 1's `Article`, `ArticleRevision` and `ArticleRelease` records. Parse Markdown/front matter/directives using installed facilities; generate provenance and parity manifests in the imported release payload. A dedicated import-history subsystem is unnecessary for this baseline migration. The command reports each source/result and defaults to a no-write dry run. The public reader remains unchanged until Phase 5.

Read `.ai/rules/{general,resources,writing}.md`, relevant Laravel/testing skills and installed versions before implementation. Use existing `Str::markdown`, Symfony YAML and PHP DOM facilities rather than new production dependencies. No package installation, production import, boot-time import or deployment is authorized. The disposable editor spike is evidence, not a runtime dependency.

## Decisions Considered and Rejected

- **Whole archive editable** — rejected read-only legacy and on-demand migration.
- **Structured blocks** — rejected opaque HTML blobs, flattened figures and interchangeable Markdown/rich-text editing modes.
- **Canonical Tiptap-compatible JSON** — rejected relying on stock Flux HTML serialization to preserve custom content.
- **Restricted static SVG source for new diagrams** — source plus preview, not Mermaid infrastructure, an interactive drawing canvas or executable markup. Existing diagram presets retain their static meaning.
- **Create-once/no-clobber import** — rejected replacing later CMS revisions with historical source or importing on every application boot.
- **Preserve original files** — rejected changing source to fit the importer; compare to `72f7d8ad8521573cb224022c902447f9ca4c4351` even after implementation commits.
- **Truthful charts** — reject hard-coded axes, division by zero and silent data coercion.
- **No early public cutover** — fidelity and delivery integration precede changing public data sources.

## Feedback Strategy

**Inner-loop command**: `php artisan test --compact tests/Feature/Publishing/DocumentRoundTripTest.php`

**Playground**: Pest fixtures and the original archive as read-only input.

**Why**: Canonicalization, parsing and rendering are deterministic; narrow tests find losses before browser integration.

## File Changes

### New Files

| File Path | Purpose |
| --- | --- |
| `app/Services/Publishing/ArticleDocument.php` | Validate/canonicalize/render supported JSON |
| `app/Services/Publishing/ArticleDocumentValidationException.php` | Path-addressed validation failures |
| `app/Services/Publishing/ArchiveMarkdownImporter.php` | Existing Markdown/directive to document conversion |
| `app/Services/Publishing/ArchiveSourceManifest.php` | Source fingerprints and independent parity summaries |
| `app/Actions/Publishing/ImportWritingArchive.php` | Explicit dry-run/create-once import |
| `app/Console/Commands/PublishingImportArchive.php` | Operator command with explicit write/actor options |
| `resources/views/components/marketing/article-source-diagram.blade.php` | Safe static SVG-source figure wrapper |
| `tests/Feature/Publishing/DocumentRoundTripTest.php` | Supported blocks, safety and failed-save non-mutation |
| `tests/Feature/Publishing/ArchiveMigrationTest.php` | Whole-archive parity and import safety |
| `tests/Fixtures/Publishing/archive/minimal.md` | Valid mixed-content fixture |
| `tests/Fixtures/Publishing/archive/data/minimal-chart.json` | Chart fixture |
| `tests/Fixtures/Publishing/archive/malformed-unknown-directive.md` | Unknown directive rejection |
| `tests/Fixtures/Publishing/archive/malformed-missing-chart.md` | Missing figure data rejection |

### Modified Files

| File Path | Changes |
| --- | --- |
| `app/Actions/Publishing/WriteArticle.php` | Validate all saved documents before writes; preserve human-only protection changes |
| `app/Actions/Publishing/ManageArticleRelease.php` | Freeze server-rendered document payloads |
| `resources/views/components/marketing/article-chart.blade.php` | Dynamic scales and zero/one-row cases without losing existing data/markup meaning |
| `tests/Feature/MarketingDiagramTest.php` | Preserve current semantics and add truthful edge-case rendering assertions |

### Deleted Files

None. Existing PSR-4 autoloading and Artisan command discovery are sufficient; no composer change or import-history tables are needed.

## Implementation Details

### 1. Document API and native editor shape

**Pattern to follow**: `app/Actions/ReadWriting.php` for safe rendering requirements, Phase 1 `PublishingFingerprint` for digest conventions.

Expose `validate(array $document): void`, `canonicalize(array $document): array`, `renderHtml(array $document): string`, and `fingerprint(array $document): string`, with PHPDoc array shapes. Invalid input raises field/node-path errors before any revision is appended.

```json
{
  "version": 1,
  "type": "doc",
  "content": [
    {
      "type": "paragraph",
      "attrs": {"id": "blk_0123456789abcdef", "protected": false},
      "content": [{"type": "text", "text": "A link", "marks": [{"type": "link", "attrs": {"href": "/writing/example/"}}]}]
    }
  ]
}
```

Use Tiptap names: `paragraph`, `heading` (`attrs.level` 2/3), `blockquote`, `bulletList`, `orderedList` (`attrs.start`), `listItem`, `codeBlock`, `horizontalRule`, `hardBreak`, `text`; marks `bold`, `italic`, `strike`, `code`, `link` with `attrs.href`. The original archive includes a horizontal rule; silently dropping it is not acceptable. Custom nodes are `note`, `callout`, `chart`, `diagram`; Phase 3 registers exactly these names. Stable IDs/protection belong in `attrs`, not as unknown top-level ProseMirror keys. The editor strips only the outer `version` for `setContent`, restoring it on serialization. Normalize permitted Tiptap default/null attributes consistently rather than rejecting defaults that the installed editor necessarily emits.

For block IDs, authored nodes use `blk_` plus 16 lowercase hex characters; imported nodes use deterministic `imp_` identifiers derived from source/block position. Require unique IDs. Preserve array order and literal authored strings; canonicalization must not trim prose or meaning-bearing captions/titles. Text nodes cannot contain child nodes. Bound document bytes, depth and node count with explicit constants and actionable errors (initial ceilings: 1 MiB, depth 32, 10,000 nodes).

Protection is explicit on selected blocks/passages. Human-authorized saves may change protection; server actions record who made the change if audit metadata is retained. An agent proposal cannot clear protection or modify/remove protected blocks by supplying new attrs. Do not introduce conflicting rules where the editor cannot send an authorized protection change at all.

Links permit HTTP(S), mailto, root-relative non-protocol-relative paths and fragments; reject controls, unsafe schemes, credentials and malformed destinations. Escape text directly according to node type. Render only known HTML elements/attributes; link target/rel are server-normalized. No arbitrary raw HTML, event attributes or styles from the document. Return semantic HTML using existing marketing note/chart/diagram components where compatible.

**Feedback loop** — Playground: round-trip tests. Experiment: every node/mark, literal asterisks and angle brackets, Tiptap default attrs, duplicate IDs, nested lists, HR, unsafe URLs, oversized/deep documents and rejected saves leaving the prior revision intact. Check: `php artisan test --compact tests/Feature/Publishing/DocumentRoundTripTest.php`.

### 2. Semantic notes, charts and diagram source

Notes/callouts have escaped `attrs.title` and nested prose; callout style is a bounded enum such as `key`. Use the current note semantic HTML. An imported title/caption stays unchanged, not reworded by an agent or formatter.

Charts store `attrs.chartType` (`bar`/`line`), `x`, one series `{key,label}`, data rows, caption and width. Exactly one numeric series matches this archive and the first-release scope; reject unsupported multiple series rather than silently ignoring them. Accept 1–200 rows, nonempty x labels and finite nonnegative numeric values; reject strings pretending to be numbers if conversion would change source meaning. Generate a truthful scale starting at zero with an upper bound at/above the maximum. All-zero data draws at baseline; a one-point line is centered. Always provide complete accessible labels and the underlying data table. Preserve original values and ordering, including duplicates.

Diagram attrs use `sourceType: "preset"` with `name: "walkthrough"|"reporting"`, or `sourceType: "svg"` with editable `source` containing restricted SVG XML; both have a caption. Presets invoke the existing static components without adding animation. New-source editing is a code textarea and server preview, not a canvas or a new JSON drawing language.

Validate SVG using PHP DOM with network access disabled and no entity expansion. Reject DOCTYPE, processing instructions and external entities before parsing; require the SVG namespace/root. Allow only static `g`, `path`, `line`, `polyline`, `polygon`, `rect`, `circle`, `ellipse`, `text`, `tspan`, `title`, `desc`. Allow finite geometry/path data and a bounded set of presentation attributes. Colors are server palette values, simple validated hex, `none` or `currentColor`; classes are a fixed diagram palette allowlist. Reject events, script, `foreignObject`, `image`, `use`, styles, animations, href/xlink references, `url(...)`, data URLs, arbitrary namespaces, filters and masks. Limit source size, depth and element counts. Reconstruct output from validated nodes/attrs; never echo raw input XML. Require an accessible title/description or generate one from the approved caption. Preview displays only this server-validated output.

**Feedback loop** — Playground: document and existing diagram tests. Experiment: archive values, `[0,0]`, one row, negative/NaN data, both presets, a valid source diagram, each active SVG construct and XML entity attack. Check: `php artisan test --compact tests/Feature/Publishing/DocumentRoundTripTest.php tests/Feature/MarketingDiagramTest.php`.

### 3. Whole-archive parser and fidelity

**Pattern to follow**: `ReadWriting::render()` directive grammar and its front-matter logic. Keep `resources/writing/**` read-only.

`ArchiveMarkdownImporter::parseDirectory(string $directory): array` returns a PHPDoc-shaped list of slug, metadata, canonical document, source manifest and parity manifest; it does not persist models. Parse all input before importing any records. Use installed YAML/Markdown facilities and explicit DOM-to-node mapping for prose fragments. Markdown conversion happens at import only, not on ordinary rich-editor text. Missing/unknown/malformed directives, unexpected HTML/nodes, broken chart data or unsupported content produce an article/path/line error and fail preflight. Do not flatten or skip them.

Support `@figure` chart data restricted to real files under the source data directory, the two existing diagram presets, `@aside` and `@callout`, including note-local author shorthand's existing `/authors/...` destinations. Validate resolved paths to reject traversal/symlink escape. Honor original date parsing, metadata and source filenames as stable slugs. Report excluded draft/future source entries explicitly; current baseline contains ten published essays. Derive directive counts from the actual sources, not invented fixed counts.

For each article, capture original Markdown/data SHA-256s, title/description/date/tags/slug, normalized textual runs, ordered link destinations, note titles, chart exact rows/labels/values, diagram preset/caption semantics and HTML/document hashes. Compare imported output to the independent existing reader plus direct source/data inspection; a manifest produced only from the new renderer and compared to itself cannot detect loss. Do not make byte-identical HTML a requirement where harmless markup differs; text, data, destinations and meaning must match.

**Feedback loop** — Playground: `ArchiveMigrationTest.php` with small malformed fixtures plus the real archive. Experiment: all baseline entries, note author links, HR/list formatting, missing JSON, unknown directive and path escape. Check: `php artisan test --compact tests/Feature/Publishing/ArchiveMigrationTest.php`.

### 4. Explicit importer and provenance

Expose `ImportWritingArchive::dryRun(string $sourceDirectory, string $baselineCommit): array` and `write(string $sourceDirectory, string $baselineCommit, User $operator): array`. Reports contain counts, per-source outcomes, parity/fingerprint details and errors. **Dry run performs zero database writes**, including audit/run records. Persist provenance in created release payloads, not an extra import-run subsystem.

Command: `publishing:import-archive`, with `--dry-run` (default), `--write`, `--source=resources/writing`, `--baseline=72f7d8ad8521573cb224022c902447f9ca4c4351`, `--actor=<existing-user-id>`, and `--confirm-production-write`. Dry run needs no actor. Write requires an identified existing actor authorized for publishing writes and explicit operator approval; production additionally requires the confirmation flag. Never guess an account or create it. A flag alone is not an agent's authorization to modify production.

After successful full preflight, perform the small baseline import in a transaction, locking conflicting article identities and respecting unique slugs. Create revision 1 and an `origin=import` historical release, set live/working pointers and original public date, with no fabricated approval rows. Existing matching import identities report already imported; any existing CMS edits or unrelated slug collision are reported without clobbering. Do not update existing article records during a repeat import. Failure rolls back new records and pointers; no partial silent success.

Imported release payload includes schema version, canonical document, `{htmlVersion, html, hash}`, original metadata/date/slug/reading-time, and archive baseline/source/parity manifests. Later live revisions may differ; original provenance remains. There are no additional schema migrations in this phase unless a demonstrably missing field from Phase 1's stated interface requires a narrowly scoped follow-up migration.

**Feedback loop** — Playground: importer command through Pest with test factories. Experiment: no-write dry run, first import, repeat, user-edited existing record, unrelated slug collision, malformed preflight, transaction failure and production write without confirmation. Check: `php artisan test --compact tests/Feature/Publishing/ArchiveMigrationTest.php`.

## Testing Requirements

| Test file | Required coverage |
| --- | --- |
| `DocumentRoundTripTest.php` | Exact canonical supported-node round trips, safe markup, same renderer for preview/public, chart/SVG edge cases, rejected save does not mutate |
| `ArchiveMigrationTest.php` | Every baseline essay and referenced datum, independent fidelity oracle, repeat/no-clobber, no-write dry run, explicit actor, rollback, source fingerprints |
| Existing public suites | Current public reader remains working before cutover; no weakened assertions |

Manual checks compare notes/charts/diagrams and text to existing public output on desktop/mobile and no-JS/print. These checks are not replaced by byte hashes.

## Failure Modes

| Component | Trigger/failure | Impact | Mitigation |
| --- | --- | --- | --- |
| Document | Incompatible Tiptap node names/attribute positions | Blocks/IDs disappear at reload | Native JSON conventions and Phase 3 real browser round-trip |
| Renderer | Text re-parsed as Markdown | Authored characters change meaning | Escape typed text directly |
| Chart | Single row/zero maximum/large values | False scale or division error | Dynamic bounds and explicit degenerate cases |
| Diagram | Active XML/SVG or external entity | XSS, network access or resource exhaustion | Parse restrictions, allowlists, reconstruction and limits |
| Import | Unsupported directive/HTML/data | Silent archive loss | Complete preflight fails with location |
| Repeat import | Newer CMS edit exists | Lost work | Create-once; no update of existing identities |
| Parity | Compare new renderer with itself | False confidence | Legacy reader plus direct source/data oracle |
| Source | Committed rewrite hides in clean worktree | Original evidence lost | Pinned Git-baseline diff and stored hashes |

## Validation Commands

```bash
php artisan test --compact tests/Feature/Publishing/DocumentRoundTripTest.php tests/Feature/Publishing/ArchiveMigrationTest.php
php artisan test --compact tests/Feature/MarketingSiteTest.php tests/Feature/MarketingDiagramTest.php tests/Feature/MarketingDiscoveryTest.php
vendor/bin/pint --dirty --format agent
composer types:check
git diff --exit-code 72f7d8ad8521573cb224022c902447f9ca4c4351 -- resources/writing
```

Use the commit skill when available, with `docs/ideation/2026-09-22-agent-publishing-studio/spec-phase-2.md` verbatim in the phase commit body. Do not stage unrelated files.

## Rollout and Boundaries

Code/tests and an optional local no-write dry run only. Real imports require the explicit operator action above. Public cutover waits for Phase 5. Phase 3 consumes `ArticleDocument::renderHtml`; editor extensions must match this exact schema and fail visibly rather than fall back to lossy HTML.
