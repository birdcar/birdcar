<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Public Writing Reader
    |--------------------------------------------------------------------------
    |
    | Select the whole public writing source. Use "files" before CMS cutover
    | and "database" after the archive has been imported and verified. Database
    | mode never falls back to files article-by-article.
    |
    */
    'public_reader' => env('PUBLISHING_PUBLIC_READER', 'files'),
];
