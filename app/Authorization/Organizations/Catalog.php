<?php

namespace App\Authorization\Organizations;

use App\Authorization\Contracts\AuthorizationCatalog;

final class Catalog implements AuthorizationCatalog
{
    public function permissions(): array
    {
        return [
            Permission::View,
            Permission::Update,
        ];
    }

    public function roles(): array
    {
        return [
            [
                'role' => Role::Viewer,
                'permissions' => [
                    Permission::View,
                ],
            ],
            [
                'role' => Role::Editor,
                'permissions' => [
                    Permission::Update,
                ],
            ],
        ];
    }
}
