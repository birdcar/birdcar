# Implementation Spec: Consistent Voice Publishing - Phase 1

**Contract**: ./contract.md
**Estimated Effort**: L

## Technical Approach

This phase lays the foundation every later phase builds on, in two ordered steps.

**Step A moves the whole test suite from SQLite in memory to Postgres** before any model changes, so later failures are never confused with driver differences. Locally the suite uses the `birdcar_test` database on Herd's Postgres (same host and port as development: `127.0.0.1:5433`, from `.env`). CI gets a pgvector-enabled Postgres service. The existing suite must be green on Postgres at the end of Step A, committed on its own.

**Step B separates an idea from the pieces it produces.** A new `ContentIdea` model owns the owner's raw idea text and a short summary title. `Article` stays the piece model (no rename) and gains a `kind` (`PieceKind` enum: `blog_post`, `linkedin_post`, `bluesky_thread`, `email_blurb`), a `content_idea_id` and an optional `parent_article_id` (kit pieces in Phase 6 point at their flagship). The `articles.idea` column moves to `content_ideas.body` and is dropped. The Spatie `#[Sluggable(from: 'idea')]` attribute is removed: a piece gets the existing provisional `idea-` plus random slug at capture, and its real slug is chosen at release (already the case in `ManageArticleRelease`) or, from Phase 4, at packaging. No title, label or slug is ever derived from the idea. Admin surfaces show the idea's summary title as the idea (visibly labeled), and a piece's title only from its revision metadata.

The summary title is derived deterministically at capture (first non-empty line, Markdown stripped, cut at a word boundary to at most 80 characters, with an ellipsis when cut). The owner can rename the idea inline. Phase 4 lets the Interviewer propose a better summary title when the owner has not renamed it. That behavior is tracked by `summary_title_source` (`derived`, `owner`, `interviewer`).

## Decisions Considered and Rejected

_Carried from the contract; consult before making gap decisions._

- **The whole test suite moves to Postgres (birdcar_test on Herd locally, pgvector service in CI)**: rejected SQLite with a guarded vector migration, and a PHP similarity fallback. SQLite throws on vector columns and the owner chose production parity.
- **Keep Article as the piece model with a kind and parent link, plus a ContentIdea model**: rejected renaming Article to Piece. Reuses revisions, releases, attempts and their tests; the rename would churn every publishing file for no behavior change.
- **One idea owns many pieces (flagship, kit, siblings), each with its own stage**: rejected a flagship plus kit only or sibling posts only, and the 2026-09-22 exclusion of derivatives. The owner's end goal is one idea becoming several marketable pieces.
- **Refine the existing stages, keep every current stage and gate**: rejected merging research into planning, or dropping the angle gate.

## Feedback Strategy

**Inner-loop command**: `php artisan test --compact tests/Feature/Publishing/ContentIdeaTest.php`

**Playground**: Pest feature suite on Postgres (`birdcar_test`).

**Why this approach**: The work is migrations, model behavior and Livewire actions, all exercised fastest through feature tests against the real driver.

## File Changes

### New Files

| File Path | Purpose |
| --- | --- |
| `app/Models/ContentIdea.php` | Idea model: `author_id`, `summary_title`, `summary_title_source`, `body`; `pieces()` HasMany Article; `flagship()` helper; static `deriveSummaryTitle(string $body): string` |
| `app/Models/Publishing/PieceKind.php` | Backed enum `blog_post`, `linkedin_post`, `bluesky_thread`, `email_blurb`, with `label()` and `isKit()` |
| `database/factories/ContentIdeaFactory.php` | Factory with a paragraph body and derived summary title |
| `database/migrations/2026_10_02_100000_create_content_ideas_table.php` | Creates `content_ideas`, adds `content_idea_id`, `kind`, `parent_article_id` to `articles`, backfills one idea per existing article, drops `articles.idea` |
| `tests/Feature/Publishing/ContentIdeaTest.php` | Capture, summary derivation, labels, provisional slugs, archive preservation, rename, kind/parent relations |

### Modified Files

