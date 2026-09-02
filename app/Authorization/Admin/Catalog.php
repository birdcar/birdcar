<?php

namespace App\Authorization\Admin;

use App\Authorization\Contracts\AuthorizationCatalog;

final class Catalog implements AuthorizationCatalog
{
    public function permissions(): array
    {
        return [
            Permission::View,
        ];
    }

    public function roles(): array
    {
        return [
            [
                'role' => Role::Access,
                'permissions' => [
                    Permission::View,
                ],
            ],
        ];
    }
}
