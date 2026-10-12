<?php

use App\Actions\Publishing\AcceptPackageProposal;
use App\Actions\Publishing\AdvancePublishingAttempt;
use App\Actions\Publishing\RunEditorialActivity;
use App\Actions\Publishing\StartEditorialActivity;
use App\Actions\Publishing\WriteArticle;
use App\Authorization\Admin\Role as AdminRole;
use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\Article;
use App\Models\ArticleRelease;
use App\Models\ArticleRevision;
use App\Models\ContentIdea;
use App\Models\EditorialActivity;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\Publishing\EditorialStage;
use App\Models\Publishing\PieceKind;
use App\Models\PublishingAttempt;
use App\Models\User;
use App\Services\Publishing\EditorialOutput;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
});

/**
 * Every Laravel migration file returns an anonymous class with these methods, but the abstract
 * base class does not declare them.
 */
interface ContentIdeaRunnableMigration
{
    public function up(): void;

    public function down(): void;
}

test('capturing an idea creates one idea and its flagship blog post piece', function (): void {
    $actor = contentIdeaAuthor();

    $article = app(WriteArticle::class)->captureIdea($actor, "Why small teams drop follow-ups\nMore detail later.");
    $idea = $article->contentIdea;

    expect(ContentIdea::query()->count())->toBe(1)
        ->and(Article::query()->count())->toBe(1)
        ->and($article->kind)->toBe(PieceKind::BlogPost)
        ->and($article->parent_article_id)->toBeNull()
        ->and($article->author_id)->toBe($actor->id)
        ->and($article->slug)->toMatch('/^idea-[a-z0-9]{12}$/')
        ->and($idea->author_id)->toBe($actor->id)
        ->and($idea->body)->toBe("Why small teams drop follow-ups\nMore detail later.")
        ->and($idea->summary_title)->toBe('Why small teams drop follow-ups')
        ->and($idea->summary_title_source)->toBe(ContentIdea::SOURCE_DERIVED)
        ->and($idea->flagship()?->id)->toBe($article->id);
});

test('capturing an idea requires the publishing write permission', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(PublishingPermission::View->value);

    expect(fn () => app(WriteArticle::class)->captureIdea($user, 'Not allowed.'))
        ->toThrow(AuthorizationException::class);

    expect(ContentIdea::query()->count())->toBe(0)
        ->and(Article::query()->count())->toBe(0);
});

test('summary titles are derived from the first line with text', function (string $body, string $expected): void {
    expect(ContentIdea::deriveSummaryTitle($body))->toBe($expected);
})->with([
    'one line' => ['A single thought', 'A single thought'],
    'markdown heading' => ["# Heading\nmore", 'Heading'],
    'whitespace only' => ["  \n\t\n   ", ContentIdea::UNTITLED],
    'empty' => ['', ContentIdea::UNTITLED],
    'leading blank lines' => ["\n   \n\tActual first line\nsecond", 'Actual first line'],
    'crlf' => ["\r\n## Windows heading\r\nbody", 'Windows heading'],
    'list and quote markers' => ["- \n> 1. **Bold** and [linked](https://example.com) words", 'Bold and linked words'],
    'snake case survives' => ['Rename the user_id column', 'Rename the user_id column'],
    'collapsed whitespace' => ["Too   many\tspaces", 'Too many spaces'],
    'non latin' => ['小さなチームがフォローアップを落とす理由', '小さなチームがフォローアップを落とす理由'],
]);

test('long summary titles are cut at a word boundary with an ellipsis', function (): void {
    $body = trim(str_repeat('Customers wait while follow ups slip through the cracks. ', 36));
    $summary = ContentIdea::deriveSummaryTitle($body);

    expect(mb_strlen($body))->toBeGreaterThan(2000)
        ->and(mb_strlen($summary))->toBeLessThanOrEqual(81)
        ->and($summary)->toEndWith('…')
        ->and($body)->toStartWith(mb_substr($summary, 0, -1).' ');
});

test('a word longer than the limit is hard cut', function (): void {
    $summary = ContentIdea::deriveSummaryTitle(str_repeat('a', 120));

    expect($summary)->toBe(str_repeat('a', 80).'…');
});

