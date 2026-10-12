<?php

use App\Actions\Publishing\AdvancePublishingAttempt;
use App\Actions\Publishing\ApprovePublishingStage;
use App\Actions\Publishing\IndexExemplarSections;
use App\Actions\Publishing\ManageArticleRelease;
use App\Actions\Publishing\StartEditorialActivity;
use App\Actions\Publishing\WriteArticle;
use App\Authorization\Admin\Role as AdminRole;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Jobs\IndexPublishedExemplar;
use App\Models\Article;
use App\Models\ArticleRelease;
use App\Models\ArticleRevision;
use App\Models\EditorialActivity;
use App\Models\ExemplarSection;
use App\Models\Publishing\ApprovalKind;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\Publishing\PieceKind;
use App\Models\PublishingAttempt;
use App\Models\User;
use App\Services\Publishing\PublishingFingerprint;
use App\Settings\PublishingAgentSettings;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/** Each topic word owns one axis, so a text's vector says which topics it mentions. */
const EXEMPLAR_RETRIEVAL_TOPICS = ['invoice' => 0, 'hiring' => 1, 'onboarding' => 2];

const EXEMPLAR_RETRIEVAL_ENDPOINT = 'https://openrouter.ai/api/v1/embeddings';

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
    // Set before anything resolves the provider: instances are cached, `.env` may set a base URL, and CI has no key.
    config()->set('ai.providers.openrouter-embeddings.key', 'test-key');
    config()->set('ai.providers.openrouter-embeddings.url', 'https://openrouter.ai/api/v1');
});

function exemplarRetrievalAuthor(): User
{
    $user = User::factory()->create();
    $user->assignRole([AdminRole::Access->value, PublishingRole::Author->value]);

    return $user;
}

/**
 * A deterministic, never-zero vector: one axis per topic word the text mentions, or a text-specific axis when it
 * mentions none.
 *
 * @return list<float>
 */
function exemplarRetrievalVector(string $text): array
{
    $vector = array_fill(0, 1536, 0.0);
    $matched = false;
    foreach (EXEMPLAR_RETRIEVAL_TOPICS as $word => $axis) {
        if (str_contains(mb_strtolower($text), $word)) {
            $vector[$axis] = 1.0;
            $matched = true;
        }
    }

    if (! $matched) {
        $vector[100 + crc32($text) % 1400] = 1.0;
    }

    return array_values($vector);
}

/**
 * Fakes the OpenRouter embeddings endpoint with one vector per input. `$status` decides each response's status code.
 *
 * @param  (Closure(Request): int)|null  $status
 */
function exemplarRetrievalFake(?Closure $status = null): void
{
    Http::preventStrayRequests();
    Http::fake([EXEMPLAR_RETRIEVAL_ENDPOINT => function (Request $request) use ($status) {
        $code = $status === null ? 200 : $status($request);
        if ($code !== 200) {
            return Http::response(['error' => ['message' => 'boom']], $code);
        }

        $inputs = is_array($request['input']) ? array_values($request['input']) : [$request['input']];

        return Http::response([
            'object' => 'list',
            'data' => array_map(fn (int $index, mixed $text): array => [
                'object' => 'embedding',
                'index' => $index,
                'embedding' => exemplarRetrievalVector(is_string($text) ? $text : ''),
            ], array_keys($inputs), $inputs),
            'model' => 'openai/text-embedding-3-small',
            'usage' => ['prompt_tokens' => 1, 'total_tokens' => 1],
        ]);
    }]);
}

/**
 * @param  list<array{0: string|null, 1: list<string>}>  $sections  heading (null for text before the first H2) and its paragraphs
 * @return array<string, mixed>
 */
function exemplarRetrievalDocument(array $sections): array
{
    $content = [];
    foreach ($sections as [$heading, $paragraphs]) {
        if ($heading !== null) {
            $content[] = ['type' => 'heading', 'attrs' => ['id' => 'blk_'.bin2hex(random_bytes(8)), 'level' => 2], 'content' => [['type' => 'text', 'text' => $heading]]];
        }
        foreach ($paragraphs as $paragraph) {
            $content[] = ['type' => 'paragraph', 'attrs' => ['id' => 'blk_'.bin2hex(random_bytes(8))], 'content' => [['type' => 'text', 'text' => $paragraph]]];
        }
    }

    return ['version' => 1, 'type' => 'doc', 'content' => $content];
}

