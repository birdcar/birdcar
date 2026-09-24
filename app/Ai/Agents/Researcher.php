<?php

namespace App\Ai\Agents;

class Researcher extends EditorialAgent
{
    protected function roleInstructions(): string
    {
        return 'Research challenge: test claims against public evidence, keep Exa research explicit, record sources, quotations, contradictions, and unresolved gaps.';
    }
}
