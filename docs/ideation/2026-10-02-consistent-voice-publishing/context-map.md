# Context Map: Consistent Voice Publishing

**Phase**: 1 (Content ideas and pieces)
**Gates**: 5/5 ready
**Verdict**: GO

## Gates

| Gate                 | Status | Evidence |
| -------------------- | ------ | -------- |
| Scope clarity        | ready  | The spec names every file except four, and this map adds them with the change each needs: `partials/session.blade.php:5` (the h1 that renders `$articleTitle`), `app/Actions/Admin/ReadAdminHome.php:37-60` (eager-load for the Admin index), `partials/develop.blade.php:109` (voice-sample label falls back to `idea`), and `tests/Manual/PublishingModelTrialTest.php:140,149` (`capture()` calls that PHPStan analyses). |
| Pattern familiarity  | ready  | Read `Article.php` (attributes, generic relations), `PublishingAttempt.php` (enum cast), `EditorialStage`/`ApprovalKind` enums, `WriteArticle.php` (authorize helper, typed transactions), `PublishingAttemptFactory.php`, the data migration `2026_09_24_230100_remove_publishing_budget_storage.php` (`chunkById(200)`), and the migration test `PublishingBudgetRemovalTest.php` (require, then down, seed, up). |
| Dependency awareness | ready  | Mapped every reader of `articles.idea` (13 sites in app and views), 100 `WriteArticle::capture()` calls across 15 Feature files plus 2 in Manual, the 6 factories that nest `Article::factory()`, the consumers of `ImportWritingArchive` and `ReadPublishedWriting`, and ownership checks in `ArticlePolicy`. |
| Edge case coverage   | ready  | Concrete list below: summary derivation (multibyte text, a single word longer than 80 characters, only a marker on a line, CRLF), backfill of nullable `idea`/`author_id`, the `kind` default not being on the in-memory model after create, Postgres jsonb reordering keys, the FK cascade-vs-restrict conflict, strict lazy loading, and running the import twice. |
| Test strategy        | ready  | `birdcar_test` exists on Herd PG 18.0 at 127.0.0.1:5433 (seen with `psql -l`; pgvector 0.8.1 available). Commands are listed below: inner loop, phase check, pint, phpstan, `composer test`, and the existing migration-test pattern. The scout did not run the suite because it writes to the database, so the Step A failures listed are predictions. |

## Key Patterns

- `app/Models/Article.php:14-16`: class-level attributes `#[RouteKey('slug')]`, `#[Sluggable(...)]` (remove this one) and `#[Fillable([...])]`. `casts()` returns `array<string, string>`. Relations carry generic docblocks (`@return BelongsTo<User, $this>`, `@return HasMany<ArticleRevision, $this>`). Models use `/** @use HasFactory<XFactory> */ use HasFactory;`. Every sibling model is a plain non-final `class`; the spec sketch says `final class ContentIdea`, so pick one deliberately (siblings favour non-final).
- `app/Models/PublishingAttempt.php:13-35`: enum cast `'stage' => EditorialStage::class`. Use the same form for `'kind' => PieceKind::class`.
- `app/Models/Publishing/EditorialStage.php`, `ApprovalKind.php`: backed string enums with TitleCase cases and no methods so far. `PieceKind` becomes the first enum with methods (`label()`, `isKit()`) in `App\Models\Publishing`.
- `app/Actions/Publishing/WriteArticle.php`:
  - Constructor-promoted private dependencies.
  - `authorize(User, string)` (176-181) throws `AuthorizationException('This user is not allowed to mutate publishing records.')`.
  - Typed transactions (60-117) use `/** @var T $x */ $x = DB::transaction(function () use (...): T {...});`.
  - `capture()` (29-38) builds the provisional slug as `Str::slug($slug ?: 'idea-'.Str::lower(Str::random(12))) ?: 'article'`.
