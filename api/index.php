<?php

/**
 * Vercel Serverless Function Bridge for Laravel
 */

@set_time_limit(180);
@ini_set('max_execution_time', '180');
@ini_set('memory_limit', '512M');

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
$shouldCopy = !file_exists($tmpDb);
if (!$shouldCopy && file_exists($dbSource)) {
    if (filemtime($dbSource) > filemtime($tmpDb) || filesize($dbSource) !== filesize($tmpDb)) {
        $shouldCopy = true;
    }
}

if ($shouldCopy && file_exists($dbSource)) {
    @copy($dbSource, $tmpDb);
    @touch($tmpDb, filemtime($dbSource));
    // Clear stale compiled blade views on new deploy / DB update
    foreach (glob('/tmp/storage/framework/views/*.php') ?: [] as $vFile) {
        @unlink($vFile);
    }
} elseif (!file_exists($tmpDb)) {
    @touch($tmpDb);
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
