<?php

use App\Actions\Admin\InviteAdministrator;
use App\Mail\SurfaceMailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

arch('app code never sends mail outside surface notifications', function () {
    expect([
        'Illuminate\Support\Facades\Mail',
        'Illuminate\Contracts\Mail\Mailer',
        'Illuminate\Contracts\Mail\Factory',
        'Illuminate\Mail\Mailer',
        'Illuminate\Mail\PendingMail',
        'Illuminate\Notifications\Messages\MailMessage',
        'mail',
    ])->not->toBeUsed();
});

arch('only the invite action touches the mail manager', function () {
    expect('Illuminate\Mail\MailManager')->toOnlyBeUsedIn(InviteAdministrator::class);
});

arch('every mail class declares a surface', function () {
    expect('App\Mail')->classes()->toExtend(SurfaceMailable::class)->ignoring(SurfaceMailable::class);
});

arch('mailables are never queued directly', function () {
    expect('App\Mail')->not->toImplement(ShouldQueue::class);
});

arch('queued notifications wait for the database commit', function () {
    expect('App')->classes()->extending(Notification::class)->implementing(ShouldQueue::class)->toImplement(ShouldQueueAfterCommit::class);
});
