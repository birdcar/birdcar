<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;

#[MaxTokens(16000)]
class LintRepairer extends EditorialAgent
{
    public function model(): string
    {
        return $this->modelOverride ?? 'google/gemini-3.8-flash';
    }

    protected function roleInstructions(): string
    {
        return 'Lint repair: clear only the mechanical voice-rule hits in input.hits by returning at most one bounded patch per block in input.blocks, changing the smallest span and keeping the meaning, facts and voice.';
    }

    protected function roleReasoningEffort(): string
    {
        return 'low';
    }
}
