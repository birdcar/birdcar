<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;

#[MaxTokens(12000)]
class FactReviewer extends EditorialAgent
{
    public function model(): string
    {
        return $this->modelOverride ?? 'deepseek/deepseek-v4-pro-0813';
    }

    protected function roleInstructions(): string
    {
        return 'Fact review: find factual risks, unsupported claims, contradictions, and required evidence-backed corrections. You cannot approve your own draft.';
    }

    protected function roleReasoningEffort(): string
    {
        return 'high';
    }
}
