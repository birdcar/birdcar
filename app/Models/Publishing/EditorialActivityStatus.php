<?php

namespace App\Models\Publishing;

enum EditorialActivityStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Paused = 'paused';
    case AwaitingApproval = 'awaiting_approval';
    case Declined = 'declined';
    case Completed = 'completed';
    case Failed = 'failed';
    case Stale = 'stale';
}
