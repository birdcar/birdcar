# Implementation Spec: Consistent Voice Publishing - Phase 5

**Contract**: ./contract.md
**Estimated Effort**: M

## Technical Approach

This phase adds retrieved exemplars on top of the fixed canon. Blog-post pieces get 2-4 sections of the owner's other published writing that are closest to the brief. The fixed canon essays and site copy are already frozen in full into every Drafter and VoiceReviewer input, so they are never indexed. The index holds the six non-canon archive essays and every blog post published through the pipeline from now on. Kit pieces do not use retrieval (no published kit pieces exist to retrieve, and short-form retrieval is Stretch).

Everything uses Laravel-native seams. A migration calls `Schema::ensureVectorExtensionExists()` and creates `exemplar_sections` with a `vector(1536)` column and an HNSW index. The model casts it with `AsVector`. Embeddings go through the SDK's `Embeddings` entry point on a dedicated `openrouter-embeddings` provider entry. That entry uses the `openai-compatible` driver on the existing `OPENROUTER_API_KEY` and `OPENROUTER_BASE_URL`, with `openai/text-embedding-3-small` at 1536 dimensions. It is also the app's `default_for_embeddings`, so nothing ever falls back to OpenAI directly. Similarity uses `whereVectorSimilarTo()` with a precomputed embedding. Raw strings are never passed, because a string would be embedded through whatever the default provider is.

Retrieval happens once per plan approval for blog posts. `ApprovePublishingStage::approve()` computes it before opening its transaction (it auto-starts the Draft inside the transaction, so a network call there would hold row locks). It stores the result on the attempt as `exemplar_context`. `StartEditorialActivity` freezes that context into Draft and ReviewVoice inputs as `canon.retrieved_exemplars`, so a retried draft and its reviewer see the same sections. A provider failure never blocks approval: the context records `status: unavailable` with the reason, and the Draft runs on the fixed canon alone.

Indexing is an owner-run backfill command (paid) plus a queued job dispatched after any pipeline blog post is published.

## Decisions Considered and Rejected

_Carried from the contract; consult before making gap decisions._

- **A fixed exemplar canon plus 2-4 sections retrieved per blog brief by the SDK's built-in embeddings from non-canon archive essays and pipeline-published pieces**: rejected a fixed canon only, per-post picking, the 2026-09-22 rejection of an embeddings index, and indexing material already frozen in full.
- **Embeddings use a dedicated openai-compatible provider entry on the OpenRouter key and base URL, set as the embeddings default; vector queries always receive precomputed embeddings**: rejected OpenAI direct, Gemini, and the shipped `default_for_embeddings` of `openai`.
- **The whole suite runs on Postgres; the vector migration calls `Schema::ensureVectorExtensionExists()` with no driver guard**: rejected a pgsql-guarded migration with only vector tests on Postgres, and a PHP similarity fallback.

## Feedback Strategy

**Inner-loop command**: `php artisan test --compact tests/Feature/Publishing/ExemplarRetrievalTest.php`

**Playground**: Pest on `birdcar_test` (Herd Postgres with pgvector 0.8.1), `Http::fake` for `https://openrouter.ai/api/v1/embeddings` returning deterministic vectors.

**Why this approach**: The vector query and the outbound request are both observable in tests against the real database, so no live call is needed while iterating.

## File Changes

### New Files

| File Path | Purpose |
| --- | --- |
| `database/migrations/2026_10_02_200000_create_exemplar_sections_table.php` | `ensureVectorExtensionExists()`, table, HNSW index, `publishing_attempts.exemplar_context` jsonb |
| `app/Models/ExemplarSection.php` | Model with `AsVector` cast, `article()` relation, `kind` cast to `PieceKind` |
| `app/Actions/Publishing/IndexExemplarSections.php` | Chunk an article's published release into sections, upsert by hash, prune vanished hashes, embed missing vectors in batches |
| `app/Services/Publishing/ExemplarRetriever.php` | Build the query text, embed it, run the similarity query, return 2-4 sections or a status |
| `app/Jobs/IndexPublishedExemplar.php` | Queued job for one article after it is published |
| `app/Console/Commands/PublishingIndexExemplars.php` | `php artisan publishing:index-exemplars {--article=}`: owner-run backfill; prints counts and never runs automatically |
| `tests/Feature/Publishing/ExemplarRetrievalTest.php` | Chunking, eligibility, outbound request shape, freezing, failure handling |
| `tests/Feature/Publishing/ExemplarVectorSearchTest.php` | pgvector column, HNSW index, nearest-section ordering with crafted vectors |

### Modified Files

