<?php

namespace App\Authorization\Contracts;

use BackedEnum;

interface AuthorizationCatalog
{
    /**
     * @return list<BackedEnum>
     */
    public function permissions(): array;

    /**
     * @return list<array{
     *     role: BackedEnum,
     *     permissions: list<BackedEnum>
     * }>
     */
    public function roles(): array;
}
