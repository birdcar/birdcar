<?php

namespace App\Console\Commands;

use App\Actions\Publishing\SyncVoiceCanonFromRepo;
use App\Services\Publishing\VoiceCanon;
use Illuminate\Console\Command;

class PublishingCanonSync extends Command
{
    protected $signature = 'publishing:canon-sync';

    protected $description = 'Record the repo voice canon as a canon version when its hash is new. Activates it only when the owner has made no Admin edits since the previous repo version. Run it in the deploy commands after migrations.';

    public function handle(SyncVoiceCanonFromRepo $sync, VoiceCanon $canon): int
    {
        $outcome = $sync->sync();
        $version = substr($canon->repoSnapshot()->version, 0, 12);

        $this->line(match ($outcome) {
            SyncVoiceCanonFromRepo::NOOP => "noop: a canon version already has the repo hash {$version}.",
            SyncVoiceCanonFromRepo::CREATED_ACTIVE => "created-active: repo canon {$version} is now the active version.",
            SyncVoiceCanonFromRepo::CREATED_INACTIVE => "created-inactive: repo canon {$version} was recorded but not activated, because Admin edits are active. Activate it in Settings → Voice canon to adopt it.",
        });

        return self::SUCCESS;
    }
}
