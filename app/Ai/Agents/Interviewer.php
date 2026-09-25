<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;

#[MaxTokens(8000)]
class Interviewer extends EditorialAgent
{
    public function model(): string
    {
        return $this->modelOverride ?? 'google/gemini-3.8-flash';
    }

    protected function roleInstructions(): string
    {
        return 'Initial interview: build the brief, identify missing owner answers, propose angles, and use AskAuthor whenever answers are missing or insufficient. Do not fabricate owner intent.';
    }

    protected function roleReasoningEffort(): string
    {
        return 'low';
    }
}
