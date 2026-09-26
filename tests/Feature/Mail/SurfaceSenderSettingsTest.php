<?php

use App\Mail\SurfaceMailable;
use App\Settings\AdminMailSettings;
use App\Settings\MarketingMailSettings;
use App\Settings\SurfaceMailSettings;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Tests\Fixtures\Mail\ExampleAdminMail;
use Tests\Fixtures\Mail\ExampleMarketingMail;
use Tests\Fixtures\Mail\ExampleSurfaceNotification;

beforeEach(function (): void {
    config([
        'mail.mailers.array_admin' => ['transport' => 'array'],
        'mail.mailers.array_marketing' => ['transport' => 'array'],
        'admin.mail.mailer' => 'array_admin',
        'marketing.mail.mailer' => 'array_marketing',
    ]);
});

/**
 * @return list<Email>
 */
function surfaceMessagesOn(string $mailer): array
{
    return app(MailManager::class)->mailer($mailer)->getSymfonyTransport()->messages()
        ->map(fn (SentMessage $sent): Email => $sent->getOriginalMessage())
        ->values()
        ->all();
}

function sendSurfaceMail(SurfaceMailable $mailable): void
{
    Notification::route('mail', 'owner@example.com')->notify(new ExampleSurfaceNotification($mailable));
}

/**
 * @return array<string, array{class-string<SurfaceMailSettings>, string}>
 */
function surfaceSettingsGroups(): array
{
    return [
        'admin' => [AdminMailSettings::class, 'admin_mail'],
        'marketing' => [MarketingMailSettings::class, 'marketing_mail'],
    ];
}

test('clean installs seed a sender for each surface without a reply-to', function (): void {
    $admin = app(AdminMailSettings::class);
    $marketing = app(MarketingMailSettings::class);

    expect($admin->sender())->toEqual(new Address('noreply@admin.birdcar.dev', 'Birdcar'))
        ->and($admin->replyToAddress())->toBeNull();
    expect($marketing->sender())->toEqual(new Address('hello@birdcar.dev', 'Birdcar'))
        ->and($marketing->replyToAddress())->toBeNull();
});

test('sender addresses are accepted only on the exact domains of their surface', function (string $settingsClass, string $address, bool $accepted): void {
    expect($settingsClass::allowsSenderAddress($address))->toBe($accepted);
})->with([
    'admin on the admin domain' => [AdminMailSettings::class, 'noreply@admin.birdcar.dev', true],
    'admin on the apex domain' => [AdminMailSettings::class, 'ops@birdcar.dev', true],
    'marketing on the apex domain' => [MarketingMailSettings::class, 'hello@birdcar.dev', true],
    'marketing with an uppercase address' => [MarketingMailSettings::class, 'HELLO@BIRDCAR.DEV', true],
    'marketing on the admin subdomain' => [MarketingMailSettings::class, 'news@admin.birdcar.dev', false],
    'admin on a lookalike domain' => [AdminMailSettings::class, 'x@birdcar.dev.evil.com', false],
    'marketing on a lookalike domain' => [MarketingMailSettings::class, 'x@birdcar.dev.evil.com', false],
    'admin on an unrelated domain' => [AdminMailSettings::class, 'x@example.com', false],
    'marketing on an unrelated domain' => [MarketingMailSettings::class, 'x@example.com', false],
    'admin with a malformed address' => [AdminMailSettings::class, 'not-an-email', false],
    'marketing with a malformed address' => [MarketingMailSettings::class, 'not-an-email', false],
    'admin with an empty address' => [AdminMailSettings::class, '', false],
    'marketing with an empty address' => [MarketingMailSettings::class, '', false],
]);

test('invalid sender updates are rejected before anything is assigned', function (string $name, string $address, ?string $replyTo): void {
    $settings = app(MarketingMailSettings::class);

    expect(fn () => $settings->updateSender($name, $address, $replyTo))->toThrow(InvalidArgumentException::class)
        ->and($settings->from_name)->toBe('Birdcar')
        ->and($settings->from_address)->toBe('hello@birdcar.dev')
        ->and($settings->reply_to)->toBeNull();
})->with([
    'an off-domain address' => ['Birdcar', 'news@admin.birdcar.dev', null],
    'a blank name' => ['  ', 'hello@birdcar.dev', null],
    'a name with a line break' => ["Birdcar\r\nBcc: victim@example.com", 'hello@birdcar.dev', null],
    'a malformed reply-to' => ['Birdcar', 'hello@birdcar.dev', 'not-an-email'],
]);

