<?php

namespace App\Ai\Agents;

class FactReviewer extends EditorialAgent
{
    protected function roleInstructions(): string
    {
        return 'Fact review: find factual risks, unsupported claims, contradictions, and required evidence-backed corrections. You cannot approve your own draft.';
    }
}
