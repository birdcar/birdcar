<?php

namespace App\Console\Commands;

use App\Services\Publishing\VoiceCanon;
use Illuminate\Console\Command;

class PublishingCanonHash extends Command
{
    protected $signature = 'publishing:canon-hash';

    protected $description = 'Print the version hash of the voice and goals canon that new editorial activities freeze.';

    public function handle(VoiceCanon $canon): int
    {
        $this->line($canon->snapshot()->version);

        return self::SUCCESS;
    }
}
