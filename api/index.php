<?php

/**
 * Vercel Serverless Function Bridge for Laravel
 */

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Ensure essential writable directories exist in /tmp
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

// Set runtime environment variables for Vercel Serverless
putenv('APP_ENV=production');
putenv('APP_DEBUG=false');
putenv('APP_CONFIG_CACHE=/tmp/config.php');
putenv('APP_EVENTS_CACHE=/tmp/events.php');
putenv('APP_PACKAGES_CACHE=/tmp/packages.php');
putenv('APP_ROUTES_CACHE=/tmp/routes.php');
putenv('APP_SERVICES_CACHE=/tmp/services.php');
putenv('VIEW_COMPILED_PATH=/tmp/views');
putenv('LARAVEL_STORAGE_PATH=/tmp/storage');
putenv('LOG_CHANNEL=stderr');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . $tmpDb);

$_ENV['LARAVEL_STORAGE_PATH'] = '/tmp/storage';
$_SERVER['LARAVEL_STORAGE_PATH'] = '/tmp/storage';
$_ENV['DB_DATABASE'] = $tmpDb;
$_SERVER['DB_DATABASE'] = $tmpDb;
$_ENV['DB_CONNECTION'] = 'sqlite';
$_SERVER['DB_CONNECTION'] = 'sqlite';

// Fallback APP_KEY if not configured in Vercel Dashboard
if (empty(getenv('APP_KEY'))) {
    $fallbackKey = 'base64:iI/y07XaGJ1V1DZ4AEuy11Oa2HmBVQmMdcJSHvVMvcg=';
    putenv('APP_KEY=' . $fallbackKey);
    $_ENV['APP_KEY'] = $fallbackKey;
    $_SERVER['APP_KEY'] = $fallbackKey;
}

try {
    // Forward execution to Laravel's public entrypoint
    require __DIR__ . '/../public/index.php';
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>500 Application Error</title></head><body style="font-family:sans-serif;padding:30px;background:#fef2f2;color:#991b1b;">';
    echo '<h2>500 - Application Error</h2>';
    echo '<p><strong>' . htmlspecialchars($e->getMessage()) . '</strong></p>';
    echo '<pre style="background:#fff;padding:15px;border-radius:6px;border:1px solid #f87171;overflow:auto;">' . htmlspecialchars($e->getFile() . ':' . $e->getLine()) . "\n\n" . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    echo '</body></html>';
}
