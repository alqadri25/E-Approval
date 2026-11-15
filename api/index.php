<?php

/**
 * Vercel Serverless Function Bridge for Laravel
 */

// Ensure writable directories exist in /tmp
$tmpDirs = [
    '/tmp/views',
    '/tmp/storage',
    '/tmp/storage/app',
    '/tmp/storage/app/public',
    '/tmp/storage/framework',
    '/tmp/storage/framework/cache',
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

// Copy pre-seeded SQLite database to writable /tmp
$dbSource = __DIR__ . '/../database/database.sqlite';
$tmpDb = '/tmp/database.sqlite';
if (!file_exists($tmpDb)) {
    if (file_exists($dbSource)) {
        @copy($dbSource, $tmpDb);
    } else {
        @touch($tmpDb);
    }
}

putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . $tmpDb);
$_ENV['DB_DATABASE'] = $tmpDb;
$_SERVER['DB_DATABASE'] = $tmpDb;
$_ENV['DB_CONNECTION'] = 'sqlite';
$_SERVER['DB_CONNECTION'] = 'sqlite';

if (empty(getenv('APP_KEY'))) {
    $fallbackKey = 'base64:iI/y07XaGJ1V1DZ4AEuy11Oa2HmBVQmMdcJSHvVMvcg=';
    putenv('APP_KEY=' . $fallbackKey);
    $_ENV['APP_KEY'] = $fallbackKey;
    $_SERVER['APP_KEY'] = $fallbackKey;
}

require __DIR__ . '/../public/index.php';
