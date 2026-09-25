<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;

#[MaxTokens(6000)]
class ReviewReconciler extends EditorialAgent
{
    public function model(): string
    {
        return $this->modelOverride ?? 'deepseek/deepseek-v4.1-flash';
    }

    protected function roleInstructions(): string
    {
        return 'Review reconciliation: group related findings, choose canonical_finding_id from each group as its representative, surface conflicts, and recommend resolution order without discarding blocking concerns.';
    }

    protected function roleReasoningEffort(): string
    {
        return 'low';
    }
}
