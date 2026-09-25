<?php

use App\Authorization\Admin\Role as AdminRole;
use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\Article;
use App\Models\ArticleRelease;
use App\Models\ArticleRevision;
use App\Models\EditorialActivity;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\Publishing\EditorialStage;
use App\Models\PublishingAttempt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
});

function publishingUser(): User
{
    $user = User::factory()->create();
    $user->assignRole(AdminRole::Access->value);
    $user->assignRole(PublishingRole::Author->value);

    return $user;
}

function publishingWriteOnlyUser(): User
{
    $user = User::factory()->create();
    $user->assignRole(AdminRole::Access->value);
    $user->givePermissionTo(PublishingPermission::View->value, PublishingPermission::Write->value);

    return $user;
}

function articleRevisionWithTitle(Article $article, User $user, string $title): ArticleRevision
{
    $revision = ArticleRevision::factory()->create([
        'article_id' => $article->id,
        'created_by' => $user->id,
        'metadata' => ['title' => $title, 'description' => 'Description', 'tags' => []],
    ]);
    $article->forceFill(['working_revision_id' => $revision->id])->save();

    return $revision;
}

function activeArticleFor(User $user, string $idea, EditorialStage $stage = EditorialStage::Developing, ?string $title = null): Article
{
    $article = Article::factory()->create(['author_id' => $user->id, 'idea' => $idea]);
    if ($title !== null) {
        articleRevisionWithTitle($article, $user, $title);
    }
    $attempt = PublishingAttempt::factory()->create([
        'article_id' => $article->id,
        'user_id' => $user->id,
        'stage' => $stage,
    ]);
    $article->forceFill(['current_attempt_id' => $attempt->id])->save();

    return $article->fresh();
}