test('long non latin first lines are cut without splitting characters', function (): void {
    $summary = ContentIdea::deriveSummaryTitle(str_repeat('é', 100));

    expect(mb_check_encoding($summary, 'UTF-8'))->toBeTrue()
        ->and($summary)->toBe(str_repeat('é', 80).'…');
});

test('renaming an idea records the owner title without touching revision metadata', function (): void {
    $actor = contentIdeaAuthor();
    $write = app(WriteArticle::class);
    $article = $write->captureIdea($actor, 'A rough first line');
    $revision = $write->save($actor, $article, null, ['version' => 1, 'type' => 'doc', 'content' => []], ['title' => 'Working title'], 'rename-revision');

    $write->renameIdea($actor, $article->contentIdea, '  The real idea  ');

    expect($article->contentIdea->fresh()?->only(['summary_title', 'summary_title_source', 'body']))->toBe([
        'summary_title' => 'The real idea',
        'summary_title_source' => ContentIdea::SOURCE_OWNER,
        'body' => 'A rough first line',
    ])
        ->and($revision->fresh()?->metadata)->toBe(['title' => 'Working title'])
        ->and(ArticleRevision::query()->count())->toBe(1);
});

test('renaming an idea rejects empty and over long titles without writing', function (string $title): void {
    $actor = contentIdeaAuthor();
    $write = app(WriteArticle::class);
    $idea = $write->captureIdea($actor, 'Keep this summary')->contentIdea;

    expect(fn () => $write->renameIdea($actor, $idea, $title))->toThrow(ValidationException::class);

    expect($idea->fresh()?->only(['summary_title', 'summary_title_source']))->toBe([
        'summary_title' => 'Keep this summary',
        'summary_title_source' => ContentIdea::SOURCE_DERIVED,
    ]);
})->with([
    'empty' => [''],
    'whitespace' => ['   '],
    'too long' => [str_repeat('x', 121)],
]);

test('renaming an idea requires the publishing write permission', function (): void {
    $idea = ContentIdea::factory()->create();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PublishingPermission::View->value);

    expect(fn () => app(WriteArticle::class)->renameIdea($viewer, $idea, 'Renamed'))
        ->toThrow(AuthorizationException::class);
});

test('packaging sets the piece title and slug and never touches the idea summary', function (): void {
    $actor = contentIdeaAuthor();
    $write = app(WriteArticle::class);
    $article = $write->captureIdea($actor, 'Owners keep hiring for the same chores');
    $revision = $write->save($actor, $article, null, ['version' => 1, 'type' => 'doc', 'content' => []], ['title' => 'Working title', 'date' => '2024-01-01'], 'packaging-idea-revision');
    $attempt = app(AdvancePublishingAttempt::class)->develop($actor, $article, $revision->id);
    $package = contentIdeaCompletedPackage($actor, $attempt, $revision);
    $summaryBefore = $article->contentIdea->only(['summary_title', 'summary_title_source', 'body']);

    $accepted = app(AcceptPackageProposal::class)->accept($actor, $package, [
        'title' => 'Another admin hire will not fix it',
        'seo_title' => 'Do you need another admin hire? What to check first',
        'description' => 'What to check first.',
        'slug' => 'another-admin-hire-will-not-fix-it',
        'greatest_hits_tag' => 'the admin hire question',
    ], $revision->id);

    expect($accepted->metadata)->toMatchArray(['title' => 'Another admin hire will not fix it', 'slug' => 'another-admin-hire-will-not-fix-it', 'date' => '2024-01-01'])
        ->and($article->contentIdea->fresh()?->only(['summary_title', 'summary_title_source', 'body']))->toBe($summaryBefore)
        ->and($article->fresh()?->slug)->toBe($article->slug);
});

