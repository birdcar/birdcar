<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;

#[MaxTokens(32000)]
class RevisionRechecker extends EditorialAgent
{
    public function model(): string
    {
        return $this->modelOverride ?? 'deepseek/deepseek-v4-pro-0813';
    }

    protected function roleInstructions(): string
    {
        return 'Revision recheck: compare input.reviewed_manuscript with the revised input.manuscript and classify every finding in input.review_findings exactly once, by finding_id and block_id, as resolved or unresolved; keep unresolved or contradictory evidence blocking, and report any new blocking findings.';
    }

    protected function roleReasoningEffort(): string
    {
        return 'high';
    }
}
