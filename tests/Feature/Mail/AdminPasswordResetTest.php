<?php

use App\Mail\Admin\AdminMailable;
use App\Models\User;
use App\Notifications\Admin\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Mail\MailManager;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;

test('forgot password requests on the admin host send the admin password reset instead of the stock one', function (): void {
    Notification::fake();
    $user = User::factory()->create(['email' => 'forgot@example.com']);

    $this->post('http://admin.birdcar.test/forgot-password', ['email' => 'forgot@example.com'])
        ->assertSessionHas('status');

    Notification::assertSentTo($user, PasswordReset::class);
    Notification::assertNotSentTo($user, ResetPassword::class);
});

test('password resets are queued to send after the surrounding transaction commits', function (): void {
    Queue::fake([SendQueuedNotifications::class]);
    $user = User::factory()->create();

    $user->sendPasswordResetNotification('reset-token');

    Queue::assertPushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job): bool => $job->notification instanceof PasswordReset
        && $job->notification->token === 'reset-token'
        && $job->afterCommit === true);
});

test('queued password resets keep the reset token out of the stored queue payload', function (): void {
    config(['queue.default' => 'database']);
    $user = User::factory()->create();

    $user->sendPasswordResetNotification('plaintext-reset-token');

    expect(DB::table('jobs')->sole()->payload)->not->toContain('plaintext-reset-token');
});

test('queued password resets link to the admin origin and send on the admin mailer from the admin sender', function (): void {
    config([
        'app.url' => 'https://birdcar.test',
        'admin.url' => 'https://admin.birdcar.test',
        'mail.mailers.array_admin' => ['transport' => 'array'],
        'admin.mail.mailer' => 'array_admin',
    ]);
    $user = User::factory()->create(['email' => 'queued@example.com']);

    $user->sendPasswordResetNotification('reset-token');

    $messages = app(MailManager::class)->mailer('array_admin')->getSymfonyTransport()->messages()
        ->map(fn (SentMessage $sent): Email => $sent->getOriginalMessage())
        ->values();
    expect($messages)->toHaveCount(1)
        ->and($messages[0]->getFrom()[0]->getAddress())->toBe('noreply@admin.birdcar.dev')
        ->and($messages[0]->getTo()[0]->getAddress())->toBe('queued@example.com')
        ->and($messages[0]->getSubject())->toBe('Reset your Birdcar Admin password')
        ->and($messages[0]->getTextBody())->toContain('https://admin.birdcar.test/reset-password/reset-token?email=queued%40example.com')
        ->and($messages[0]->getTextBody())->not->toContain('https://birdcar.test/reset-password');
});

test('password resets stay on the admin surface until customer auth exists', function (): void {
    $user = User::factory()->create();

    $mail = (new PasswordReset('reset-token'))->toMail($user);

    expect($mail)->toBeInstanceOf(
        AdminMailable::class,
        'Password resets are pinned to the admin surface. Customer auth needs the surface-aware reset listed under the contract\'s Future Considerations; decide it before changing this test.',
    );
});
