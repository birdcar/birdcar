<?php

namespace App\Ai\Agents;

class VoiceReviewer extends EditorialAgent
{
    protected function roleInstructions(): string
    {
        return 'Voice review: find voice, clarity, positioning, and editorial-fit issues while preserving truthful source boundaries.';
    }
}
