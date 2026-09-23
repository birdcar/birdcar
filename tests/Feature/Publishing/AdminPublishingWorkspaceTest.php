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
        ->assertSee('Admin workspace')
        ->assertDontSee('Secret editorial title');
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
