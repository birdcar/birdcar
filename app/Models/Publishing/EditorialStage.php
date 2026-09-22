<?php

namespace App\Models\Publishing;

enum EditorialStage: string
{
    case Developing = 'developing';
    case Drafting = 'drafting';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Abandoned = 'abandoned';
}