function publishedArticleFor(User $user, string $idea, string $title = 'Live title', ?EditorialStage $activeStage = null): Article
{
    $article = Article::factory()->create(['author_id' => $user->id, 'idea' => $idea, 'slug' => Str::slug($idea)]);
    $revision = articleRevisionWithTitle($article, $user, $title);
    $release = ArticleRelease::factory()->create([
        'article_id' => $article->id,
        'revision_id' => $revision->id,
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);
    $article->forceFill([
        'published_release_id' => $release->id,
        'first_published_at' => CarbonImmutable::parse('2020-05-04 00:00:00'),
    ])->save();

    if ($activeStage instanceof EditorialStage) {
        $attempt = PublishingAttempt::factory()->create([
            'article_id' => $article->id,
            'user_id' => $user->id,
            'stage' => $activeStage,
        ]);
        $article->forceFill(['current_attempt_id' => $attempt->id])->save();
    }

    return $article->fresh();
}

test('admission only users see shell but no editorial data', function (): void {
    $user = User::factory()->create();
    $user->assignRole(AdminRole::Access->value);
    Article::factory()->create(['idea' => 'Secret editorial title']);

    $this->actingAs($user)
        ->get('http://admin.birdcar.test/')
        ->assertOk()
        ->assertSee('Your workspace is ready')
        ->assertSee('aria-label="Admin modules"', false)
        ->assertDontSee('aria-label="Publishing navigation"', false)
        ->assertDontSee('href="http://admin.birdcar.test/publishing', false)
        ->assertDontSee('Secret editorial title');
});

test('admin navigation separates modules from publishing sections', function (): void {
    $user = publishingUser();

    $response = $this->actingAs($user)->get('http://admin.birdcar.test/publishing');

    $response->assertOk()
        ->assertSeeInOrder(['data-flux-sidebar', 'data-flux-header', 'data-flux-main', 'id="admin-main"'], false)
        ->assertSee('aria-label="Admin modules"', false)
        ->assertSee('aria-label="Publishing navigation"', false)
        ->assertSee('aria-label="Open Admin navigation"', false)
        ->assertSee('collapsible', false)
        ->assertSee('Toggle Admin navigation')
        ->assertDontSee('collapsible="mobile"', false)
        ->assertSee('data-admin-logout', false)
        ->assertSee('data-flux-composer', false)
        ->assertSee('wire:submit="developIdea"', false)
        ->assertSee('aria-label="Develop idea"', false)
        ->assertSee('aria-label="Save for later"', false)
        ->assertSee('data-flux-button', false)
        ->assertSee('window.Flux.applyAppearance', false)
        ->assertSee("window.localStorage.getItem('flux.appearance') || 'system'", false);

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//nav[@aria-label="Publishing navigation"]//a[@aria-current="page" and contains(@href, "/publishing")]')->length)->toBe(1);
    expect($xpath->query('//nav[@aria-label="Admin modules"]')->length)->toBe(1);
    expect($xpath->query('//main')->length)->toBe(1);
    expect($xpath->query('//a[@data-flux-sidebar-brand and @aria-label="Admin home"]')->item(0)->getAttribute('href'))->toBe(route('admin.index'));
    expect($xpath->query('//a[@data-flux-sidebar-brand]//img[@src="/favicon.svg"]')->length)->toBe(1);
    expect(trim($xpath->query('//a[@data-flux-sidebar-brand]')->item(0)->textContent))->toBe('Admin');
});

test('published navigation identifies the active section', function (): void {
    $response = $this->actingAs(publishingUser())->get('http://admin.birdcar.test/publishing/published');
    $response->assertOk();

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//nav[@aria-label="Publishing navigation"]//a[@aria-current="page" and contains(@href, "/publishing/published")]')->length)->toBe(1);
});

test('settings navigation appears only for users who can configure agents', function (): void {
    $response = $this->actingAs(publishingUser())->get('http://admin.birdcar.test/publishing');
    $response->assertOk();

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $settingsLinks = (new DOMXPath($document))->query('//nav[@aria-label="Publishing navigation"]//a[@href="http://admin.birdcar.test/publishing/settings"]');
    expect($settingsLinks->length)->toBe(1)
        ->and($settingsLinks->item(0)->hasAttribute('aria-current'))->toBeFalse();

    $this->actingAs(publishingWriteOnlyUser())
        ->get('http://admin.birdcar.test/publishing')
        ->assertOk()
        ->assertSee('aria-label="Publishing navigation"', false)
        ->assertDontSee('href="http://admin.birdcar.test/publishing/settings"', false);
});

test('admission only users cannot open direct publishing urls', function (): void {
    $user = User::factory()->create();
    $user->assignRole(AdminRole::Access->value);

    $this->actingAs($user)
        ->get('http://admin.birdcar.test/publishing')
        ->assertForbidden();
});

test('revoked publishing permission fails closed during livewire updates', function (): void {
    $user = publishingUser();
    $component = Livewire\Livewire::actingAs($user)
        ->test('admin.publishing.dashboard')
        ->set('idea', 'Should not save after revoke');

    $user->removeRole(PublishingRole::Author->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $component->call('saveForLater')->assertForbidden();
    expect(Article::query()->where('idea', 'Should not save after revoke')->exists())->toBeFalse();
});

test('revoked admin admission fails closed during livewire updates', function (): void {
    $user = publishingUser();
    $component = Livewire\Livewire::actingAs($user)
        ->test('admin.publishing.dashboard')
        ->set('idea', 'Should not save after admin revoke');

    $user->removeRole(AdminRole::Access->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $component->call('saveForLater')->assertForbidden();
    expect(Article::query()->where('idea', 'Should not save after admin revoke')->exists())->toBeFalse();
});

test('publishing authors can reach dashboard and create passive ideas without starting an interview', function (): void {
    $user = publishingUser();

    $this->actingAs($user)
        ->get('http://admin.birdcar.test/publishing')
        ->assertOk()
        ->assertSee('Publishing workspace');

    Livewire\Livewire::actingAs($user)
        ->test('admin.publishing.dashboard')
        ->set('idea', 'A passive idea')
        ->call('saveForLater')
        ->assertHasNoErrors()
        ->assertSee('Idea saved for later.');

    $article = Article::query()->where('idea', 'A passive idea')->firstOrFail();
    expect($article->current_attempt_id)->toBeNull()
        ->and(EditorialActivity::query()->where('article_id', $article->id)->exists())->toBeFalse();
});

test('develop idea starts one pending interview and redirects to the workspace', function (): void {
    $user = publishingUser();

    Livewire\Livewire::actingAs($user)
        ->test('admin.publishing.dashboard')
        ->set('idea', 'An active idea')
        ->call('developIdea')
        ->assertHasNoErrors()
        ->assertRedirect();

    $article = Article::query()->where('idea', 'An active idea')->firstOrFail();
    expect($article->current_attempt_id)->not->toBeNull();

    $activities = EditorialActivity::query()
        ->where('article_id', $article->id)
        ->where('attempt_id', $article->current_attempt_id)
        ->get();

    expect($activities)->toHaveCount(1)
        ->and($activities->first()->kind)->toBe(EditorialActivityKind::Interview)
        ->and($activities->first()->status)->toBe(EditorialActivityStatus::Pending)
        ->and($activities->first()->status)->not->toBe(EditorialActivityStatus::Running);
});

test('workspace shows queued agent work and its activity log without allowance controls', function (): void {
    $user = publishingUser();
    $article = activeArticleFor($user, 'Queued agent idea');
    EditorialActivity::factory()->create([
        'article_id' => $article->id,
        'attempt_id' => $article->current_attempt_id,
        'initiating_user_id' => $user->id,
        'kind' => EditorialActivityKind::Interview,
        'status' => EditorialActivityStatus::Pending,
    ]);

    Livewire\Livewire::actingAs($user)
        ->test('admin.publishing.article-workspace', ['article' => $article])
        ->assertSee('Agent work is queued; publishing agents are paused')
        ->assertSee('Activity log')
        ->assertDontSee('allowance')
        ->assertDontSee('Agent budget');

    setPublishingAgentsPaused(false);

    Livewire\Livewire::actingAs($user)
        ->test('admin.publishing.article-workspace', ['article' => $article->fresh()])
        ->assertSee('Ready for the next available worker');
});

test('develop idea is forbidden without develop permission', function (): void {
    $user = publishingWriteOnlyUser();

    Livewire\Livewire::actingAs($user)
        ->test('admin.publishing.dashboard')
        ->set('idea', 'No develop permission')
        ->call('developIdea')
        ->assertForbidden();

    expect(Article::query()->where('idea', 'No develop permission')->exists())->toBeFalse();
});

test('dashboard lists owner-scoped ideas and active writing with titles and full idea access', function (): void {
    $user = publishingUser();
    $other = publishingUser();
    $longIdea = str_repeat('Long idea sentence. ', 80).'Final accessible sentence.';
    Article::factory()->create(['author_id' => $user->id, 'idea' => $longIdea]);
    activeArticleFor($user, 'Active idea body', EditorialStage::InReview, 'Working active title');
    Article::factory()->create(['author_id' => $other->id, 'idea' => 'Other author idea']);
    activeArticleFor($other, 'Other active idea', EditorialStage::Developing, 'Other active title');

    $this->actingAs($user)
        ->get('http://admin.birdcar.test/publishing')
        ->assertOk()
        ->assertSee('Ideas')
        ->assertSee('Active writing')
        ->assertSee(Str::limit($longIdea, 100))
        ->assertSee('Final accessible sentence.')
        ->assertSee('Working active title')
        ->assertSee('In review')
        ->assertDontSee('Other author idea')
        ->assertDontSee('Other active title');
});

test('dashboard paginates ideas and active writing independently', function (): void {
    $user = publishingUser();
    foreach (range(1, 13) as $index) {
        Article::factory()->create([
            'author_id' => $user->id,
            'idea' => sprintf('Backlog idea %02d', $index),
            'created_at' => now()->subMinutes($index),
        ]);
        activeArticleFor($user, sprintf('Active writing %02d', $index));
    }

    $this->actingAs($user)
        ->get('http://admin.birdcar.test/publishing')
        ->assertOk()
        ->assertSee('Backlog idea 01')
        ->assertDontSee('Backlog idea 13')
        ->assertSee('Active writing 01')
        ->assertDontSee('Active writing 13')
        ->assertSee('paginator-ideasPage-page2', false)
        ->assertSee('paginator-activePage-page2', false);
});

test('published work is listed separately from active writing', function (): void {
    $user = publishingUser();
    $published = publishedArticleFor($user, 'Published piece', 'Published live title');

    $this->actingAs($user)
        ->get('http://admin.birdcar.test/publishing')
        ->assertOk()
        ->assertDontSee('Published piece')
        ->assertDontSee('Published live title');

    $response = $this->actingAs($user)
        ->get('http://admin.birdcar.test/publishing/published')
        ->assertOk()
        ->assertSee('Published live title')
        ->assertSee('Original date May 4, 2020')
        ->assertSee(route('admin.publishing.articles.show', $published), false);

    if (Route::has('public.article')) {
        $response->assertSee('Public link');
    } else {
        $response->assertDontSee('Public link');
    }
});

test('published library is owner-scoped and shows draft-in-progress status without hiding active revisions', function (): void {
    $user = publishingUser();
    $other = publishingUser();
    publishedArticleFor($user, 'Published active idea', 'Published active live title', EditorialStage::Drafting);
    publishedArticleFor($user, 'Terminal published idea', 'Terminal live title', EditorialStage::Published);
    publishedArticleFor($other, 'Other published idea', 'Other live title');

    $this->actingAs($user)
        ->get('http://admin.birdcar.test/publishing')
        ->assertOk()
        ->assertSee('Published active live title')
        ->assertSee('Draft in progress on published work')
        ->assertDontSee('Terminal live title')
        ->assertDontSee('Other live title');

    $this->actingAs($user)
        ->get('http://admin.birdcar.test/publishing/published')
        ->assertOk()
        ->assertSee('Published active live title')
        ->assertSee('Draft in progress')
        ->assertSee('Terminal live title')
        ->assertSee('Live')
        ->assertDontSee('Other live title');
});

test('published library paginates releases', function (): void {
    $user = publishingUser();
    foreach (range(1, 13) as $index) {
        $article = publishedArticleFor($user, sprintf('Published idea %02d', $index), sprintf('Live title %02d', $index));
        $article->forceFill(['first_published_at' => now()->subMinutes($index)])->save();
    }

    $this->actingAs($user)
        ->get('http://admin.birdcar.test/publishing/published')
        ->assertOk()
        ->assertSee('Live title 01')
        ->assertDontSee('Live title 13')
        ->assertSee('paginator-publishedPage-page2', false);
});
