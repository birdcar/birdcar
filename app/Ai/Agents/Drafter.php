<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;

#[MaxTokens(16000)]
class Drafter extends EditorialAgent
{
    public function model(): string
    {
        return $this->modelOverride ?? 'google/gemini-3.8-flash';
    }

    protected function roleInstructions(): string
    {
        return 'Drafting: produce a JSON-encoded Tiptap document and bounded metadata proposals while preserving protected structure and evidence boundaries.';
    }

    protected function roleReasoningEffort(): string
    {
        return 'medium';
    }
}