function exemplarRetrievalArticle(string $slug, PieceKind $kind = PieceKind::BlogPost, ?int $ideaId = null, ?User $author = null): Article
{
    return Article::factory()->create(array_filter([
        'slug' => $slug,
        'kind' => $kind,
        'content_idea_id' => $ideaId,
        'author_id' => $author?->id,
    ], fn (mixed $value): bool => $value !== null));
}

/**
 * Publishes the document as a new imported release and points the article at it.
 *
 * @param  array<string, mixed>  $document
 */
function exemplarRetrievalPublish(Article $article, array $document): ArticleRelease
{
    $revision = exemplarRetrievalRevision($article, $document);
    $release = ArticleRelease::factory()->imported()->create(['article_id' => $article->id, 'revision_id' => $revision->id]);
    $article->forceFill(['working_revision_id' => $revision->id, 'published_release_id' => $release->id])->save();

    return $release;
}

/** @param array<string, mixed> $document */
function exemplarRetrievalRevision(Article $article, array $document): ArticleRevision
{
    $metadata = ['title' => 'Exemplar fixture', 'description' => 'Exemplar fixture.', 'tags' => []];

    return ArticleRevision::factory()->create([
        'article_id' => $article->id,
        'number' => (int) ArticleRevision::query()->where('article_id', $article->id)->max('number') + 1,
        'document' => $document,
        'metadata' => $metadata,
        'content_hash' => app(PublishingFingerprint::class)->hash(['document' => $document, 'metadata' => $metadata]),
    ]);
}

/** A published post with one already-embedded section; nothing calls the provider. */
function exemplarRetrievalSection(string $slug, string $heading, string $text, PieceKind $kind = PieceKind::BlogPost, ?int $ideaId = null): ExemplarSection
{
    $article = exemplarRetrievalArticle($slug, $kind, $ideaId);
    exemplarRetrievalPublish($article, exemplarRetrievalDocument([[$heading, [$text]]]));

    return ExemplarSection::factory()->embedded(exemplarRetrievalVector(IndexExemplarSections::embeddingInput($heading, $text)))->create([
        'article_id' => $article->id,
        'kind' => $kind,
        'heading' => $heading,
        'text' => $text,
        'content_hash' => hash('sha256', $heading."\n".$text),
    ]);
}

/** An attempt with an approved angle, completed research and a reviewed plan, built while agents are paused. */
function exemplarRetrievalAttempt(User $actor, PieceKind $kind = PieceKind::BlogPost): PublishingAttempt
{
    $write = app(WriteArticle::class);
    $approve = app(ApprovePublishingStage::class);
    $article = $write->captureIdea($actor, 'Owners chase every invoice by hand at month end');
    $article->forceFill(['kind' => $kind])->save();
    $revision = $write->save($actor, $article, null, exemplarRetrievalDocument([[null, ['The invoice run takes a day.']]]), ['title' => 'The invoice run'], 'exemplar-'.Str::random(8));
    $attempt = app(AdvancePublishingAttempt::class)->develop($actor, $article, $revision->id, ['summary' => 'Owners chase invoices by hand.']);
    $approve->approve($actor, $attempt, ApprovalKind::Angle, $approve->inputHashFor($attempt, ApprovalKind::Angle));
    $attempt = $attempt->fresh() ?? throw new RuntimeException('The attempt disappeared.');

    EditorialActivity::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'initiating_user_id' => $actor->id,
        'kind' => EditorialActivityKind::ResearchChallenge,
        'status' => EditorialActivityStatus::Completed,
        'stage' => 'drafting',
        'input_version' => $attempt->input_version,
        'review_cycle' => $attempt->review_cycle,
        'batch_key' => 'research-'.Str::random(8),
        'idempotency_key' => 'research-'.Str::random(16),
        'prompt_version' => 1,
        'prompt_hash' => hash('sha256', Str::random(16)),
        'input' => ['approval_hashes' => [ApprovalKind::Angle->value => $approve->inputHashFor($attempt, ApprovalKind::Angle)]],
        'model_snapshot' => [],
        'response' => ['claims' => [], 'sourceReferences' => [], 'contradictions' => [], 'gaps' => []],
        'completed_at' => now(),
    ]);

    return app(AdvancePublishingAttempt::class)->rethink($actor, $attempt, plan: [
        'outline' => ['Where the day goes', 'What to check first'],
        'visualPlan' => ['hero image'],
        'argument' => 'Chasing every invoice by hand costs a day a month.',
    ]);
}

