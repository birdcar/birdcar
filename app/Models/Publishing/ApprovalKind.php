<?php

namespace App\Models\Publishing;

enum ApprovalKind: string
{
    case Angle = 'angle';
    case Plan = 'plan';
    case Release = 'release';
}
