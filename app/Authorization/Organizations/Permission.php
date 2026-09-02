<?php

namespace App\Authorization\Organizations;

enum Permission: string
{
    case View = 'organizations.view';
    case Update = 'organizations.update';
}