function exemplarRetrievalApprovePlan(User $actor, PublishingAttempt $attempt): EditorialActivity
{
    $approve = app(ApprovePublishingStage::class);
    $approve->approve($actor, $attempt, ApprovalKind::Plan, $approve->inputHashFor($attempt, ApprovalKind::Plan));

    return EditorialActivity::query()->where('attempt_id', $attempt->id)->where('kind', EditorialActivityKind::Draft->value)->sole();
}

test('published blog posts index into one section per H2 while canon essays, kit pieces and drafts are skipped', function () {
    exemplarRetrievalFake();
    $index = app(IndexExemplarSections::class);
    $noHeadings = exemplarRetrievalArticle('one-long-thought');
    exemplarRetrievalPublish($noHeadings, exemplarRetrievalDocument([[null, ['A single thought about pricing.', 'It keeps going.']]]));
    $oneHeading = exemplarRetrievalArticle('one-heading');
    exemplarRetrievalPublish($oneHeading, exemplarRetrievalDocument([[null, ['An opening line.']], ['Why it breaks', ['First reason.', 'Second reason.']]]));
    $fiveHeadings = exemplarRetrievalArticle('five-headings');
    exemplarRetrievalPublish($fiveHeadings, exemplarRetrievalDocument(array_map(fn (int $n): array => ["Heading {$n}", ["Paragraph {$n}."]], range(1, 5))));
    $canon = exemplarRetrievalArticle('just-build-it-twice');
    exemplarRetrievalPublish($canon, exemplarRetrievalDocument([['Build it twice', ['A canon essay.']]]));
    ExemplarSection::factory()->create(['article_id' => $canon->id]);
    $kit = exemplarRetrievalArticle('kit-piece', PieceKind::LinkedinPost);
    exemplarRetrievalPublish($kit, exemplarRetrievalDocument([['A kit heading', ['A kit paragraph.']]]));
    $unpublished = exemplarRetrievalArticle('still-a-draft');

    expect($index->index($noHeadings))->toBe(['created' => 1, 'pruned' => 0, 'embedded' => 1])
        ->and($index->index($oneHeading))->toBe(['created' => 2, 'pruned' => 0, 'embedded' => 2])
        ->and($index->index($fiveHeadings))->toBe(['created' => 5, 'pruned' => 0, 'embedded' => 5])
        ->and($index->index($canon))->toBe(['created' => 0, 'pruned' => 1, 'embedded' => 0])
        ->and($index->index($kit))->toBe(['created' => 0, 'pruned' => 0, 'embedded' => 0])
        ->and($index->index($unpublished))->toBe(['created' => 0, 'pruned' => 0, 'embedded' => 0]);

    $lone = ExemplarSection::query()->where('article_id', $noHeadings->id)->sole();
    expect($lone->heading)->toBeNull()
        ->and($lone->text)->toBe("A single thought about pricing.\n\nIt keeps going.")
        ->and($lone->kind)->toBe(PieceKind::BlogPost)
        ->and($lone->release_id)->toBe($noHeadings->fresh()?->published_release_id)
        ->and($lone->embedding)->toHaveCount(1536)
        ->and($lone->embedded_at)->not->toBeNull()
        ->and(ExemplarSection::query()->where('article_id', $oneHeading->id)->orderBy('position')->pluck('heading')->all())->toBe([null, 'Why it breaks'])
        ->and(ExemplarSection::query()->whereIn('article_id', [$canon->id, $kit->id, $unpublished->id])->count())->toBe(0);

    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request): bool => $request->url() === EXEMPLAR_RETRIEVAL_ENDPOINT
        && $request['model'] === 'openai/text-embedding-3-small'
        && $request['dimensions'] === 1536
        && $request->hasHeader('Authorization', 'Bearer test-key'));
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'api.openai.com'));
});

