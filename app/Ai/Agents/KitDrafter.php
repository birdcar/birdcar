<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;

#[MaxTokens(16000)]
class KitDrafter extends EditorialAgent
{
    public function model(): string
    {
        return $this->modelOverride ?? 'google/gemini-3.8-flash';
    }

    protected function roleInstructions(): string
    {
        return 'Kit drafting: cut one distribution piece (the kind input.piece_kind names) from the approved flagship post in input.flagship, in Nick\'s public voice, within the channel rules. Never add a claim the flagship does not make.';
    }

    protected function roleReasoningEffort(): string
    {
        return 'medium';
    }
}
