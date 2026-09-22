<?php

use App\Authorization\Admin\Catalog as AdminCatalog;
use App\Authorization\Organizations\Catalog as OrganizationsCatalog;
use App\Authorization\Publishing\Catalog as PublishingCatalog;

return [
    'guard' => 'web',

    'catalogs' => [
        AdminCatalog::class,
        OrganizationsCatalog::class,
        PublishingCatalog::class,
    ],
];
