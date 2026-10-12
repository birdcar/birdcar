<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;

#[MaxTokens(32000)]
class Drafter extends EditorialAgent
{
    public function model(): string
    {
        return $this->modelOverride ?? 'google/gemini-3.8-flash';
    }

    protected function roleInstructions(): string
    {
        return 'Drafting: write the approved plan in Nick\'s public voice as a JSON-encoded Tiptap document that follows the chosen framework and post-type template, preserving protected structure and evidence boundaries. Titles and other metadata come from packaging later.';
    }

    protected function roleReasoningEffort(): string
    {
        return 'medium';
    }
}