test('the interviewer names the idea only when the owner has not renamed it', function (string $source, string $expectedTitle, string $expectedSource): void {
    $actor = contentIdeaAuthor();
    $write = app(WriteArticle::class);
    $article = $write->captureIdea($actor, 'owners keep rebuilding the friday report by hand every week and nobody owns it');
    $article->contentIdea->forceFill(['summary_title' => 'A title from '.$source, 'summary_title_source' => $source])->save();
    $attempt = app(AdvancePublishingAttempt::class)->develop($actor, $article);

    contentIdeaRunInterview($actor, $attempt, '  Nobody owns the Friday report  ');

    expect($article->contentIdea->fresh()?->only(['summary_title', 'summary_title_source']))->toBe(['summary_title' => $expectedTitle, 'summary_title_source' => $expectedSource]);
})->with([
    'a derived title' => [ContentIdea::SOURCE_DERIVED, 'Nobody owns the Friday report', ContentIdea::SOURCE_INTERVIEWER],
    'an earlier interviewer title' => [ContentIdea::SOURCE_INTERVIEWER, 'Nobody owns the Friday report', ContentIdea::SOURCE_INTERVIEWER],
    'an owner rename' => [ContentIdea::SOURCE_OWNER, 'A title from owner', ContentIdea::SOURCE_OWNER],
]);

test('an interviewer idea title over the limit fails the interview and a blank one changes nothing', function (): void {
    $actor = contentIdeaAuthor();
    $article = app(WriteArticle::class)->captureIdea($actor, 'A rough first line');
    $attempt = app(AdvancePublishingAttempt::class)->develop($actor, $article);

    $failed = contentIdeaRunInterview($actor, $attempt, str_repeat('a', ContentIdea::DERIVED_TITLE_LIMIT + 1));

    expect($failed->status)->toBe(EditorialActivityStatus::Failed)
        ->and($failed->error_reason)->toBe('Interview idea title is 81 characters; it must be at most 80.');

    app(AdvancePublishingAttempt::class)->abandon($actor, $attempt, 'Try the interview again.');
    $next = app(AdvancePublishingAttempt::class)->develop($actor, $article->fresh() ?? $article);
    contentIdeaRunInterview($actor, $next, '   ');

    expect($article->contentIdea->fresh()?->only(['summary_title', 'summary_title_source']))->toBe(['summary_title' => 'A rough first line', 'summary_title_source' => ContentIdea::SOURCE_DERIVED]);
});

test('pieces belong to an idea with an optional parent piece', function (): void {
    $actor = contentIdeaAuthor();
    $write = app(WriteArticle::class);
    $flagship = $write->captureIdea($actor, 'One idea, several pieces');
    $idea = $flagship->contentIdea;

    $kit = $write->addPiece($actor, $idea, PieceKind::BlueskyThread, $flagship);
    $sibling = $write->addPiece($actor, $idea, PieceKind::BlogPost);

    expect($kit->kind)->toBe(PieceKind::BlueskyThread)
        ->and($kit->kind->isKit())->toBeTrue()
        ->and($kit->kind->label())->toBe('Bluesky thread')
        ->and($kit->parent?->id)->toBe($flagship->id)
        ->and($flagship->children()->pluck('id')->all())->toBe([$kit->id])
        ->and($sibling->parent_article_id)->toBeNull()
        ->and($idea->pieces()->orderBy('id')->pluck('id')->all())->toBe([$flagship->id, $kit->id, $sibling->id])
        ->and($idea->flagship()?->id)->toBe($flagship->id);
});

test('a piece cannot hang off a parent from another idea', function (): void {
    $actor = contentIdeaAuthor();
    $write = app(WriteArticle::class);
    $first = $write->captureIdea($actor, 'First idea');
    $second = $write->captureIdea($actor, 'Second idea');

    expect(fn () => $write->addPiece($actor, $second->contentIdea, PieceKind::LinkedinPost, $first))
        ->toThrow(InvalidArgumentException::class);
});

test('an idea without a top level blog post has no flagship', function (): void {
    $actor = contentIdeaAuthor();
    $idea = ContentIdea::factory()->create(['author_id' => $actor->id]);
    app(WriteArticle::class)->addPiece($actor, $idea, PieceKind::EmailBlurb);

    expect($idea->flagship())->toBeNull();
});

