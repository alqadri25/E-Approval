<?php

/**
 * Vercel Serverless Function Bridge for Laravel
 */

// Create necessary writable directories in /tmp for serverless execution
$tmpDirs = [
    '/tmp/views',
    '/tmp/storage/app/public',
    '/tmp/storage/framework/cache/data',
    '/tmp/storage/framework/sessions',
    '/tmp/storage/framework/views',
    '/tmp/storage/logs',
];

foreach ($tmpDirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// Copy pre-seeded SQLite database to writable /tmp if present
$dbSource = __DIR__ . '/../database/database.sqlite';
$tmpDb = '/tmp/database.sqlite';
if (!file_exists($tmpDb) && file_exists($dbSource)) {
    @copy($dbSource, $tmpDb);
}

// Set runtime environment variables for Vercel Serverless
putenv('APP_CONFIG_CACHE=/tmp/config.php');
putenv('APP_EVENTS_CACHE=/tmp/events.php');
putenv('APP_PACKAGES_CACHE=/tmp/packages.php');
putenv('APP_ROUTES_CACHE=/tmp/routes.php');
putenv('APP_SERVICES_CACHE=/tmp/services.php');
putenv('VIEW_COMPILED_PATH=/tmp/views');
putenv('LARAVEL_STORAGE_PATH=/tmp/storage');
putenv('LOG_CHANNEL=stderr');

$_ENV['LARAVEL_STORAGE_PATH'] = '/tmp/storage';
$_SERVER['LARAVEL_STORAGE_PATH'] = '/tmp/storage';

if (file_exists($tmpDb)) {
    putenv('DB_CONNECTION=sqlite');
    putenv('DB_DATABASE=' . $tmpDb);
    $_ENV['DB_DATABASE'] = $tmpDb;
    $_SERVER['DB_DATABASE'] = $tmpDb;
}

// Forward execution to Laravel's public entrypoint
require __DIR__ . '/../public/index.php';