| File Path | Changes |
| --- | --- |
| `config/ai.php` | Add `openrouter-embeddings` (`driver: openai-compatible`, `url: env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1')`, `key: env('OPENROUTER_API_KEY')`, `models.embeddings = ['default' => 'openai/text-embedding-3-small', 'dimensions' => 1536]`); set `default_for_embeddings` to `openrouter-embeddings` |
| `app/Actions/Publishing/ApprovePublishingStage.php` | For `ApprovalKind::Plan` on a `blog_post` piece, call `ExemplarRetriever::forAttempt()` before the transaction and store `exemplar_context` inside it |
| `app/Actions/Publishing/StartEditorialActivity.php` | Freeze `canon.retrieved_exemplars` (and `canon.retrieval_status`) from `attempt.exemplar_context` for Draft and ReviewVoice on blog posts; label them with the same voice-only label |
| `app/Actions/Publishing/ManageArticleRelease.php` | After a blog post release is published, dispatch `IndexPublishedExemplar::dispatch($article->id)->afterCommit()` |
| `app/Models/PublishingAttempt.php` | Cast `exemplar_context` to array |
| `.github/workflows/tests.yml` | No change needed if Phase 1 already uses `pgvector/pgvector:pg17`; confirm the extension can be created there |

## Implementation Details

### Pre-flight: pgvector availability

1. Confirm `vector` is available locally: `SELECT name, default_version FROM pg_available_extensions WHERE name = 'vector'` (Boost `database-query`). Herd reports 0.8.1.
2. Confirm Laravel Cloud Postgres supports `CREATE EXTENSION vector` (Cloud docs, or the `deploying-laravel-cloud` skill / `cloud` CLI against a non-production environment). **If Cloud does not support pgvector, stop the phase and report to the owner.** Do not ship a migration that fails on deploy.

### Sections and indexing

**Pattern to follow**: `app/Actions/Publishing/ImportWritingArchive.php` (iterating published releases), `CheckArticleRelease::documentText()` (text from Tiptap).

```php
final class IndexExemplarSections
{
    /** @return array{created: int, pruned: int, embedded: int} */
    public function index(Article $article): array;
}

final class ExemplarRetriever
{
    /**
     * @return array{status: 'ok'|'insufficient'|'unavailable', reason: string|null, query_hash: string, sections: list<array{section_id: int, article_slug: string, heading: string|null, text: string, similarity: float}>}
     */
    public function forAttempt(PublishingAttempt $attempt): array;
}
```

**Key decisions**:

- Eligible articles: `kind = blog_post`, `published_release_id` not null, slug not in the canon exemplar manifest's archive list. Site copy is never an Article and is never indexed.
- A section is an H2 and the blocks that follow it up to the next H2. Text before the first H2 is a section with a null heading. A section over 4,000 characters is split at paragraph boundaries.
- `content_hash = sha256(heading + "\n" + text)`. Re-indexing an unchanged article embeds nothing.
- Embedding batches of up to 64 inputs: `Embeddings::for($texts)->dimensions(1536)->generate(provider: 'openrouter-embeddings', model: 'openai/text-embedding-3-small')`.
- Query text: `brief.summary`, `brief.target_query`, `angle.thesis`, `plan.argument`, joined by newlines. Sections belonging to pieces of the same ContentIdea are excluded.
- Selection: `whereVectorSimilarTo('embedding', $vector, minSimilarity: 0.2)->limit(4)`. Two or more results is `ok`, fewer is `insufficient` with what was found, and a provider error is `unavailable`.

**Implementation steps**:

1. Pre-flight.
2. Config entry and default.
3. Migration and model.
4. `IndexExemplarSections`, job, command.
5. `ExemplarRetriever`, plan-approval wiring, freezing.

**Feedback loop**:

- **Playground**: `tests/Feature/Publishing/ExemplarRetrievalTest.php`
- **Experiment**: Index a fixture archive of three essays (one is a canon exemplar slug, so it is skipped) with 0, 1 and 5 H2 sections. Re-index after editing one section, so exactly one row is re-embedded and one pruned. Approve a plan with the embeddings fake returning HTTP 500, so the draft still starts with `retrieval_status = unavailable`. Approve a plan for a kit piece (none until Phase 6, so use a `linkedin_post` factory piece), and no embedding request is sent.
- **Check command**: `php artisan test --compact tests/Feature/Publishing/ExemplarRetrievalTest.php`

## Data Model

### Schema Changes