- `database/factories/PublishingAttemptFactory.php`: `@extends Factory<Model>`, `definition(): array` with nested `Model::factory()` for foreign keys, and named states that return `static` via `$this->state(fn (array $attributes): array => [...])`.
- `database/migrations/2026_09_24_230100_remove_publishing_budget_storage.php`: anonymous migration class. Data work uses the query builder (`DB::table()->orderBy('id')->chunkById(200, ...)`), not Eloquent. It guards with `Schema::hasTable`/`hasColumn`, and a class docblock states what `down()` cannot restore.
- `tests/Feature/Publishing/PublishingBudgetRemovalTest.php:22-36,88-118`: tests a migration by `require database_path('migrations/...')`, calling `down()`, seeding legacy rows, then calling `up()`. It declares a **global** `interface RunnableMigration`. Do not redeclare that name in ContentIdeaTest, because Pest loads every file into one process. Use a prefixed name such as `ContentIdeaRunnableMigration`.
- `tests/Feature/Publishing/ArticleSlugTest.php`: `beforeEach` forgets the permission cache and runs `authorization:sync`. File-local helpers carry a prefix (`articleSlugAuthor`).
- `tests/Feature/Publishing/AdminPublishingWorkspaceTest.php:25-120`: global helpers `publishingUser()`, `publishingWriteOnlyUser()`, `activeArticleFor()`, `publishedArticleFor()` and `workspaceXpath*`. Don't redeclare them. ContentIdeaTest needs its own `contentIdea*` helpers, because those globals are not loaded when the file runs alone.
- Livewire action pattern in `⚡article-workspace.blade.php:110-125` (`saveDetails`): `Gate::authorize('update', $this->article)`, then `$this->validate([...])`, then call the action. Copy this for inline idea rename.

## Dependencies

- **`articles.idea` readers.** Every one must change; with `preventAccessingMissingAttributes`, any missed one throws in tests.
  - `app/Models/Article.php:15-16` (Sluggable source and fillable)
  - `app/Actions/Publishing/WriteArticle.php:35`
  - `app/Actions/Publishing/ImportWritingArchive.php:77`
  - `app/Actions/Publishing/ReadPublishedWriting.php:75`
  - `⚡dashboard.blade.php:51-52` (brief and interview input), `:91` (`titleFor`), `:207-211` and `:245` (preview and "View full idea")
  - `⚡article-workspace.blade.php:100` (develop input) and `:911` (`articleTitle`)
  - `partials/develop.blade.php:101` ("The original idea") and `:109` (voice-sample label fallback; **not in spec**)
  - `⚡published.blade.php:36` and `:119-120`
  - `components/admin/⚡index.blade.php:87,90` (aria-label) and `:110`
  - `admin/publishing/preview.blade.php:7,15`
  - `partials/release-checklist.blade.php:30`
  - `database/factories/ArticleFactory.php:27`
- **`$articleTitle` render site.** `resources/views/components/admin/publishing/partials/session.blade.php:5` (`<flux:heading level="1">`). The labeled idea line and the inline rename UI go here, not in the workspace component markup.
- **`WriteArticle::capture()` callers (100 in Feature, by file).** ReleaseIntegrityTest 23, AdminPublishingEditorTest 21, ReleaseReadinessTest 14, EditorialWorkflowTest 14, PublishingSessionTest 9, PublishingAuthorizationTest 4, ArticleSlugTest 4, DocumentRoundTripTest 2, PublishingJourneyTest 2, and 1 each in EditorialToolApprovalTest, PublishingAgentSettingsTest, EditorialReviewTest, AgentBudgetTest and EvidenceResearchTest.
  - Also `tests/Manual/PublishingModelTrialTest.php:140,149`. `phpstan.neon` analyses `tests/`, so PHPStan fails if these aren't updated.
  - App callers: `⚡dashboard.blade.php:32,50`.
  - Only ArticleSlugTest depends on the slug that `capture()` returns. Release tests pass slugs to `ManageArticleRelease::prepare()` directly, and AdminPublishingEditorTest uses `$article->slug` dynamically.
