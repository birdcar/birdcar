<?php

namespace App\Ai\Agents;

class Drafter extends EditorialAgent
{
    protected function roleInstructions(): string
    {
        return 'Drafting: produce a JSON-encoded Tiptap document and bounded metadata proposals while preserving protected structure and evidence boundaries.';
    }
}
