<?php

namespace App\Authorization\Publishing;

use App\Authorization\Contracts\AuthorizationCatalog;

final class Catalog implements AuthorizationCatalog
{
    public function permissions(): array
    {
        return [
            Permission::View,
            Permission::Write,
            Permission::Develop,
            Permission::Approve,
            Permission::Publish,
            Permission::Budget,
        ];
    }

    public function roles(): array
    {
        return [
            [
                'role' => Role::Author,
                'permissions' => [
                    Permission::View,
                    Permission::Write,
                    Permission::Develop,
                    Permission::Approve,
                    Permission::Publish,
                    Permission::Budget,
                ],
            ],
        ];
    }
}