test('long sections split at block boundaries, keep their heading and skip empty and repeated sections', function () {
    $paragraph = str_repeat('Owners lose a day. ', 78).'Owners lose a day.';
    $huge = str_repeat('word ', 1100).'end';
    $document = exemplarRetrievalDocument([
        ['Long section', [$paragraph, $paragraph.' again', $paragraph.' once more']],
        ['One huge block', [$huge]],
        ['Nothing under it', []],
        ['Legacy', []],
    ]);
    $document['content'][] = ['type' => 'codeBlock', 'attrs' => ['id' => 'blk_00000000000000aa'], 'content' => [['type' => 'text', 'text' => 'echo hidden']]];
    $document['content'][] = ['type' => 'paragraph', 'attrs' => ['id' => 'blk_00000000000000ab'], 'text' => 'Legacy copy.', 'content' => [['type' => 'text', 'text' => 'Legacy copy.']]];
    $document['content'] = [...$document['content'], ...exemplarRetrievalDocument([['Twice', ['The same text.']], ['Twice', ['The same text.']]])['content']];

    $sections = app(IndexExemplarSections::class)->sections($document);

    expect(mb_strlen($paragraph))->toBe(1500)
        ->and(array_column($sections, 'heading'))->toBe(['Long section', 'Long section', 'One huge block', 'Legacy', 'Twice'])
        ->and(array_column($sections, 'position'))->toBe([0, 1, 2, 3, 4])
        ->and($sections[0]['text'])->toBe($paragraph."\n\n".$paragraph.' again')
        ->and($sections[1]['text'])->toBe($paragraph.' once more')
        ->and($sections[2]['text'])->toBe($huge)
        ->and($sections[3]['text'])->toBe('Legacy copy.')
        ->and($sections[4]['content_hash'])->toBe(hash('sha256', "Twice\nThe same text."));
});

test('re-indexing embeds nothing for an unchanged post and replaces only an edited section', function () {
    exemplarRetrievalFake();
    $index = app(IndexExemplarSections::class);
    $article = exemplarRetrievalArticle('changing-post');
    exemplarRetrievalPublish($article, exemplarRetrievalDocument([['First', ['One.']], ['Second', ['Two.']], ['Third', ['Three.']]]));

    expect($index->index($article))->toBe(['created' => 3, 'pruned' => 0, 'embedded' => 3]);
    $kept = ExemplarSection::query()->where('article_id', $article->id)->whereIn('heading', ['First', 'Third'])->pluck('id')->all();

    expect($index->index($article))->toBe(['created' => 0, 'pruned' => 0, 'embedded' => 0]);
    Http::assertSentCount(1);

    $release = exemplarRetrievalPublish($article, exemplarRetrievalDocument([['First', ['One.']], ['Second', ['Two, edited.']], ['Third', ['Three.']]]));

    expect($index->index($article))->toBe(['created' => 1, 'pruned' => 1, 'embedded' => 1]);
    Http::assertSentCount(2);

    $rows = ExemplarSection::query()->where('article_id', $article->id)->orderBy('position')->get();
    expect($rows->pluck('text')->all())->toBe(['One.', 'Two, edited.', 'Three.'])
        ->and($rows->pluck('release_id')->unique()->all())->toBe([$release->id])
        ->and($rows->whereNull('embedding'))->toBeEmpty()
        ->and($rows->whereIn('id', $kept)->count())->toBe(2);
});