- **`Article::factory()` with `idea` overrides.**
  - `tests/Feature/AdminHomeTest.php:45,92`
  - `tests/Feature/Publishing/AdminPublishingWorkspaceTest.php:57,98,126,313,315,336,357`, plus `Article::query()->where('idea', ...)` at 212, 225, 243, 258 and 306
  - `ReleaseReadinessTest.php:473`
  - `PublicWritingTest.php:94,116`
  - `ArticleSlugTest.php:36`
- **Factories that nest `Article::factory()`.** Each will now also create a ContentIdea: `EditorialFindingFactory:18`, `EvidenceSourceFactory:19`, `EditorialActivityFactory:20`, `ArticleReleaseFactory:113`, `ArticleRevisionFactory:37`, `PublishingAttemptFactory:24`.
- **`ImportWritingArchive::write`.** Called by `app/Console/Commands/PublishingImportArchive.php`, `tests/Feature/MarketingSiteTest.php:447`, `tests/Feature/MarketingDiagramTest.php:216` and `ArchiveMigrationTest.php` (85, 101-126, production command test).
- **`ReadPublishedWriting`.** Consumed by `routes/web.php`, `resources/views/pages/writing/index.blade.php`, `resources/views/pages/writing/[slug].blade.php`, `PublicWritingTest`, `MarketingSiteTest`.
- **List queries that must eager-load `contentIdea`** (strict mode throws on N+1 in multi-model results):
  - `⚡dashboard.blade.php:63-82`, both paginators
  - `⚡published.blade.php:22-28`
  - `app/Actions/Admin/ReadAdminHome.php:37-60` (feeds `⚡index`; **not in spec**). Its eager loads use column-constrained selects such as `workingRevision:id,article_id,...`. If you constrain `contentIdea`, include every column you read, or `preventAccessingMissingAttributes` throws.
  - Workspace `voiceSampleOptions` (`⚡article-workspace.blade.php:~899-905`). The simplest fix is to drop the idea fallback at `develop.blade.php:109`; Phase 2 deletes that checklist anyway.
- **`ArticlePolicy`** (`app/Policies/ArticlePolicy.php`): view, update, develop, approve and publish all require the permission **and** `article.author_id === user.id`. `WriteArticle` checks only the permission, so the Livewire rename must call `Gate::authorize('update', $this->article)`.
- **Slug rules after `#[Sluggable]` is removed** (the package stays; `app/Models/Organization.php` still uses it). Collisions are enforced only at release: `CheckArticleRelease.php:86-90` (`slug.required`, `slug.unique`, `slug.immutable`) and `ManageArticleRelease.php:433-451` (`canonicalSlug` throws). `ManageArticleRelease.php:171-172` rewrites the provisional slug to the canonical one at prepare.
- **`phpunit.xml`.** Used by `php artisan test`, `vendor/bin/pest` and composer `test` (`config:clear`, `pint --test`, `phpstan`, `artisan test`), and by CI through `composer ci:check`.
- **`.github/workflows/tests.yml`.** "Setup Application" runs `composer setup`, which runs `php artisan migrate --force` against the new service DB. Job-level env overrides `.env`, which is copied from `.env.example` and has `DB_CONNECTION=sqlite` at line 23, because Dotenv is immutable.

## Conventions

- **Naming**:
  - Actions live in `App\Actions\Publishing\*`; enums in `App\Models\Publishing\*`; models in `App\Models\*`.
  - Migrations are `YYYY_MM_DD_HHMMSS_snake.php`.
  - Test helpers are prefixed with the file's subject.
  - Livewire SFCs are named like `⚡dashboard.blade.php` and resolve to components such as `admin.publishing.dashboard`, `admin.publishing.article-workspace`, `admin.publishing.published` and `admin.index`.
