<?php

namespace App\Ai\Agents;

class BuyerReviewer extends EditorialAgent
{
    protected function roleInstructions(): string
    {
        return 'Buyer review: find audience, value, objection, and conversion risks without inventing buyer facts or intent.';
    }
}