| File Path | Changes |
| --- | --- |
| `phpunit.xml` | `DB_CONNECTION=pgsql`, `DB_DATABASE=birdcar_test`; remove the SQLite `:memory:` value; keep `DB_URL` empty |
| `.github/workflows/tests.yml` | Add a `services.postgres` using `pgvector/pgvector:pg17` (database `birdcar_test`, user/password `postgres`, health check), job-level `DB_CONNECTION=pgsql`, `DB_HOST=127.0.0.1`, `DB_PORT=5432`, `DB_DATABASE=birdcar_test`, `DB_USERNAME=postgres`, `DB_PASSWORD=postgres` |
| `app/Models/Article.php` | Remove `#[Sluggable]`; fillable gains `content_idea_id`, `kind`, `parent_article_id`, loses `idea`; cast `kind` to `PieceKind`; relations `contentIdea()`, `parent()`, `children()` |
| `database/factories/ArticleFactory.php` | Replace `idea` with `content_idea_id => ContentIdea::factory()` and `kind => PieceKind::BlogPost`; provisional slug |
| `app/Actions/Publishing/WriteArticle.php` | Replace `capture(User, string $idea, ?string $slug)` with `captureIdea(User $actor, string $body): Article` (creates idea plus flagship blog piece in one transaction) and `addPiece(User $actor, ContentIdea $idea, PieceKind $kind, ?Article $parent = null): Article`; add `renameIdea(User $actor, ContentIdea $idea, string $title): void` |
| `app/Actions/Publishing/ImportWritingArchive.php` | Create a ContentIdea per archive essay (`body` and `summary_title` from the published title, source `derived`), keep slug, title, dates and URLs unchanged |
| `app/Actions/Publishing/ReadPublishedWriting.php` | Title fallback no longer reads `idea`; published releases always carry `metadata.title`, so fall back to `'Untitled'` only |
| `resources/views/components/admin/publishing/⚡dashboard.blade.php` | `saveForLater`/`developIdea` call `captureIdea`; interview input `idea` comes from `contentIdea->body`; `titleFor()` returns metadata title or the idea summary rendered as "Idea: …" through a separate `ideaLabelFor()` |
| `resources/views/components/admin/publishing/⚡article-workspace.blade.php` | `startDevelopment` passes `contentIdea->body`; `articleTitle` no longer falls back to the idea; header shows the idea summary as a distinct labeled line; inline idea rename for authors with `publishing.write` |
| `resources/views/components/admin/publishing/partials/develop.blade.php` | "The original idea" disclosure reads `contentIdea->body` |
| `resources/views/components/admin/publishing/⚡published.blade.php` | Idea line reads `contentIdea->summary_title` |
| `resources/views/components/admin/⚡index.blade.php` | Same title/idea separation as the dashboard |
| `resources/views/admin/publishing/preview.blade.php` | Fallback to "Untitled draft", never the idea |
| `resources/views/components/admin/publishing/partials/release-checklist.blade.php` | Fallback to metadata only |
| `tests/Feature/Publishing/*.php`, `tests/Feature/**` | Replace every `idea` attribute usage with the factory or `captureIdea`; fix any SQLite-only assumption found in Step A |

### Deleted Files

None.

## Implementation Details

### Step A: Postgres test suite

**Overview**: Point the suite at `birdcar_test` locally and a pgvector service in CI, and make the existing suite green.

**Implementation steps**:

1. Confirm Herd Postgres is running and `birdcar_test` exists (`psql -h 127.0.0.1 -p 5433 -U root -l` or Boost `database-query`). Do not create other databases.
2. Edit `phpunit.xml`: `DB_CONNECTION=pgsql`, `DB_DATABASE=birdcar_test`. PHPUnit `<env>` values do not override variables already set in the process, so CI's job env wins in CI and `.env` supplies host, port and user locally.
3. Edit `.github/workflows/tests.yml` with the Postgres service and job env above. `composer setup` then migrates `birdcar_test`. Keep the "Load published writing without development dependencies" step working: it runs after the suite against the same database, and `RefreshDatabase` leaves migrations (including any archive data migration) applied. Verify that step's assumption locally by running it with the CI env.
4. Run `php artisan test --compact`. Fix failures in application code when Postgres exposes a real bug (case-sensitive `LIKE`, strict typing on integer/string comparison, JSON operators, ordering without `ORDER BY`). Fix tests only when the test itself encoded a SQLite artifact.
5. Commit Step A on its own: `test(publishing): Run the suite on Postgres`.

**Feedback loop**:

- **Playground**: `php artisan test --compact`
- **Experiment**: Run the full suite once, then each failing file alone.
- **Check command**: `php artisan test --compact && ! grep -nE 'sqlite|:memory:' phpunit.xml .github/workflows/tests.yml`