test('plan approval retrieves the closest sections of other published posts before its transaction and freezes them for the draft and voice review', function () {
    $actor = exemplarRetrievalAuthor();
    $attempt = exemplarRetrievalAttempt($actor);
    $ideaId = (int) Article::query()->whereKey($attempt->article_id)->value('content_idea_id');
    exemplarRetrievalSection('chasing-invoices', 'Invoices pile up', 'Every invoice waits for a reminder.');
    exemplarRetrievalSection('invoices-and-hiring', 'Before hiring', 'Hiring will not clear the invoice backlog.');
    exemplarRetrievalSection('onboarding-notes', 'First week', 'Onboarding takes a week.');
    exemplarRetrievalSection('same-idea-invoices', 'Same idea', 'An invoice note from this very idea.', ideaId: $ideaId);
    exemplarRetrievalSection('just-build-it-twice', 'Canon', 'A canon invoice story.');
    exemplarRetrievalSection('invoice-kit', 'Kit', 'An invoice kit post.', PieceKind::LinkedinPost);
    setPublishingAgentsPaused(false);
    Queue::fake();
    $levels = [];
    exemplarRetrievalFake(function () use (&$levels): int {
        $levels[] = DB::transactionLevel();

        return 200;
    });
    $baseline = DB::transactionLevel();

    $draft = exemplarRetrievalApprovePlan($actor, $attempt);

    $retrieved = $draft->input['canon']['retrieved_exemplars'] ?? null;
    $context = $attempt->fresh()?->exemplar_context;
    expect($levels)->toBe([$baseline])
        ->and($draft->input['canon']['retrieval_status'] ?? null)->toBe('ok')
        ->and(is_array($retrieved) ? array_column($retrieved, 'article_slug') : null)->toBe(['chasing-invoices', 'invoices-and-hiring'])
        ->and($retrieved[0]['similarity'] ?? null)->toEqualWithDelta(1.0, 0.0001)
        ->and($retrieved[1]['similarity'] ?? null)->toEqualWithDelta(1 / sqrt(2), 0.0001)
        ->and($retrieved[0]['text'] ?? null)->toBe('Every invoice waits for a reminder.')
        ->and($context['status'] ?? null)->toBe('ok')
        ->and($context['query_hash'] ?? null)->toBe(hash('sha256', "Owners chase invoices by hand.\nChasing every invoice by hand costs a day a month."));
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request['input'] === ["Owners chase invoices by hand.\nChasing every invoice by hand costs a day a month."]);

    // The review must judge the draft against the draft's own sections, even if the attempt's context changes later.
    $draft->forceFill(['status' => EditorialActivityStatus::Completed, 'completed_at' => now()])->save();
    $attempt = $attempt->fresh() ?? throw new RuntimeException('The attempt disappeared.');
    $attempt->forceFill(['exemplar_context' => [...$attempt->exemplar_context ?? [], 'sections' => []]])->save();
    $start = app(StartEditorialActivity::class);
    $review = $start->start($actor, $attempt, EditorialActivityKind::ReviewVoice, ['draft_activity_id' => $draft->id], 'review-with-draft');
    $unnamed = $start->start($actor, $attempt, EditorialActivityKind::ReviewVoice, [], 'review-without-draft');

    expect($review->input['canon']['retrieved_exemplars'] ?? null)->toEqual($retrieved)
        ->and($review->input['canon']['retrieval_status'] ?? null)->toBe('ok')
        ->and($unnamed->input['canon']['retrieved_exemplars'] ?? null)->toEqual($retrieved);
});

test('a plan approval proceeds without exemplars when the embeddings provider fails', function () {
    $actor = exemplarRetrievalAuthor();
    $attempt = exemplarRetrievalAttempt($actor);
    exemplarRetrievalSection('chasing-invoices', 'Invoices pile up', 'Every invoice waits for a reminder.');
    setPublishingAgentsPaused(false);
    Queue::fake();
    exemplarRetrievalFake(fn (): int => 500);

    $draft = exemplarRetrievalApprovePlan($actor, $attempt);

    expect($draft->status)->toBe(EditorialActivityStatus::Pending)
        ->and($draft->input['canon']['retrieval_status'] ?? null)->toBe('unavailable')
        ->and($draft->input['canon']['retrieved_exemplars'] ?? null)->toBe([])
        ->and($attempt->fresh()?->exemplar_context['reason'] ?? null)->toStartWith('RequestException:')
        ->and($attempt->fresh()?->exemplar_context['reason'] ?? null)->not->toContain('test-key');
    Http::assertSentCount(1);
});

test('a plan approval sends no embeddings request when nothing can be retrieved, for a kit piece or while paused', function (string $case) {
    $actor = exemplarRetrievalAuthor();
    $attempt = exemplarRetrievalAttempt($actor, $case === 'kit piece' ? PieceKind::LinkedinPost : PieceKind::BlogPost);
    if ($case !== 'empty index') {
        exemplarRetrievalSection('chasing-invoices', 'Invoices pile up', 'Every invoice waits for a reminder.');
    }
    setPublishingAgentsPaused($case === 'paused');
    Queue::fake();
    exemplarRetrievalFake();

    $approve = app(ApprovePublishingStage::class);
    $approve->approve($actor, $attempt, ApprovalKind::Plan, $approve->inputHashFor($attempt, ApprovalKind::Plan));

    Http::assertNothingSent();
    $context = $attempt->fresh()?->exemplar_context;
    $draft = EditorialActivity::query()->where('attempt_id', $attempt->id)->where('kind', EditorialActivityKind::Draft->value)->first();
    if ($case === 'empty index') {
        expect($context['status'] ?? null)->toBe('insufficient')
            ->and($draft?->input['canon']['retrieval_status'] ?? null)->toBe('insufficient')
            ->and($draft?->input['canon']['retrieved_exemplars'] ?? null)->toBe([]);
    } elseif ($case === 'kit piece') {
        expect($context)->toBeNull()
            ->and($draft?->input['canon'] ?? [])->not->toHaveKeys(['retrieved_exemplars', 'retrieval_status']);
    } else {
        expect($context)->toBeNull()->and($draft)->toBeNull();
    }
})->with(['empty index', 'kit piece', 'paused']);