```sql
CREATE EXTENSION IF NOT EXISTS vector;

CREATE TABLE exemplar_sections (
  id BIGSERIAL PRIMARY KEY,
  article_id BIGINT NOT NULL REFERENCES articles(id) ON DELETE CASCADE,
  release_id BIGINT NULL REFERENCES article_releases(id) ON DELETE SET NULL,
  kind VARCHAR(30) NOT NULL,
  position INTEGER NOT NULL,
  heading VARCHAR(255) NULL,
  text TEXT NOT NULL,
  content_hash CHAR(64) NOT NULL,
  embedding vector(1536) NULL,
  embedded_at TIMESTAMP NULL,
  created_at TIMESTAMP, updated_at TIMESTAMP,
  UNIQUE (article_id, content_hash)
);
CREATE INDEX exemplar_sections_embedding_hnsw ON exemplar_sections USING hnsw (embedding vector_cosine_ops);

ALTER TABLE publishing_attempts ADD COLUMN exemplar_context JSONB NULL;
```

Use Laravel's `$table->vector('embedding', dimensions: 1536)->nullable()->index()` for the HNSW index rather than raw SQL.

## Testing Requirements

| Test File | Coverage |
| --- | --- |
| `tests/Feature/Publishing/ExemplarRetrievalTest.php` | Eligibility (canon slugs skipped, kit kinds skipped), chunking, hash idempotency and pruning; `Http::assertSent` to `https://openrouter.ai/api/v1/embeddings` with `model = openai/text-embedding-3-small` and `dimensions = 1536` and the OpenRouter bearer key; Draft and ReviewVoice inputs carry the same `retrieved_exemplars`; failure path; the publish hook dispatches the job |
| `tests/Feature/Publishing/ExemplarVectorSearchTest.php` | The column type is `vector`; the HNSW index exists (`pg_indexes`); with three crafted unit vectors the nearest section is returned first; the minimum-similarity cut excludes an orthogonal vector |
| `tests/Feature/Publishing/EditorialInputContractTest.php` | Extended: blog-post Draft and ReviewVoice rows assert `canon.retrieved_exemplars` is present (possibly empty with a status) |

Run the vector test with `--fail-on-skipped` (contract criterion). Nothing in it may skip.

## Error Handling

| Error Scenario | Handling Strategy |
| --- | --- |
| Embedding provider error during plan approval | Approval proceeds; `exemplar_context.status = unavailable` with the reason; Draft uses the fixed canon |
| Embedding provider error during indexing | Rows stay with null `embedding`; the command reports the failure and exits non-zero; rerun later embeds only missing vectors |
| `vector` extension missing in an environment | Migration fails loudly; the pre-flight step exists to catch this before deploy |
| Dimension mismatch (model change) | Insert fails; the config pins dimensions; changing the model requires a re-embed migration (documented in the command help) |

## Failure Modes

| Component | Failure Mode | Trigger | Impact | Mitigation |
| --- | --- | --- | --- | --- |
| Retrieval | Topic, not voice, drives similarity | Embeddings capture subject matter | Exemplars on the same topic but not the best voice | Accepted; the fixed canon carries voice, retrieval adds topical craft; drift trial measures the outcome |
| Retrieval | Practitioner archive sections leak anecdotes | Six older essays target practitioners | Off-audience content imitated | Voice-only label; fact-reviewer flags archive anecdotes |
| Config | Silent OpenAI fallback | Raw string passed to `whereVectorSimilarTo` | Second spend authority, missing key errors | Always pass the precomputed vector; test asserts no request to `api.openai.com` |
| Indexing | Stale index after an edit-and-republish | Job not dispatched | Old text retrieved | Publish hook plus idempotent re-index by hash |
| Locks | Network call inside a transaction | Retrieval moved into the approval closure | Long row locks | Retrieval computed before the transaction; test asserts it runs when the transaction level is 0 |

## Validation Commands

```bash
php artisan test --compact tests/Feature/Publishing/ExemplarRetrievalTest.php
php artisan test --compact --fail-on-skipped tests/Feature/Publishing/ExemplarVectorSearchTest.php
php artisan test --compact tests/Feature/Publishing
vendor/bin/pint --dirty --format agent
env -u AI_AGENT -u CLAUDECODE vendor/bin/phpstan analyse --no-progress
composer test
```

## Rollout Considerations

- **Owner tasks**: after deploy, run `php artisan publishing:index-exemplars` locally and in production (paid, small: about 30 sections). This is listed in the acceptance gate.
- **Monitoring**: OpenRouter usage shows embedding calls under the same key.
- **Rollback plan**: drop the table and column via `down()`; retrieval code reads a null context as "none".
- **Rules in force**: `.ai/rules/ai.md`, `.ai/rules/app.md`, `.ai/rules/tests.md`, Octane (no static caches of vectors).

## Open Items

- [ ] Laravel Cloud pgvector support is unverified until the pre-flight runs.
