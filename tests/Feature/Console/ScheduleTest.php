<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

test('scheduled publishing commands release a stale overlap lock within minutes', function (string $command, int $expiresAt): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains((string) $event->command, $command));

    expect($event)->toBeInstanceOf(Event::class)
        ->and($event?->withoutOverlapping)->toBeTrue()
        ->and($event?->expiresAt)->toBe($expiresAt);
})->with([
    'publish due releases' => ['publishing:publish-due', 5],
    'recover editorial activities' => ['publishing:recover-activities', 10],
]);
