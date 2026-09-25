<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;

#[MaxTokens(8000)]
class VoiceReviewer extends EditorialAgent
{
    public function model(): string
    {
        return $this->modelOverride ?? 'google/gemini-3.8-flash';
    }

    protected function roleInstructions(): string
    {
        return 'Voice review: find voice, clarity, positioning, and editorial-fit issues while preserving truthful source boundaries.';
    }

    protected function roleReasoningEffort(): string
    {
        return 'medium';
    }
}