test('the board labels idea summaries on every card and never uses them as bare headings', function (): void {
    $user = contentIdeaAuthor();
    $saved = Article::factory()->idea('**Saved** idea summary')->create(['author_id' => $user->id]);
    $active = contentIdeaActiveArticle($user, '**Active** idea summary');
    $titled = contentIdeaActiveArticle($user, '**Titled** idea summary');
    ArticleRevision::factory()->create(['article_id' => $titled->id, 'created_by' => $user->id, 'metadata' => ['title' => 'A real piece title']]);
    $titled->forceFill(['working_revision_id' => ArticleRevision::query()->where('article_id', $titled->id)->value('id')])->save();

    $html = Livewire::actingAs($user)->test('admin.publishing.board')->html();

    foreach ([$saved, $active, $titled] as $article) {
        contentIdeaAssertLabeledOnly($html, $article->contentIdea->summary_title);
    }
    expect($html)->toContain('A real piece title');
});

test('the workspace header shows the idea as a labeled line instead of the title', function (): void {
    $user = contentIdeaAuthor();
    $article = app(WriteArticle::class)->captureIdea($user, 'Workspace idea summary');

    $html = Livewire::actingAs($user)->test('admin.publishing.article-workspace', ['article' => $article])->html();
    $xpath = contentIdeaXpath($html);

    expect(trim($xpath->evaluate('string(//h1)')))->toBe('Untitled blog post')
        ->and(contentIdeaXpathNodes($html, '//*[@data-idea-summary]//*[@data-idea-label][contains(., "Workspace idea summary")]')->length)->toBe(1);
    contentIdeaAssertLabeledOnly($html, 'Workspace idea summary');
});

test('authors rename the idea inline from the workspace', function (): void {
    $user = contentIdeaAuthor();
    $article = app(WriteArticle::class)->captureIdea($user, 'Before the rename');

    Livewire::actingAs($user)
        ->test('admin.publishing.article-workspace', ['article' => $article])
        ->assertSet('ideaTitle', 'Before the rename')
        ->set('ideaTitle', '')
        ->call('renameIdea')
        ->assertHasErrors(['ideaTitle' => 'required'])
        ->set('ideaTitle', 'After the rename')
        ->call('renameIdea')
        ->assertHasNoErrors()
        ->assertDispatched('idea-renamed')
        ->assertSee('After the rename');

    expect($article->contentIdea->fresh()?->summary_title_source)->toBe(ContentIdea::SOURCE_OWNER);
});

test('authors who can only view cannot rename an idea from the workspace', function (): void {
    $viewer = User::factory()->create();
    $viewer->assignRole(AdminRole::Access->value);
    $viewer->givePermissionTo(PublishingPermission::View->value);
    $article = Article::factory()->idea('Read only idea')->create(['author_id' => $viewer->id]);

    Livewire::actingAs($viewer)
        ->test('admin.publishing.article-workspace', ['article' => $article])
        ->assertDontSeeHtml('data-idea-rename')
        ->set('ideaTitle', 'Renamed anyway')
        ->call('renameIdea')
        ->assertForbidden();

    expect($article->contentIdea->fresh()?->summary_title)->toBe('Read only idea');
});

test('the published list and admin home label idea summaries', function (): void {
    $user = contentIdeaAuthor();
    $published = Article::factory()->idea('Published idea summary')->create(['author_id' => $user->id]);
    $revision = ArticleRevision::factory()->create(['article_id' => $published->id, 'created_by' => $user->id, 'metadata' => ['title' => 'Live published title', 'description' => 'Description', 'tags' => []]]);
    $release = ArticleRelease::factory()->create(['article_id' => $published->id, 'revision_id' => $revision->id, 'status' => 'published', 'published_at' => now()->subDay()]);
    $published->forceFill(['working_revision_id' => $revision->id, 'published_release_id' => $release->id, 'first_published_at' => now()->subDay()])->save();
    contentIdeaActiveArticle($user, 'Attention idea summary', ['paused_at' => now(), 'pause_reason' => 'Waiting.']);
    contentIdeaActiveArticle($user, 'Continuing idea summary', ['stage' => EditorialStage::InReview]);

    $publishedHtml = Livewire::actingAs($user)->test('admin.publishing.published')->html();
    $homeHtml = $this->actingAs($user)->get('http://admin.birdcar.test/')->assertOk()->getContent();

    expect($publishedHtml)->toContain('Live published title');
    contentIdeaAssertLabeledOnly($publishedHtml, 'Published idea summary');
    if (! is_string($homeHtml)) {
        throw new RuntimeException('The admin home did not render HTML.');
    }
    contentIdeaAssertLabeledOnly($homeHtml, 'Attention idea summary');
    contentIdeaAssertLabeledOnly($homeHtml, 'Continuing idea summary');
    expect(contentIdeaXpathNodes($homeHtml, '//*[@aria-label="Inspect workspace: Idea: Attention idea summary"]')->length)->toBe(1);
});

