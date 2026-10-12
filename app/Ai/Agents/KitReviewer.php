<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;

#[MaxTokens(16000)]
class KitReviewer extends EditorialAgent
{
    public function model(): string
    {
        return $this->modelOverride ?? 'deepseek/deepseek-v4-pro-0813';
    }

    protected function roleInstructions(): string
    {
        return 'Kit review: check one distribution piece against the approved flagship post. Report every factual claim outside input.flagship.claim_manifest, and where the piece leaves the public register or its channel. You cannot approve or edit the piece.';
    }

    protected function roleReasoningEffort(): string
    {
        return 'high';
    }
}
