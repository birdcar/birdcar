<?php

use App\Actions\Publishing\WriteArticle;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
});

test('captured articles normalize the requested slug and suffix collisions', function (): void {
    $actor = articleSlugAuthor();
    $write = app(WriteArticle::class);

    $first = $write->capture($actor, 'First idea.', 'Shared Slug');
    $second = $write->capture($actor, 'Second idea.', 'shared-slug');

    expect($first->slug)->toBe('shared-slug')
        ->and($second->slug)->toBe('shared-slug-1');
});

test('captured articles without a usable slug fall back to a generated one', function (?string $requested, string $pattern): void {
    $article = app(WriteArticle::class)->capture(articleSlugAuthor(), 'An idea.', $requested);

    expect($article->slug)->toMatch($pattern);
})->with([
    'no slug' => [null, '/^idea-[a-z0-9]{12}$/'],
    'punctuation only' => ['!!!', '/^article$/'],
]);

test('updating an article keeps its slug instead of regenerating it from the idea', function (): void {
    $article = app(WriteArticle::class)->capture(articleSlugAuthor(), 'Original idea.', 'stable-slug');

    $article->update(['idea' => 'A completely different idea.']);

    expect($article->fresh()?->slug)->toBe('stable-slug');
});

function articleSlugAuthor(): User
{
    $user = User::factory()->create();
    $user->assignRole(PublishingRole::Author->value);

    return $user;
}
