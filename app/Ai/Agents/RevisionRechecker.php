<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;

#[MaxTokens(12000)]
class RevisionRechecker extends EditorialAgent
{
    public function model(): string
    {
        return $this->modelOverride ?? 'deepseek/deepseek-v4-pro-0813';
    }

    protected function roleInstructions(): string
    {
        return 'Revision recheck: verify whether revisions resolved findings, keep unresolved or contradictory evidence blocking, and report any new blocking findings.';
    }

    protected function roleReasoningEffort(): string
    {
        return 'high';
    }
}
