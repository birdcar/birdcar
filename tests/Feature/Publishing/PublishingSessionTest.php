<?php

use App\Actions\Publishing\AdvancePublishingAttempt;
use App\Actions\Publishing\WriteArticle;
use App\Authorization\Admin\Role as AdminRole;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\ArticleRelease;
use App\Models\EditorialActivity;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialStage;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
    setPublishingAgentsPaused(true);
    Http::preventStrayRequests();
});

function sessionAuthor(): User
{
    $user = User::factory()->create();
    $user->assignRole([AdminRole::Access->value, PublishingRole::Author->value]);

    return $user;
}

function sessionDocument(string $text = 'The work stays yours.'): array
{
    return ['version' => 1, 'type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'attrs' => ['id' => 'blk_1234567890abcdef'], 'content' => [['type' => 'text', 'text' => $text]]],
    ]];
}

test('a saved idea can begin interviewing from its session without duplicate work', function (): void {
    $author = sessionAuthor();
    $article = app(WriteArticle::class)->capture($author, 'An idea worth developing');

    Livewire::actingAs($author)->test('admin.publishing.article-workspace', ['article' => $article])
        ->call('startDevelopment')
        ->call('startDevelopment')
        ->assertSet('saveError', null);

    expect($article->fresh()->current_attempt_id)->not->toBeNull();
    expect(EditorialActivity::query()->where('article_id', $article->id)->where('kind', EditorialActivityKind::Interview)->count())->toBe(1);
});

test('starting an interview rejects a stage that changed after the workspace opened', function (): void {
    $author = sessionAuthor();
    $article = app(WriteArticle::class)->capture($author, 'A changed stage');
    $attempt = app(AdvancePublishingAttempt::class)->develop($author, $article);
    $component = Livewire::actingAs($author)->test('admin.publishing.article-workspace', ['article' => $article->fresh()]);
    $attempt->forceFill(['stage' => EditorialStage::Drafting])->save();

    $component->call('startInterview')->assertSet('saveError', 'Interview work can only start while developing.');
    expect(EditorialActivity::query()->where('attempt_id', $attempt->id)->exists())->toBeFalse();
});

test('session navigation renders readable brief content without exposing its json structure', function (): void {
    $author = sessionAuthor();
    $article = app(WriteArticle::class)->capture($author, 'Less chasing');
    app(AdvancePublishingAttempt::class)->develop($author, $article, brief: ['reader' => 'An owner doing too much follow-up', 'argument' => 'Make the next action visible']);

    $this->actingAs($author)->get(route('admin.publishing.articles.show', $article))
        ->assertOk()
        ->assertSee('Write &amp; review', false)
        ->assertSee('An owner doing too much follow-up')
        ->assertSee('Make the next action visible')
        ->assertDontSee('&quot;reader&quot;:', false)
        ->assertDontSee('Nano USD')
        ->assertDontSee('Budget mutation key');
});

test('saving article details preserves the manuscript and synchronizes the editor revision', function (): void {
    $author = sessionAuthor();
    $article = app(WriteArticle::class)->capture($author, 'Original idea');
    $base = app(WriteArticle::class)->save($author, $article, null, sessionDocument(), ['title' => 'Old title', 'date' => '2024-01-01'], 'session-details');

    Livewire::actingAs($author)->test('admin.publishing.article-workspace', ['article' => $article->fresh()])
        ->set('details.title', 'A finished argument')
        ->set('details.description', 'What changes when the next step is clear.')
        ->call('saveDetails')
        ->assertSet('saveState', 'saved')
        ->assertDispatched('publishing-document-updated', fn ($event, $data): bool => $data['baseRevisionId'] === $base->id && $data['revisionId'] !== $base->id);

    $saved = $article->fresh()->workingRevision;
    expect($saved->document)->toBe($base->document);
    expect($saved->metadata)->toMatchArray(['title' => 'A finished argument', 'description' => 'What changes when the next step is clear.', 'date' => '2024-01-01']);
});

test('a stale details form cannot replace a newer manuscript', function (): void {
    $author = sessionAuthor();
    $writer = app(WriteArticle::class);
    $article = $writer->capture($author, 'Concurrent work');
    $base = $writer->save($author, $article, null, sessionDocument(), ['title' => 'Original'], 'details-base');
    $component = Livewire::actingAs($author)->test('admin.publishing.article-workspace', ['article' => $article->fresh()]);
    $newer = $writer->save($author, $article->fresh(), $base->id, sessionDocument('A newer manuscript'), ['title' => 'Newer'], 'details-newer');

    $component->set('details.title', 'Stale title')->call('saveDetails')
        ->assertSet('saveState', 'conflict')
        ->assertNotDispatched('publishing-document-updated');

    expect($article->fresh()->working_revision_id)->toBe($newer->id);
});

test('protected passage changes notify a clean browser of the exact replacement revision', function (): void {
    $author = sessionAuthor();
    $article = app(WriteArticle::class)->capture($author, 'A protected thought');
    $base = app(WriteArticle::class)->save($author, $article, null, sessionDocument(), [], 'protect-session');

    Livewire::actingAs($author)->test('admin.publishing.article-workspace', ['article' => $article->fresh()])
        ->call('protectFirstBlock')
        ->assertDispatched('publishing-document-updated', fn ($event, $data): bool => $data['baseRevisionId'] === $base->id && $data['document']['content'][0]['attrs']['protected'] === true);
});

test('release preview displays frozen content rather than rendering the latest working revision', function (): void {
    $author = sessionAuthor();
    $article = app(WriteArticle::class)->capture($author, 'A release preview');
    $base = app(WriteArticle::class)->save($author, $article, null, sessionDocument('Exactly what was approved.'), ['title' => 'Frozen title'], 'release-preview');
    $release = ArticleRelease::factory()->create([
        'article_id' => $article->id,
        'revision_id' => $base->id,
    ]);
    app(WriteArticle::class)->save($author, $article->fresh(), $base->id, sessionDocument('Working version'), ['title' => 'New title'], 'release-preview-working');

    $this->actingAs($author)->get(route('admin.publishing.articles.preview', ['article' => $article, 'release' => $release->id]))
        ->assertOk()->assertSee('Frozen title')->assertSee('Exactly what was approved.')
        ->assertDontSee('Working version')->assertHeader('Cache-Control', 'max-age=0, no-store, private');
});

test('refreshing an agent revision preserves unfinished article details', function (): void {
    $author = sessionAuthor();
    $writer = app(WriteArticle::class);
    $article = $writer->capture($author, 'Concurrent details');
    $base = $writer->save($author, $article, null, sessionDocument(), ['title' => 'Original', 'description' => 'Original summary'], 'refresh-base');
    $component = Livewire::actingAs($author)->test('admin.publishing.article-workspace', ['article' => $article->fresh()])
        ->set('details.title', 'Still writing this title');
    $newer = $writer->save($author, $article->fresh(), $base->id, sessionDocument('New manuscript'), ['title' => 'Agent title', 'description' => 'New summary'], 'refresh-newer');

    $component->call('refreshAgentWork')
        ->assertSet('details.title', 'Still writing this title')
        ->assertSet('details.description', 'New summary')
        ->assertSet('currentRevisionId', $newer->id)
        ->assertDispatched('publishing-document-updated');
});

test('a release preview cannot select another articles release', function (): void {
    $author = sessionAuthor();
    $article = app(WriteArticle::class)->capture($author, 'My article');
    $foreign = ArticleRelease::factory()->create();

    $this->actingAs($author)->get(route('admin.publishing.articles.preview', ['article' => $article, 'release' => $foreign->id]))
        ->assertNotFound();
});
