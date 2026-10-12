<?php

use App\Actions\Publishing\WriteArticle;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\Publishing\PieceKind;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
});

test('captured ideas get provisional slugs that never derive from the idea', function (): void {
    $actor = articleSlugAuthor();
    $write = app(WriteArticle::class);

    $first = $write->captureIdea($actor, 'Shared Slug');
    $second = $write->captureIdea($actor, 'Shared Slug');

    expect($first->slug)->toMatch('/^idea-[a-z0-9]{12}$/')
        ->and($second->slug)->toMatch('/^idea-[a-z0-9]{12}$/')
        ->and($first->slug)->not->toBe($second->slug)
        ->and($first->slug)->not->toContain('shared');
});

test('added pieces get their own provisional slug', function (): void {
    $actor = articleSlugAuthor();
    $write = app(WriteArticle::class);
    $flagship = $write->captureIdea($actor, 'An idea with a kit.');

    $piece = $write->addPiece($actor, $flagship->contentIdea, PieceKind::LinkedinPost, $flagship);

    expect($piece->slug)->toMatch('/^idea-[a-z0-9]{12}$/')
        ->and($piece->slug)->not->toBe($flagship->slug);
});

test('renaming the idea keeps the piece slug', function (): void {
    $actor = articleSlugAuthor();
    $write = app(WriteArticle::class);
    $article = $write->captureIdea($actor, 'Original idea.');
    $slug = $article->slug;

    $write->renameIdea($actor, $article->contentIdea, 'A completely different idea');

    expect($article->fresh()?->slug)->toBe($slug);
});

function articleSlugAuthor(): User
{
    $user = User::factory()->create();
    $user->assignRole(PublishingRole::Author->value);

    return $user;
}
