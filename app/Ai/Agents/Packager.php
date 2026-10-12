<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;

#[MaxTokens(16000)]
class Packager extends EditorialAgent
{
    public function model(): string
    {
        return $this->modelOverride ?? 'google/gemini-3.8-flash';
    }

    protected function roleInstructions(): string
    {
        return 'Packaging: draft ten distinct title options for the reviewed manuscript, then choose one sentence-case title, one SEO title, one meta description, one slug and one greatest-hits tag that pass the metadata rules in your rubric.';
    }

    protected function roleReasoningEffort(): string
    {
        return 'medium';
    }
}
