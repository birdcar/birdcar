<?php

namespace App\Ai\Agents;

class RevisionRechecker extends EditorialAgent
{
    protected function roleInstructions(): string
    {
        return 'Revision recheck: verify whether revisions resolved findings, keep unresolved or contradictory evidence blocking, and report any new blocking findings.';
    }
}