test('the migration moves each article idea into a content idea and keeps its slug', function (): void {
    $migration = contentIdeaMigration();
    $author = User::factory()->create();
    $migration->down();
    $now = now();
    $draftId = DB::table('articles')->insertGetId(['slug' => 'idea-legacydraft01', 'author_id' => $author->id, 'idea' => "# A legacy idea\nWith more lines.", 'created_at' => $now, 'updated_at' => $now]);
    $archiveId = DB::table('articles')->insertGetId(['slug' => 'legacy-essay', 'author_id' => $author->id, 'idea' => 'A Legacy Essay Title', 'created_at' => $now, 'updated_at' => $now]);
    $revisionId = DB::table('article_revisions')->insertGetId(['article_id' => $archiveId, 'number' => 1, 'origin' => 'import', 'document' => '{"version":1,"type":"doc","content":[]}', 'metadata' => '{"title":"A Legacy Essay Title"}', 'content_hash' => str_repeat('a', 64), 'created_at' => $now, 'updated_at' => $now]);
    DB::table('article_releases')->insert(['article_id' => $archiveId, 'revision_id' => $revisionId, 'origin' => 'import', 'payload' => '{}', 'release_hash' => str_repeat('b', 64), 'status' => 'published', 'published_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
    $orphanId = DB::table('articles')->insertGetId(['slug' => 'idea-legacyorphan1', 'author_id' => null, 'idea' => null, 'created_at' => $now, 'updated_at' => $now]);

    $migration->up();

    expect(Schema::hasColumn('articles', 'idea'))->toBeFalse()
        ->and(ContentIdea::query()->count())->toBe(3);

    $draft = Article::query()->with('contentIdea')->findOrFail($draftId);
    $archive = Article::query()->with('contentIdea')->findOrFail($archiveId);
    $orphan = Article::query()->with('contentIdea')->findOrFail($orphanId);

    expect($draft->slug)->toBe('idea-legacydraft01')
        ->and($draft->kind)->toBe(PieceKind::BlogPost)
        ->and($draft->contentIdea->only(['author_id', 'body', 'summary_title', 'summary_title_source']))->toBe([
            'author_id' => $author->id,
            'body' => "# A legacy idea\nWith more lines.",
            'summary_title' => 'A legacy idea',
            'summary_title_source' => ContentIdea::SOURCE_DERIVED,
        ])
        ->and($archive->slug)->toBe('legacy-essay')
        ->and($archive->contentIdea->summary_title)->toBe('A Legacy Essay Title')
        ->and($orphan->contentIdea->only(['author_id', 'body', 'summary_title']))->toBe([
            'author_id' => null,
            'body' => '',
            'summary_title' => ContentIdea::UNTITLED,
        ]);

    $migration->down();

    expect(Schema::hasTable('content_ideas'))->toBeFalse()
        ->and(DB::table('articles')->where('id', $draftId)->value('idea'))->toBe("# A legacy idea\nWith more lines.");

    $migration->up();
});

test('deleting an author keeps their pieces and ideas', function (): void {
    $actor = contentIdeaAuthor();
    $article = app(WriteArticle::class)->captureIdea($actor, 'Outlives its author');

    $actor->deleteOrFail();

    expect($article->fresh()?->author_id)->toBeNull()
        ->and($article->contentIdea->fresh()?->author_id)->toBeNull();
});

/** A completed package on the working revision, as packaging would leave it for the owner. */
function contentIdeaCompletedPackage(User $actor, PublishingAttempt $attempt, ArticleRevision $revision): EditorialActivity
{
    return EditorialActivity::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'initiating_user_id' => $actor->id,
        'kind' => EditorialActivityKind::Package,
        'status' => EditorialActivityStatus::Completed,
        'stage' => 'in_review',
        'input_version' => $attempt->input_version,
        'revision_id' => $revision->id,
        'revision_hash' => $revision->content_hash,
        'review_cycle' => $attempt->review_cycle,
        'batch_key' => 'package-'.$attempt->review_cycle.'-'.$revision->id,
        'idempotency_key' => 'package-'.Str::random(16),
        'prompt_version' => 1,
        'prompt_hash' => hash('sha256', Str::random(16)),
        'input' => [],
        'model_snapshot' => [],
        'completed_at' => now(),
    ]);
}

