<?php

use App\Authorization\Admin\Role as AdminRole;
use App\Authorization\Mail\Role as MailRole;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\User;
use App\Settings\Sections\MailSection;
use App\Settings\Sections\ProfileSection;
use App\Settings\SettingsSections;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
});

/**
 * @param  array<int, string>  $roles
 */
function settingsShellUser(array $roles): User
{
    $user = User::factory()->create();
    $user->assignRole([AdminRole::Access->value, ...$roles]);

    return $user->fresh() ?? Assert::fail('The user was not persisted.');
}

/**
 * @return list<string>
 */
function settingsShellTexts(string $html, string $expression): array
{
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $nodes = (new DOMXPath($document))->query($expression);

    if ($nodes === false) {
        Assert::fail("The XPath expression [{$expression}] is invalid.");
    }

    $texts = [];
    foreach ($nodes as $node) {
        if ($node instanceof DOMElement) {
            $texts[] = trim(preg_replace('/\s+/', ' ', $node->textContent) ?? '');
        }
    }

    return $texts;
}

/**
 * @param  TestResponse<Response>  $response
 */
function settingsShellHtml(TestResponse $response): string
{
    $content = $response->assertOk()->getContent();

    if ($content === false) {
        Assert::fail('Response has no content.');
    }

    return $content;
}

test('guests are sent to sign in before settings', function (): void {
    $this->get('http://admin.birdcar.test/settings')->assertRedirect('/login');
});

test('settings require admin access even for account sections', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('http://admin.birdcar.test/settings/account/profile')
        ->assertForbidden();
});

test('the rail groups application sections before account sections in registered order', function (): void {
    $html = settingsShellHtml($this->actingAs(settingsShellUser([MailRole::Operator->value, PublishingRole::Author->value]))->get('http://admin.birdcar.test/settings/account/security'));

    expect(settingsShellTexts($html, '//nav[@aria-label="Settings sections"]//h2'))->toBe(['Application', 'Your account'])
        ->and(settingsShellTexts($html, '//nav[@aria-label="Settings sections"]//a'))->toBe(['Mail', 'Publishing', 'Profile', 'Security', 'Preferences'])
        ->and(settingsShellTexts($html, '//nav[@aria-label="Settings sections"]//a[@aria-current="page"]'))->toBe(['Security']);
});

test('the rail omits sections and groups the user cannot configure', function (): void {
    $html = settingsShellHtml($this->actingAs(settingsShellUser([]))->get('http://admin.birdcar.test/settings/account/profile'));

    expect(settingsShellTexts($html, '//nav[@aria-label="Settings sections"]//h2'))->toBe(['Your account'])
        ->and(settingsShellTexts($html, '//nav[@aria-label="Settings sections"]//a'))->toBe(['Profile', 'Security', 'Preferences']);
});

test('the settings landing opens the first section the user can see', function (array $roles, string $section): void {
    $html = settingsShellHtml($this->actingAs(settingsShellUser($roles))->get('http://admin.birdcar.test/settings'));

    expect(settingsShellTexts($html, '//header[contains(@class, "settings-section-header")]/h1'))->toBe([$section])
        ->and(settingsShellTexts($html, '//nav[@aria-label="Settings sections"]//a[@aria-current="page"]'))->toBe([$section]);
})->with([
    'a mail operator lands on mail' => [[MailRole::Operator->value], 'Mail'],
    'a publishing author lands on publishing' => [[PublishingRole::Author->value], 'Publishing'],
    'an admin without module roles lands on profile' => [[], 'Profile'],
]);

test('the sidebar pins settings and the account menu opens your account', function (): void {
    $html = settingsShellHtml($this->actingAs(settingsShellUser([]))->get('http://admin.birdcar.test/'));

    expect(settingsShellTexts($html, '//nav[@aria-label="Admin settings"]//a'))->toBe(['Settings'])
        ->and(settingsShellTexts($html, '//nav[@aria-label="Admin modules"]//a'))->not->toContain('Mail')
        ->and($html)->toContain('href="http://admin.birdcar.test/settings/account/profile"');
});

test('former settings addresses redirect permanently to their sections', function (string $from, string $to): void {
    $this->actingAs(settingsShellUser([MailRole::Operator->value, PublishingRole::Author->value]))
        ->get('http://admin.birdcar.test'.$from)
        ->assertStatus(301)
        ->assertRedirect('http://admin.birdcar.test'.$to);
})->with([
    'mail' => ['/mail', '/settings/mail'],
    'publishing' => ['/publishing/settings', '/settings/publishing'],
]);

test('the registry rejects configured classes that are not settings sections', function (): void {
    config(['admin.settings.sections' => [MailSection::class, User::class]]);

    app(SettingsSections::class)->all();
})->throws(InvalidArgumentException::class, 'must list classes implementing');

test('the registry rejects two sections with the same path', function (): void {
    config(['admin.settings.sections' => [ProfileSection::class, ProfileSection::class]]);

    app(SettingsSections::class)->all();
})->throws(InvalidArgumentException::class, 'Settings section path [account/profile] is registered twice.');
