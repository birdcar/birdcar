<?php

namespace App\Authorization\Publishing;

enum Permission: string
{
    case View = 'publishing.view';
    case Write = 'publishing.write';
    case Develop = 'publishing.develop';
    case Approve = 'publishing.approve';
    case Publish = 'publishing.publish';
    case Budget = 'publishing.budget';
}