### Step B: ContentIdea and piece fields

**Pattern to follow**: `app/Models/Article.php` (attribute-based `#[Fillable]`, typed relations with generics), `app/Actions/Publishing/WriteArticle.php` (authorization helper, transactions).

**Overview**: Add the idea model, move the idea text, add kind and parent to articles, and route every capture through `WriteArticle::captureIdea`.

```php
final class ContentIdea extends Model
{
    // #[Fillable(['author_id', 'summary_title', 'summary_title_source', 'body'])]

    public static function deriveSummaryTitle(string $body): string
    {
        // first non-empty line, strip leading Markdown markers (#, -, *, >, digits.),
        // collapse whitespace, cut at the last word boundary <= 80 chars, append "…" when cut;
        // never empty: fall back to "Untitled idea"
    }

    /** @return HasMany<Article, $this> */
    public function pieces(): HasMany {}

    public function flagship(): ?Article {} // first blog_post piece without a parent, oldest first
}
```

```php
public function captureIdea(User $actor, string $body): Article; // returns the flagship piece
public function addPiece(User $actor, ContentIdea $idea, PieceKind $kind, ?Article $parent = null): Article;
public function renameIdea(User $actor, ContentIdea $idea, string $title): void; // sets source=owner, max 120 chars
```

**Key decisions**:

- `captureIdea` returns the flagship Article so existing callers (dashboard redirect, attempts) keep working with a piece.
- The provisional slug stays `Str::slug('idea-'.Str::lower(Str::random(12)))`, as today. Route binding by slug (`#[RouteKey('slug')]`) is unchanged.
- `renameIdea` requires `publishing.write`, trims, rejects empty or over-120-character titles with a validation exception, and never touches any piece's metadata.

**Implementation steps**:

1. Create the enum, model, factory and migration. The migration creates `content_ideas`, adds nullable `content_idea_id`, `kind` (string, default `blog_post`), `parent_article_id`. It backfills in chunks: for each article, one idea with `body = idea` and `summary_title = ContentIdea::deriveSummaryTitle(idea)` (archive-imported articles: `summary_title = idea`, which is the published title), then sets `content_idea_id` not null, adds the foreign keys (`content_idea_id` restrict on delete, `parent_article_id` null on delete) and an index on `(content_idea_id, kind)`, then drops `articles.idea`. Write `down()` to restore `idea` from the idea body.
2. Update `Article`, `ArticleFactory`, `WriteArticle`, `ImportWritingArchive`, `ReadPublishedWriting`.
3. Update every Blade and Livewire surface listed in File Changes. A piece title is `metadata.title` or nothing; where a heading needs text before a title exists, show the idea summary prefixed with the visible label "Idea" (for example `<flux:badge>Idea</flux:badge> {{ $summary }}`).
4. Replace `idea` in every test and factory call. Search with `grep -rn "'idea'" tests database app resources`.
5. Write `ContentIdeaTest` (cases below).

**Feedback loop**:

- **Playground**: `tests/Feature/Publishing/ContentIdeaTest.php`
- **Experiment**: Capture ideas with a one-line body, a 2,000-character body, a body starting with `# Heading`, a body of only whitespace lines before text, and a non-Latin first line. Rename then re-capture. Run the archive import twice.
- **Check command**: `php artisan test --compact tests/Feature/Publishing/ContentIdeaTest.php tests/Feature/Publishing/ArticleSlugTest.php tests/Feature/Publishing/ArchiveMigrationTest.php`

## Data Model

### Schema Changes

```sql
CREATE TABLE content_ideas (
  id BIGSERIAL PRIMARY KEY,
  author_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  summary_title VARCHAR(120) NOT NULL,
  summary_title_source VARCHAR(20) NOT NULL DEFAULT 'derived', -- derived | owner | interviewer
  body TEXT NOT NULL,
  created_at TIMESTAMP, updated_at TIMESTAMP
);
CREATE INDEX content_ideas_author_id_created_at_index ON content_ideas(author_id, created_at);

ALTER TABLE articles
  ADD COLUMN content_idea_id BIGINT NOT NULL REFERENCES content_ideas(id) ON DELETE RESTRICT,
  ADD COLUMN kind VARCHAR(30) NOT NULL DEFAULT 'blog_post',
  ADD COLUMN parent_article_id BIGINT NULL REFERENCES articles(id) ON DELETE SET NULL,
  DROP COLUMN idea;
CREATE INDEX articles_content_idea_id_kind_index ON articles(content_idea_id, kind);
```

