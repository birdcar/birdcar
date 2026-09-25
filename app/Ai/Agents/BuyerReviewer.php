<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;

#[MaxTokens(6000)]
class BuyerReviewer extends EditorialAgent
{
    public function model(): string
    {
        return $this->modelOverride ?? 'deepseek/deepseek-v4.1-flash';
    }

    protected function roleInstructions(): string
    {
        return 'Buyer review: find audience, value, objection, and conversion risks without inventing buyer facts or intent.';
    }

    protected function roleReasoningEffort(): string
    {
        return 'low';
    }
}
