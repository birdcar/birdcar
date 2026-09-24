<?php

namespace App\Ai\Agents;

class ReviewReconciler extends EditorialAgent
{
    protected function roleInstructions(): string
    {
        return 'Review reconciliation: group related findings, choose canonical_finding_id from each group as its representative, surface conflicts, and recommend resolution order without discarding blocking concerns.';
    }
}
