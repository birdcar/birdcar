<?php

namespace App\Models\Publishing;

enum EditorialActivityKind: string
{
    case Interview = 'interview';
    case ResearchChallenge = 'research_challenge';
    case Plan = 'plan';
    case Draft = 'draft';
    case ReviewFacts = 'review_facts';
    case ReviewVoice = 'review_voice';
    case ReviewBuyer = 'review_buyer';
    case Reconciliation = 'reconciliation';
    case Recheck = 'recheck';

    public function isReviewLens(): bool
    {
        return in_array($this, [self::ReviewFacts, self::ReviewVoice, self::ReviewBuyer], true);
    }
}