/** Runs an interview that names the idea, against a faked OpenRouter answer. */
function contentIdeaRunInterview(User $actor, PublishingAttempt $attempt, string $ideaTitle): EditorialActivity
{
    setPublishingAgentsPaused(true);
    $interview = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::Interview, [], 'interview-'.Str::random(6));
    setPublishingAgentsPaused(false);
    config()->set('ai.providers.openrouter.key', 'test-key');
    Queue::fake([RunEditorialActivity::class]);
    Http::swap(new HttpFactory(app(Dispatcher::class)));
    Http::preventStrayRequests();
    Http::fake(['https://openrouter.ai/api/v1/chat/completions' => Http::response([
        'id' => 'gen-content-idea',
        'choices' => [['finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => json_encode([
            'questions' => ['What happened last Friday?'],
            'brief' => ['summary' => 'A brief.', 'missingAnswers' => [], 'postType' => 'teardown', 'targetQuery' => null, 'ideaTitle' => $ideaTitle],
            'angleOptions' => [['title' => 'Walk the Friday report', 'thesis' => 'The report sticks in five places.']],
        ], JSON_THROW_ON_ERROR)]]],
    ])]);

    app(RunEditorialActivity::class, ['activityId' => $interview->id])->handle(app(EditorialOutput::class), app(WriteArticle::class));
    setPublishingAgentsPaused(true);

    return $interview->fresh() ?? throw new RuntimeException('The interview disappeared.');
}

/** @return Migration&ContentIdeaRunnableMigration */
function contentIdeaMigration(): Migration
{
    return require database_path('migrations/2026_10_02_100000_create_content_ideas_table.php');
}

function contentIdeaAuthor(): User
{
    $user = User::factory()->create();
    $user->assignRole(AdminRole::Access->value);
    $user->assignRole(PublishingRole::Author->value);

    return $user;
}

/** @param array<string, mixed> $attemptAttributes */
function contentIdeaActiveArticle(User $user, string $idea, array $attemptAttributes = []): Article
{
    $article = Article::factory()->idea($idea)->create(['author_id' => $user->id]);
    $attempt = PublishingAttempt::factory()->create(['article_id' => $article->id, 'user_id' => $user->id, 'stage' => EditorialStage::Developing, ...$attemptAttributes]);
    $article->forceFill(['current_attempt_id' => $attempt->id])->save();

    return $article;
}

function contentIdeaXpath(string $html): DOMXPath
{
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8"?>'.$html);

    return new DOMXPath($document);
}

/**
 * Asserts the summary renders inside an "Idea" label and that no heading or link shows it without one.
 */
function contentIdeaAssertLabeledOnly(string $html, string $summary): void
{
    $labeled = contentIdeaXpathNodes($html, sprintf('//*[@data-idea-label][contains(normalize-space(.), "%s")]', $summary));
    $bare = contentIdeaXpathNodes($html, sprintf('//*[self::h1 or self::h2 or self::h3 or self::h4 or @data-flux-heading or self::a][contains(normalize-space(.), "%s")][not(.//*[@data-idea-label])]', $summary));

    expect($labeled->length)->toBeGreaterThan(0, "The idea summary [{$summary}] is not labeled.")
        ->and($bare->length)->toBe(0, "The idea summary [{$summary}] renders as a bare heading.");

    foreach ($labeled as $label) {
        expect($label->nodeValue)->toContain('Idea');
    }
}

/**
 * @return DOMNodeList<DOMNameSpaceNode|DOMNode>
 */
function contentIdeaXpathNodes(string $html, string $expression): DOMNodeList
{
    $nodes = contentIdeaXpath($html)->query($expression);

    if ($nodes === false) {
        throw new RuntimeException("Invalid XPath expression: {$expression}");
    }

    return $nodes;
}
