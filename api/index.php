<?php

// Serverless runtime preparation for Vercel
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'UTC');

// Ensure writable directories in /tmp
$storageDirs = [
    '/tmp/storage/app',
    '/tmp/storage/app/public',
    '/tmp/storage/framework',
    '/tmp/storage/framework/cache',
    '/tmp/storage/framework/cache/data',
    '/tmp/storage/framework/sessions',
    '/tmp/storage/framework/views',
    '/tmp/storage/logs',
    '/tmp/views',
    '/tmp/database',
];

foreach ($storageDirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// Prepare SQLite database in /tmp
$sqlitePath = '/tmp/database.sqlite';
$isNewDb = !file_exists($sqlitePath) || filesize($sqlitePath) === 0;

if ($isNewDb) {
    @touch($sqlitePath);
}

// Autoload and bootstrap Laravel
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

// Auto-migrate and seed if newly initialized
if ($isNewDb && (getenv('DB_CONNECTION') ?: 'sqlite') === 'sqlite') {
    try {
        $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
        $kernel->call('migrate', ['--force' => true]);
        $kernel->call('db:seed', ['--force' => true]);
    } catch (\Throwable $e) {
        error_log('Auto-migration notice: ' . $e->getMessage());
    }
}

// Handle HTTP request
$app->handleRequest(\Illuminate\Http\Request::capture());
