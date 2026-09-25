<?php

use App\Actions\Publishing\AdvancePublishingAttempt;
use App\Actions\Publishing\WriteArticle;
use App\Authorization\Admin\Role as AdminRole;
use App\Authorization\Organizations\Role as OrganizationRole;
use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
});

test('publishing author grants direct domain action access', function (): void {
    $actor = publishingAuthorizedUser();
    $write = app(WriteArticle::class);

    $article = $write->capture($actor, 'Authorized capture.', 'authorized-capture');

    $this->assertDatabaseHas('articles', [
        'id' => $article->id,
        'author_id' => $actor->id,
        'slug' => 'authorized-capture',
    ]);
});

test('admin admission alone cannot read or mutate manuscripts', function (): void {
    $actor = User::factory()->create();
    $actor->assignRole(AdminRole::Access->value);

    expect(fn () => app(WriteArticle::class)->capture($actor, 'Admin only.', 'admin-only'))
        ->toThrow(AuthorizationException::class);
});

test('organization membership never bypasses global publishing capability checks', function (): void {
    $actor = User::factory()->create();
    $organization = Organization::factory()->create();
    $membership = OrganizationMembership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $actor->id,
    ]);
    $membership->assignRole(OrganizationRole::Editor->value);

    expect(fn () => app(WriteArticle::class)->capture($actor, 'Tenant only.', 'tenant-only'))
        ->toThrow(AuthorizationException::class);
});

test('revoked actors fail closed at the mutation boundary', function (): void {
    $actor = publishingAuthorizedUser();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $article = $write->capture($actor, 'Revoked.', 'revoked');
    $revision = $write->save($actor, $article, null, publishingAuthorizationDocument('Draft'), ['title' => 'Revoked'], 'draft-1');
    $actor->removeRole(PublishingRole::Author->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(fn () => $advance->develop($actor, $article, $revision->id))
        ->toThrow(AuthorizationException::class);
});

test('agent configuration comes only from the publishing author role', function (): void {
    $author = publishingAuthorizedUser();
    $adminOnly = User::factory()->create();
    $adminOnly->assignRole(AdminRole::Access->value);
    $writer = User::factory()->create();
    $writer->givePermissionTo(PublishingPermission::View->value, PublishingPermission::Write->value, PublishingPermission::Develop->value, PublishingPermission::Approve->value, PublishingPermission::Publish->value);
    $member = User::factory()->create();
    OrganizationMembership::factory()->create([
        'organization_id' => Organization::factory()->create()->id,
        'user_id' => $member->id,
    ])->assignRole(OrganizationRole::Editor->value);

    expect($author->can(PublishingPermission::ConfigureAgents->value))->toBeTrue()
        ->and($adminOnly->can(PublishingPermission::ConfigureAgents->value))->toBeFalse()
        ->and($writer->can(PublishingPermission::ConfigureAgents->value))->toBeFalse()
        ->and($member->can(PublishingPermission::ConfigureAgents->value))->toBeFalse();

    $author->removeRole(PublishingRole::Author->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($author->fresh()->can(PublishingPermission::ConfigureAgents->value))->toBeFalse();
});

function publishingAuthorizedUser(): User
{
    $user = User::factory()->create();
    $user->assignRole(PublishingRole::Author->value);

    return $user;
}

/**
 * @return array<string, mixed>
 */
function publishingAuthorizationDocument(string $text): array
{
    return [
        'version' => 1,
        'type' => 'doc',
        'content' => [
            ['type' => 'paragraph', 'text' => $text],
        ],
    ];
}
