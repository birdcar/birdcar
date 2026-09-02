<?php

namespace App\Authorization\Organizations;

enum Role: string
{
    case Viewer = 'organizations.viewer';
    case Editor = 'organizations.editor';
}