## Testing Requirements

### Feature Tests

| Test File | Coverage |
| --- | --- |
| `tests/Feature/Publishing/ContentIdeaTest.php` | New: capture, derivation, labels, slugs, rename, relations, archive preservation |
| `tests/Feature/Publishing/ArticleSlugTest.php` | Updated: no slug derives from the idea; release still canonicalizes |
| `tests/Feature/Publishing/ArchiveMigrationTest.php` | Updated: each archive essay has an idea; titles, slugs, dates and URLs unchanged |
| All other publishing tests | Updated factories; behavior unchanged |

**Key test cases**:

- `captureIdea` creates exactly one ContentIdea and one `blog_post` Article with `parent_article_id = null` and a slug matching `/^idea-[a-z0-9]{12}$/`.
- The dashboard, workspace, published list and Admin index never render the idea summary as a piece heading without the "Idea" label (assert on rendered HTML of `Livewire::test`).
- `deriveSummaryTitle` cases: one-line body returns the line; 2,000-character body returns at most 81 characters ending in "…" at a word boundary; `# Heading\nmore` returns "Heading"; whitespace-only body returns "Untitled idea".
- `renameIdea` sets `summary_title_source = owner` and does not change any revision metadata; a user without `publishing.write` gets an `AuthorizationException`.
- The migration backfill: an article created before the migration keeps its slug and gets an idea whose body equals the old idea text (migration test that seeds via raw insert before running the new migration, or a data assertion on the archive fixture).
- Archive import run twice is idempotent and produces one idea per essay.

### Manual Testing

- [ ] Capture an idea on `admin.birdcar.test/publishing` and confirm the workspace header shows the idea labeled as an idea, not as the title.

## Error Handling

| Error Scenario | Handling Strategy |
| --- | --- |
| `birdcar_test` missing or Herd Postgres stopped | Tests fail to connect; the spec's Step A instructions tell the executor to confirm the database first and stop if it is absent rather than creating databases |
| Rename with an empty or over-long title | Validation error on the Livewire field; no write |
| Unauthorized rename or capture | `AuthorizationException` from `WriteArticle`, same as today |

## Failure Modes

| Component | Failure Mode | Trigger | Impact | Mitigation |
| --- | --- | --- | --- | --- |
| Postgres switch | SQLite-only behavior hidden in app code | Case-sensitive `LIKE`, implicit casts, unordered results | Tests fail or production misbehaves | Fix the application query; add `orderBy` where order is asserted |
| CI | Production-load step finds no writing | Suite leaves `birdcar_test` without archive rows | CI red after tests pass | Verify locally with CI env; if needed run `php artisan migrate:fresh --force` before that step |
| Backfill migration | Long-running on many articles | Large archive | Deploy slowness | Chunk by 200; the table is small today |
| Labels | Idea summary leaks as a title | A surface missed in the sweep | Owner sees the idea as the title (the reported bug) | ContentIdeaTest renders every listed surface and asserts the "Idea" label |
| Slug | Provisional slug published | Release prepared without choosing a slug | Ugly public URL | Existing `slug.required`/canonicalization at release; Phase 4 adds packaging slug rules |

## Validation Commands

```bash
php artisan test --compact tests/Feature/Publishing/ContentIdeaTest.php tests/Feature/Publishing/ArticleSlugTest.php tests/Feature/Publishing/ArchiveMigrationTest.php
vendor/bin/pint --dirty --format agent
env -u AI_AGENT -u CLAUDECODE vendor/bin/phpstan analyse --no-progress
composer test
```

## Rollout Considerations

- **Feature flag**: none; the owner has no production data to protect beyond the archive, which this phase preserves.
- **Rollback plan**: the migration's `down()` restores `articles.idea`; revert the commits.
- **Rules in force**: `.ai/rules/app.md` (strict Eloquent: eager-load `contentIdea` wherever lists render it), `.ai/rules/actions.md` (public writing reads only releases), `.ai/rules/tests.md` (PHPStan level 7, prefix file-local helpers), `.ai/rules/general.md` (commit on main, linear history).

## Open Items

- [ ] None.