test('an off-domain sender stored directly is rejected on load and nothing is sent', function (string $settingsClass, string $group): void {
    DB::table('settings')->where('group', $group)->where('name', 'from_address')->update(['payload' => json_encode('owner@example.com')]);
    app()->forgetScopedInstances();
    $mailable = $settingsClass === AdminMailSettings::class ? new ExampleAdminMail : new ExampleMarketingMail;

    expect(fn () => app($settingsClass)->sender())->toThrow(InvalidArgumentException::class)
        ->and(fn () => sendSurfaceMail($mailable))->toThrow(InvalidArgumentException::class)
        ->and(surfaceMessagesOn('array_admin'))->toBeEmpty()
        ->and(surfaceMessagesOn('array_marketing'))->toBeEmpty();
})->with(surfaceSettingsGroups());

test('admin mail is delivered on the admin mailer from the admin sender', function (): void {
    sendSurfaceMail(new ExampleAdminMail);

    $messages = surfaceMessagesOn('array_admin');
    expect($messages)->toHaveCount(1)
        ->and($messages[0]->getFrom()[0]->getAddress())->toBe('noreply@admin.birdcar.dev')
        ->and($messages[0]->getFrom()[0]->getName())->toBe('Birdcar')
        ->and($messages[0]->getTo()[0]->getAddress())->toBe('owner@example.com');
    expect(surfaceMessagesOn('array_marketing'))->toBeEmpty();
});

test('marketing mail is delivered on the marketing mailer from the marketing sender', function (): void {
    sendSurfaceMail(new ExampleMarketingMail);

    $messages = surfaceMessagesOn('array_marketing');
    expect($messages)->toHaveCount(1)
        ->and($messages[0]->getFrom()[0]->getAddress())->toBe('hello@birdcar.dev')
        ->and($messages[0]->getTo()[0]->getAddress())->toBe('owner@example.com');
    expect(surfaceMessagesOn('array_admin'))->toBeEmpty();
});

test('a sender changed in the same process is used by the next send', function (): void {
    sendSurfaceMail(new ExampleAdminMail);
    app(AdminMailSettings::class)->updateSender('Birdcar Ops', 'ops@birdcar.dev', null)->save();
    app()->forgetScopedInstances();

    sendSurfaceMail(new ExampleAdminMail);

    $messages = surfaceMessagesOn('array_admin');
    expect($messages)->toHaveCount(2)
        ->and($messages[0]->getFrom()[0]->getAddress())->toBe('noreply@admin.birdcar.dev')
        ->and($messages[1]->getFrom()[0]->getAddress())->toBe('ops@birdcar.dev')
        ->and($messages[1]->getFrom()[0]->getName())->toBe('Birdcar Ops');
});

test('a configured reply-to adds a Reply-To header and none is sent without one', function (): void {
    sendSurfaceMail(new ExampleAdminMail);
    app(AdminMailSettings::class)->updateSender('Birdcar', 'noreply@admin.birdcar.dev', 'ops@birdcar.dev')->save();
    app()->forgetScopedInstances();

    sendSurfaceMail(new ExampleAdminMail);

    $messages = surfaceMessagesOn('array_admin');
    expect($messages[0]->getReplyTo())->toBe([])
        ->and($messages[1]->getReplyTo())->toHaveCount(1)
        ->and($messages[1]->getReplyTo()[0]->getAddress())->toBe('ops@birdcar.dev');
});

test('an envelope from address cannot override the surface sender', function (): void {
    sendSurfaceMail(new ExampleAdminMail(new Address('spoof@example.com', 'Spoof')));

    $messages = surfaceMessagesOn('array_admin');
    expect($messages)->toHaveCount(1)
        ->and($messages[0]->getFrom())->toHaveCount(1)
        ->and($messages[0]->getFrom()[0]->getAddress())->toBe('noreply@admin.birdcar.dev');
});

test('a blank or undefined surface mailer fails the send', function (string $mailer): void {
    config(['admin.mail.mailer' => $mailer]);

    expect(fn () => sendSurfaceMail(new ExampleAdminMail))->toThrow(LogicException::class, '[admin.mail.mailer]')
        ->and(surfaceMessagesOn('array_admin'))->toBeEmpty()
        ->and(surfaceMessagesOn('array'))->toBeEmpty();
})->with([
    'blank' => [''],
    'undefined' => ['missing'],
]);
