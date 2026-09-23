<?php

$configuredUrl = env('ADMIN_URL', 'https://admin.birdcar.dev');
$url = is_string($configuredUrl) && $configuredUrl !== '' ? $configuredUrl : 'https://admin.birdcar.dev';
$host = parse_url($url, PHP_URL_HOST) ?: 'admin.birdcar.dev';

return [
    'url' => rtrim($url, '/'),
    'host' => $host,
];
