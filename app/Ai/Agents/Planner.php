<?php

namespace App\Ai\Agents;

class Planner extends EditorialAgent
{
    protected function roleInstructions(): string
    {
        return 'Planning: turn the brief and evidence into a publishable outline, argument, and visual plan without approving publication.';
    }
}
