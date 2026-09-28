<?php

use App\Mail\SurfaceMailable;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Mail\MailManager;
use Illuminate\Mail\Message;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Assert;
use Tests\Fixtures\Mail\ExampleAdminMail;
use Tests\Fixtures\Mail\ExampleMarketingMail;
use Tests\Fixtures\Mail\ExamplePlainMail;
use Tests\Fixtures\Mail\ExampleSurfaceNotification;
use Tests\Fixtures\Mail\ExampleUnscopedNotification;

beforeEach(function (): void {
    config([
        'mail.mailers.array_default' => ['transport' => 'array'],
        'mail.mailers.array_admin' => ['transport' => 'array'],
        'mail.mailers.array_marketing' => ['transport' => 'array'],
        'mail.default' => 'array_default',
        'admin.mail.mailer' => 'array_admin',
        'marketing.mail.mailer' => 'array_marketing',
    ]);
});

/**
 * @return array{array_default: int, array_admin: int, array_marketing: int}
 */
function guardedTransportCounts(): array
{
    $count = function (string $mailer): int {
        $transport = app(MailManager::class)->mailer($mailer)->getSymfonyTransport();

        if (! $transport instanceof ArrayTransport) {
            Assert::fail(sprintf('Expected mailer [%s] to use the array transport, got [%s].', $mailer, $transport::class));
        }

        return $transport->messages()->count();
    };

    return [
        'array_default' => $count('array_default'),
        'array_admin' => $count('array_admin'),
        'array_marketing' => $count('array_marketing'),
    ];
}

test('mail that is not a surface Mailable on its own mailer is blocked before any transport receives it', function (Closure $send, string $message): void {
    expect($send)->toThrow(LogicException::class, $message)
        ->and(guardedTransportCounts())->toBe(['array_default' => 0, 'array_admin' => 0, 'array_marketing' => 0]);
})->with([
    'a raw Mail facade send' => [
        fn () => Mail::raw('x', fn (Message $message) => $message->to('a@example.com')),
        'A raw message was blocked',
    ],
    'a notification returning a MailMessage' => [
        fn () => Notification::route('mail', 'a@example.com')->notify(new ExampleUnscopedNotification((new MailMessage)->line('x'))),
        'Notification ['.ExampleUnscopedNotification::class.'] was blocked',
    ],
    'an unmapped framework notification' => [
        fn () => User::factory()->create()->notify(new ResetPassword('t')),
        'Notification ['.ResetPassword::class.'] was blocked',
    ],
    'a surface Mailable sent through the Mail facade' => [
        fn () => Mail::to('a@example.com')->send(new ExampleAdminMail),
        '['.ExampleAdminMail::class.'] must be sent on mailer [array_admin], not [array_default].',
    ],
    'a surface Mailable sent on another surface mailer' => [
        fn () => Mail::mailer('array_marketing')->to('a@example.com')->send(new ExampleAdminMail),
        '['.ExampleAdminMail::class.'] must be sent on mailer [array_admin], not [array_marketing].',
    ],
    'a plain Mailable returned from a notification' => [
        fn () => Notification::route('mail', 'a@example.com')->notify(new ExampleUnscopedNotification((new ExamplePlainMail)->to('a@example.com'))),
        'Mailable ['.ExamplePlainMail::class.'] was blocked',
    ],
]);

function instantiateSurfaceMailable(string $mailableClass): SurfaceMailable
{
    if (! is_a($mailableClass, SurfaceMailable::class, true)) {
        Assert::fail("Expected [{$mailableClass}] to extend ".SurfaceMailable::class.'.');
    }

    return new $mailableClass;
}

test('surface mail returned from a notification is delivered on its own surface mailer only', function (string $mailableClass, array $expectedCounts): void {
    Notification::route('mail', 'owner@example.com')->notify(new ExampleSurfaceNotification(instantiateSurfaceMailable($mailableClass)));

    expect(guardedTransportCounts())->toBe($expectedCounts);
})->with([
    'admin' => [ExampleAdminMail::class, ['array_default' => 0, 'array_admin' => 1, 'array_marketing' => 0]],
    'marketing' => [ExampleMarketingMail::class, ['array_default' => 0, 'array_admin' => 0, 'array_marketing' => 1]],
]);

test('misrouted mail is blocked in production exactly as in testing', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    expect(app()->isProduction())->toBeTrue();
    expect(fn () => Mail::to('a@example.com')->send(new ExampleAdminMail))
        ->toThrow(LogicException::class, 'must be sent on mailer [array_admin], not [array_default].')
        ->and(guardedTransportCounts())->toBe(['array_default' => 0, 'array_admin' => 0, 'array_marketing' => 0]);
});
