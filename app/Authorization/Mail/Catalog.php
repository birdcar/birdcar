<?php

namespace App\Authorization\Mail;

use App\Authorization\Contracts\AuthorizationCatalog;

final class Catalog implements AuthorizationCatalog
{
    public function permissions(): array
    {
        return [
            Permission::ConfigureSenders,
        ];
    }

    public function roles(): array
    {
        return [
            [
                'role' => Role::Operator,
                'permissions' => [
                    Permission::ConfigureSenders,
                ],
            ],
        ];
    }
}
