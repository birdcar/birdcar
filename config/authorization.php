<?php

use App\Authorization\Admin\Catalog as AdminCatalog;
use App\Authorization\Organizations\Catalog as OrganizationsCatalog;

return [
    'guard' => 'web',

    'catalogs' => [
        AdminCatalog::class,
        OrganizationsCatalog::class,
    ],
];