- **Imports**: fully-qualified `use` statements, no barrels. Permissions are aliased (`use App\Authorization\Publishing\Permission as PublishingPermission;`). Livewire SFCs import at the top of the PHP block.
- **Error handling**:
  - Actions throw `AuthorizationException` for permission failures and `RuntimeException` or `InvalidArgumentException` for domain errors.
  - Livewire calls `$this->validate()` and `Gate::authorize()`; the workspace catches `Throwable` into `$this->saveError`.
  - For rename, the spec wants a `ValidationException`; use `ValidationException::withMessages([...])`.
- **Types**:
  - Return types are explicit; generics go in PHPDoc (`HasMany<Article, $this>`), with array shapes in docblocks.
  - PHPStan runs at level 7 over `app/`, `config/`, `database/`, `routes/` and `tests/`, with no baseline and no `@phpstan-ignore`.
  - Run PHPStan as `env -u AI_AGENT -u CLAUDECODE vendor/bin/phpstan analyse --no-progress` to get full output.
- **Testing**:
  - Pest with `RefreshDatabase` applied to `tests/Feature` in `tests/Pest.php`.
  - Chained `expect()->and()` assertions.
  - Rendering is tested with `$this->actingAs($u)->get('http://admin.birdcar.test/...')` or `Livewire\Livewire::actingAs($u)->test('admin.publishing.dashboard')`.
  - `beforeEach` runs `authorization:sync`.
  - Run `vendor/bin/pint --dirty --format agent` after PHP edits.
- **Agent-input key**: brief and interview inputs use the key `'idea'` (`['idea' => ...]` in `⚡dashboard.blade.php:51-52` and `⚡article-workspace.blade.php:100`). Keep the key and change only its source to `contentIdea->body`. Likewise keep the Livewire `$idea` property and `flux:error name="idea"`. The spec's sweep (`grep -rn "'idea'"`) will flag these too; they are not column reads.

## Risks

