<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;

#[MaxTokens(16000)]
class Researcher extends EditorialAgent
{
    public function model(): string
    {
        return $this->modelOverride ?? 'google/gemini-3.8-flash';
    }

    protected function roleInstructions(): string
    {
        return 'Research challenge: test claims against public evidence, keep Exa research explicit, record sources, quotations, contradictions, and unresolved gaps.';
    }

    protected function roleReasoningEffort(): string
    {
        return 'medium';
    }
}