test('publishing a blog post queues its re-index once, and publishing a kit piece queues nothing', function () {
    Queue::fake();
    $actor = exemplarRetrievalAuthor();
    $releases = app(ManageArticleRelease::class);
    $prepared = function (PieceKind $kind) use ($actor): array {
        $article = exemplarRetrievalArticle('published-'.$kind->value, $kind, author: $actor);
        $revision = exemplarRetrievalRevision($article, exemplarRetrievalDocument([['A heading', ['A paragraph.']]]));
        $article->forceFill(['working_revision_id' => $revision->id])->save();

        return [$article, ArticleRelease::factory()->create(['article_id' => $article->id, 'revision_id' => $revision->id, 'origin' => 'import', 'attempt_id' => null])];
    };
    [$post, $postRelease] = $prepared(PieceKind::BlogPost);
    [$kit, $kitRelease] = $prepared(PieceKind::LinkedinPost);

    $releases->deliver($actor, $postRelease, null);
    $releases->deliver($actor, $postRelease, null);
    $releases->deliver($actor, $kitRelease, null);

    expect($post->fresh()?->published_release_id)->toBe($postRelease->id)
        ->and($kit->fresh()?->published_release_id)->toBe($kitRelease->id);
    Queue::assertPushedTimes(IndexPublishedExemplar::class, 1);
    Queue::assertPushed(IndexPublishedExemplar::class, fn (IndexPublishedExemplar $job): bool => $job->articleId === $post->id);
});

test('the publish job syncs without embedding while agents are paused and never fails on a provider error', function () {
    Exceptions::fake();
    $provider = new ArrayObject(['failing' => false]);
    exemplarRetrievalFake(fn (): int => $provider['failing'] === true ? 500 : 200);
    $article = exemplarRetrievalArticle('job-post');
    exemplarRetrievalPublish($article, exemplarRetrievalDocument([['One', ['First.']], ['Two', ['Second.']]]));
    $run = fn () => (new IndexPublishedExemplar($article->id))->handle(app(IndexExemplarSections::class), app(PublishingAgentSettings::class));

    $run();
    expect(ExemplarSection::query()->where('article_id', $article->id)->whereNull('embedding')->count())->toBe(2);
    Http::assertNothingSent();

    setPublishingAgentsPaused(false);
    $provider['failing'] = true;
    $run();
    Exceptions::assertReported(RequestException::class);
    expect(ExemplarSection::query()->where('article_id', $article->id)->whereNull('embedding')->count())->toBe(2);

    $provider['failing'] = false;
    $run();
    expect(ExemplarSection::query()->where('article_id', $article->id)->whereNull('embedding')->count())->toBe(0);
});

test('the backfill command reports a provider failure, exits non-zero, and a rerun embeds only what is missing', function () {
    $provider = new ArrayObject(['failing' => true]);
    exemplarRetrievalFake(fn (): int => $provider['failing'] === true ? 500 : 200);
    $first = exemplarRetrievalArticle('first-post');
    exemplarRetrievalPublish($first, exemplarRetrievalDocument([['One', ['First.']], ['Two', ['Second.']]]));
    $second = exemplarRetrievalArticle('second-post');
    exemplarRetrievalPublish($second, exemplarRetrievalDocument([['Only', ['Third.']]]));

    $this->artisan('publishing:index-exemplars')
        ->expectsOutputToContain('first-post: 2 created, 0 pruned, failed:')
        ->assertFailed();
    expect(ExemplarSection::query()->whereNull('embedding')->count())->toBe(3);

    $provider['failing'] = false;
    $this->artisan('publishing:index-exemplars', ['--article' => 'second-post'])
        ->expectsOutput('second-post: 0 created, 0 pruned, 1 embedded')
        ->assertSuccessful();
    $this->artisan('publishing:index-exemplars')
        ->expectsOutput('first-post: 0 created, 0 pruned, 2 embedded')
        ->expectsOutput('second-post: 0 created, 0 pruned, 0 embedded')
        ->assertSuccessful();
    $this->artisan('publishing:index-exemplars', ['--article' => 'missing-post'])->assertFailed();

    expect(ExemplarSection::query()->whereNull('embedding')->count())->toBe(0)
        ->and(collect(app(Schedule::class)->events())->contains(fn (Event $event): bool => str_contains((string) $event->command, 'publishing:index-exemplars')))->toBeFalse();
});
