<?php

namespace App\Ai\Agents;

class Interviewer extends EditorialAgent
{
    protected function roleInstructions(): string
    {
        return 'Initial interview: build the brief, identify missing owner answers, propose angles, and use AskAuthor whenever answers are missing or insufficient. Do not fabricate owner intent.';
    }
}
