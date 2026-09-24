<?php

use App\Authorization\Admin\Role as AdminRole;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\Article;
use App\Models\ArticleRelease;
use App\Models\User;
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

test('publishing authors can reach dashboard and create passive ideas', function (): void {
    $user = publishingUser();

    $this->actingAs($user)
        ->get('http://admin.birdcar.test/publishing')
        ->assertOk()
        ->assertSee('Publishing workspace');

    Livewire\Livewire::actingAs($user)
        ->test('admin.publishing.dashboard')
        ->set('idea', 'A passive idea')
        ->call('saveForLater')
        ->assertHasNoErrors();

    expect(Article::query()->where('idea', 'A passive idea')->whereNull('current_attempt_id')->exists())->toBeTrue();
});

test('develop idea starts one active attempt', function (): void {
    $user = publishingUser();

    Livewire\Livewire::actingAs($user)
        ->test('admin.publishing.dashboard')
        ->set('idea', 'An active idea')
        ->call('developIdea')
        ->assertHasNoErrors();

    $article = Article::query()->where('idea', 'An active idea')->firstOrFail();
    expect($article->current_attempt_id)->not->toBeNull();
});

test('published work is listed separately from active writing', function (): void {
    $user = publishingUser();
    $published = Article::factory()->create(['author_id' => $user->id, 'idea' => 'Published piece']);
    $release = ArticleRelease::factory()->create(['article_id' => $published->id]);
    $published->forceFill(['published_release_id' => $release->id, 'first_published_at' => now()])->save();

    $this->actingAs($user)
        ->get('http://admin.birdcar.test/publishing')
        ->assertOk()
        ->assertDontSee('Published piece');

    $this->actingAs($user)
        ->get('http://admin.birdcar.test/publishing/published')
        ->assertOk()
        ->assertSee('Published piece');
});
