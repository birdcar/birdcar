<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;

#[MaxTokens(16000)]
class VoiceReviewer extends EditorialAgent
{
    public function model(): string
    {
        return $this->modelOverride ?? 'google/gemini-3.8-flash';
    }

    protected function roleInstructions(): string
    {
        return 'Voice review: find where the draft stops sounding like Nick writing for owners, judged against your rubric; the linter owns the mechanical rules.';
    }

    protected function roleReasoningEffort(): string
    {
        return 'medium';
    }
}