- **CI "Load published writing" step is already broken, and the spec misreads it.** `tests.yml:58-72` calls `App\Actions\ReadWriting::all()`, but `app/Actions/ReadWriting.php` has only `render()`. Run 36761682308 logged `Call to undefined method App\Actions\ReadWriting::all()` and the step still finished `success`. The spec also assumes an "archive data migration" stays applied; none exists in `database/migrations`, and the archive only arrives through `publishing:import-archive`. Pointing the step at `ReadPublishedWriting::all()` would make it fail for real (`birdcar_test` has no published rows after the suite). Leave the step alone and report it to the owner rather than "fixing" it in Phase 1.
- **FK conflict in the spec's data model.** The spec has `content_ideas.author_id ... ON DELETE CASCADE` and `articles.content_idea_id ... ON DELETE RESTRICT`, while `articles.author_id` is nullable with `nullOnDelete` (`create_publishing_tables.php:17`). Deleting a User who has articles would then fail at the restrict FK; `User::booted()` has a deleting hook. No test covers this today. Decide on purpose: accept it, or make `content_ideas.author_id` nullable with `nullOnDelete` to match articles.
- **Backfill nulls.** `articles.idea` and `articles.author_id` are both nullable, but the new `body` and `author_id` are NOT NULL. Coalesce idea to `''` (which `deriveSummaryTitle` turns into "Untitled idea") and decide how to handle a null author. The dev DB has 11 articles, none with a null idea or author; 10 are archive imports with titles of 15-38 characters, and the longest idea is 259 characters. Identify archive articles by an `article_releases` row with `origin = 'import'` (the convention `ManageArticleRelease.php:421` uses).
- **Postgres jsonb reorders object keys, so Step A will probably expose a real bug.** `WriteArticle.php:306` checks `$incomingBlocks[$id] !== $block`, comparing a block read from jsonb with canonicalized incoming arrays. Strict `!==` is order-sensitive, so this likely raises a false "Agent edits cannot modify protected blocks." in `DocumentRoundTripTest.php:113`. Fix it in app code, for example by comparing `PublishingFingerprint::canonicalJson()` of both sides (`PublishingFingerprint` already ksorts, so hashes are safe). Also watch the `toBe` checks on jsonb-read arrays at `AdminPublishingEditorTest.php:150-151`, `PublishingSessionTest.php:91` and `DocumentRoundTripTest.php:24`.
- **Manual trial guard requires SQLite.** `tests/Manual/PublishingModelTrialTest.php:83-98` (`liveTrialRefuseUnlessIsolated`) refuses unless the connection is SQLite `:memory:`, so after Step A it always refuses. The check grep only covers `phpunit.xml` and `tests.yml`, so it won't catch this. Phase 9's `VoiceDriftTrialTest` copies the guard and expects Postgres. Consider changing it to require `pgsql` plus `birdcar_test` now, and update its `capture()` calls in any case (PHPStan).
- **`kind` default isn't on the in-memory model.** The DB default `'blog_post'` is not loaded back after `Article::create()`. Set `kind` explicitly in `captureIdea`, `addPiece`, `ImportWritingArchive` and `ArticleFactory`, or `$article->kind` is null on the just-created model.
- **ArticleFactory must give the idea the same author as the article.** Use a closure such as `'content_idea_id' => fn (array $a) => ContentIdea::factory()->state(['author_id' => $a['author_id']])`, ordered after `author_id`. Otherwise owner-scoped tests break: `->for($user, 'author')`, `['author_id' => ...]`, and the dashboard `where('author_id', ...)`.
- **Removing `#[Sluggable]` removes collision suffixing.** `ArticleSlugTest.php:13-22` (expects `shared-slug-1`) and `:24-31` (`'!!!'` becomes `article`) both depend on `capture()`'s slug parameter, so rewrite them around `captureIdea`, which takes no slug, and release canonicalization. `ImportWritingArchive` already refuses collisions itself (`conflicts()`).
- **Dashboard test expectations change.** `AdminPublishingWorkspaceTest.php:310-330` asserts `Str::limit($longIdea, 100)` (now an at-most-81-character summary) and `'Final accessible sentence.'`. Keep the "View full idea" disclosure reading `contentIdea->body`.
- **Herd environment is flaky.** Herd Postgres refused connections once during this scout and came back (PID 5150, listening on 5433). The `herd` CLI phar reports internal corruption ("actual filesize mismatch"), so confirm the database with `psql -h 127.0.0.1 -p 5433 -U root -l` or `lsof -nP -iTCP:5433 -sTCP:LISTEN`, not `herd services:*`. Locally it is PG 18.0; CI uses `pgvector/pgvector:pg17`.
- **No `--parallel`.** `php artisan test --parallel` creates `birdcar_test_N` databases, and the spec forbids creating databases.
- **Placeholder test.** `AgentBudgetTest.php:383` ("lock contention needs a non-sqlite transactional fixture") is a placeholder. Leave it as is; expanding it is out of scope.
- **Decision log checked against the code; no contradictions.** SQLite does throw on vector columns (`Illuminate/Database/Schema/Grammars/Grammar.php:377-380`, Laravel 13.33.0), so the Postgres-switch rationale holds. `Article` is still the piece model that revisions, releases and attempts hang off. `birdcar_test` exists, and dev already runs on pgsql (`birdcar_development`).

## Edge Cases (Phase 1)

- **`deriveSummaryTitle`**:
  - A one-line body returns the line.
  - A 2,000-character body returns at most 81 characters ending in "…" at a word boundary.
  - A single word longer than 80 characters has no boundary, so hard-cut at 80 and add "…".
  - `# Heading\nmore` returns "Heading".
  - Strip the leading markers `-`, `*`, `>` and `N.`. A line that is only a marker counts as empty, so use the next line.
  - Skip leading lines that are only whitespace; a whitespace-only body returns "Untitled idea".
  - Handle CRLF line endings.
  - For a non-Latin first line, use `mb_*` and `Str` functions so a cut never splits a codepoint.
  - Decide whether inline Markdown (`**bold**`, `[text](url)`) is stripped: the Technical Approach says "Markdown stripped", but the sketch strips only leading markers.
