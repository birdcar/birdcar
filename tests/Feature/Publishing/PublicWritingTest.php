<?php

use App\Actions\Publishing\ManageArticleRelease;
use App\Actions\Publishing\ReadPublishedWriting;
use App\Actions\Publishing\WriteArticle;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\Article;
use App\Models\ArticleRelease;
use App\Models\ArticleRevision;
use App\Models\User;
use App\Services\Publishing\ArticleDocument;
use App\Services\Publishing\PublishingFingerprint;
use Carbon\CarbonImmutable;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    config(['publishing.public_reader' => 'database', 'marketing.url' => 'https://birdcar.dev']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
});

test('database reader exposes only delivered release snapshots across writing pages feed and sitemap', function (): void {
    publicWritingArticle('published-snapshot', 'Published Snapshot', 'Visible public body.', '2024-02-01');
    publicWritingDraft('draft-only', 'Draft Only Secret');

    $this->get('/writing/')->assertOk()
        ->assertSee('Published Snapshot')
        ->assertDontSee('Draft Only Secret')
        ->assertDontSee('draft-only');

    $this->get('/writing/published-snapshot/')->assertOk()
        ->assertSee('Visible public body.')
        ->assertDontSee('Unpublished working secret');

    $this->get('/writing/draft-only/')->assertNotFound();
    $this->get('/rss.xml')->assertOk()
        ->assertSee('Published Snapshot')
        ->assertDontSee('Draft Only Secret');
    $this->get('/sitemap.xml')->assertOk()
        ->assertSee('https://birdcar.dev/writing/published-snapshot/')
        ->assertDontSee('draft-only');
});

test('database reader does not fall back to legacy files for missing records', function (): void {
    expect(app(ReadPublishedWriting::class)->find('just-build-it-twice'))->toBeNull();

    $this->get('/writing/just-build-it-twice/')->assertNotFound();
});

test('files mode still maps the legacy archive through the same projection', function (): void {
    config(['publishing.public_reader' => 'files']);

    $article = app(ReadPublishedWriting::class)->find('just-build-it-twice');

    expect($article)->not->toBeNull()
        ->and($article['title'])->toBe('Just build it twice')
        ->and($article['html'])->toContain('build it twice');
});

test('delivery is blocked while the public reader is still in files mode', function (): void {
    config(['publishing.public_reader' => 'files']);
    $actor = publicWritingAuthor();
    $article = Article::factory()->create(['author_id' => $actor->id, 'slug' => 'guarded-delivery']);
    $revision = ArticleRevision::factory()->create([
        'article_id' => $article->id,
        'created_by' => $actor->id,
        'document' => publicWritingDocument('Guarded body.'),
        'metadata' => publicWritingMetadata('Guarded Delivery', '2024-03-01'),
    ]);
    $article->forceFill(['working_revision_id' => $revision->id])->save();
    $release = publicWritingRelease($article, $revision, 'guarded-delivery', '2024-03-01');

    expect(fn () => app(ManageArticleRelease::class)->deliver($actor, $release, null))
        ->toThrow(RuntimeException::class, 'public_reader is database');
});

function publicWritingAuthor(): User
{
    $user = User::factory()->create();
    $user->assignRole(PublishingRole::Author->value);

    return $user;
}

function publicWritingArticle(string $slug, string $title, string $body, string $date): Article
{
    $actor = publicWritingAuthor();
    $article = Article::factory()->create(['author_id' => $actor->id, 'slug' => $slug, 'idea' => $title, 'first_published_at' => CarbonImmutable::parse($date)]);
    $revision = ArticleRevision::factory()->create([
        'article_id' => $article->id,
        'created_by' => $actor->id,
        'document' => publicWritingDocument($body),
        'metadata' => publicWritingMetadata($title, $date),
    ]);
    $release = publicWritingRelease($article, $revision, $slug, $date);
    $article->forceFill([
        'working_revision_id' => $revision->id,
        'published_release_id' => $release->id,
        'first_published_at' => CarbonImmutable::parse($date),
    ])->save();

    app(WriteArticle::class)->save($actor, $article->fresh(), $revision->id, publicWritingDocument('Unpublished working secret'), publicWritingMetadata('Unpublished working secret', $date), 'working-secret');

    return $article->fresh();
}

function publicWritingDraft(string $slug, string $title): Article
{
    $actor = publicWritingAuthor();
    $article = Article::factory()->create(['author_id' => $actor->id, 'slug' => $slug, 'idea' => $title]);
    $revision = ArticleRevision::factory()->create([
        'article_id' => $article->id,
        'created_by' => $actor->id,
        'document' => publicWritingDocument($title),
        'metadata' => publicWritingMetadata($title, '2024-03-01'),
    ]);
    $article->forceFill(['working_revision_id' => $revision->id])->save();

    return $article;
}

function publicWritingRelease(Article $article, ArticleRevision $revision, string $slug, string $date): ArticleRelease
{
    $fingerprint = app(PublishingFingerprint::class);
    $html = app(ArticleDocument::class)->renderHtml($revision->document);
    $payload = [
        'document' => $revision->document,
        'metadata' => $revision->metadata,
        'rendered_content_version' => 1,
        'rendered_document' => [
            'htmlVersion' => 1,
            'html' => $html,
            'hash' => $fingerprint->hash($html),
        ],
        'original_public_date' => CarbonImmutable::parse($date)->toISOString(),
        'canonical_slug' => $slug,
        'supporting_evidence_manifest' => [],
        'review_manifest' => [],
        'delivery_intent' => ['channel' => 'test'],
        'scheduled_at' => null,
    ];

    return ArticleRelease::create([
        'article_id' => $article->id,
        'attempt_id' => null,
        'revision_id' => $revision->id,
        'origin' => 'import',
        'payload' => $payload,
        'release_hash' => $fingerprint->hash($payload),
        'status' => 'published',
        'scheduled_at' => null,
        'published_by' => $article->author_id,
        'published_at' => CarbonImmutable::parse($date),
    ]);
}

/** @return array<string, mixed> */
function publicWritingMetadata(string $title, string $date): array
{
    return [
        'title' => $title,
        'description' => $title.' description.',
        'date' => $date,
        'tags' => [],
    ];
}

/** @return array<string, mixed> */
function publicWritingDocument(string $text): array
{
    return [
        'version' => 1,
        'type' => 'doc',
        'content' => [
            ['type' => 'paragraph', 'text' => $text],
        ],
    ];
}
