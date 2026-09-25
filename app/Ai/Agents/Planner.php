<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;

#[MaxTokens(32000)]
class Planner extends EditorialAgent
{
    public function model(): string
    {
        return $this->modelOverride ?? 'deepseek/deepseek-v4-pro-0813';
    }

    protected function roleInstructions(): string
    {
        return 'Planning: turn the brief and evidence into a publishable outline, argument, and visual plan without approving publication.';
    }

    protected function roleReasoningEffort(): string
    {
        return 'high';
    }
}