- **`captureIdea`**: creates one idea and one `blog_post` piece in a single transaction. `parent_article_id` is null, the slug matches `/^idea-[a-z0-9]{12}$/`, and the idea's author is the actor. Without `publishing.write` it throws `AuthorizationException`.
- **`addPiece`**: a parent from a different idea should be rejected; `kind` is set explicitly; the slug is provisional.
- **`ContentIdea::flagship()`**: the oldest `blog_post` with no parent, or null when there is none. Query it; don't lazy-load a relation on a collection.
- **`renameIdea`**: trims input. Empty or longer than 120 characters (`mb_strlen`) throws a `ValidationException` and writes nothing. Sets `summary_title_source = owner` and never touches revision metadata. Without the permission it throws `AuthorizationException`; the Livewire side checks ownership through `Gate::authorize('update', $article)`.
- **Migration**:
  - Backfill in chunks of 200.
  - Archive rows get `summary_title = idea` (all 10 dev titles are at most 41 characters).
  - Make `content_idea_id` NOT NULL only after the backfill, then add the FKs and the `(content_idea_id, kind)` index.
  - `down()` adds `idea` back, fills it from `body`, drops the FKs, index and columns on `articles`, and only then drops `content_ideas`.
  - Test it with the require, `down()`, raw `DB::table('articles')->insert`, `up()` pattern. Postgres DDL is transactional inside `RefreshDatabase`.
- **Archive import twice**: the second run reports `already_imported` and creates no second idea. The clobber or conflict path is unchanged.
- **Labels on every surface**: the dashboard (both lists), workspace header (session partial), published list, and Admin index (attention list, the aria-label at line 90, and the continue list) all show "Idea" plus the summary, never the summary as a bare heading. `ReadPublishedWriting` falls back to `'Untitled'`, the preview to "Untitled draft", and the release checklist to metadata only.

## Test Strategy

- **Step A**:
  1. Confirm the database: `psql -h 127.0.0.1 -p 5433 -U root -l | grep birdcar_test` (confirmed to exist).
  2. Edit `phpunit.xml` to set `DB_CONNECTION=pgsql` and `DB_DATABASE=birdcar_test`, keep `DB_URL=""`, and remove `:memory:`. Without `force`, PHPUnit `<env>` values win over `.env` but lose to process env. Locally, `.env` supplies host 127.0.0.1, port 5433, user root and the password.
  3. Run `php artisan test --compact`, then rerun each failing file alone.
  4. Check: `php artisan test --compact && ! grep -nE 'sqlite|:memory:' phpunit.xml .github/workflows/tests.yml`.
  5. Commit separately as `test(publishing): Run the suite on Postgres`.
- **Step B inner loop**: `php artisan test --compact tests/Feature/Publishing/ContentIdeaTest.php`.
- **Phase check**: `php artisan test --compact tests/Feature/Publishing/ContentIdeaTest.php tests/Feature/Publishing/ArticleSlugTest.php tests/Feature/Publishing/ArchiveMigrationTest.php`.
- **Sweep**: `rg -n "'idea'|->idea\b|->capture\(" app resources database tests` (remember the agent-input key `'idea'` stays).
- **Final**:
  - `vendor/bin/pint --dirty --format agent`
  - `env -u AI_AGENT -u CLAUDECODE vendor/bin/phpstan analyse --no-progress` (covers `tests/Manual`)
  - `composer test`
- **Manual**: capture an idea at `admin.birdcar.test/publishing` and confirm the workspace header shows it labeled as an idea.
