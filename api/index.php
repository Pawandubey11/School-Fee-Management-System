<?php

// Serverless preparation for Vercel
// Vercel execution environment has a read-only filesystem except for /tmp

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

// Prepare SQLite database in /tmp if using sqlite
$sqlitePath = '/tmp/database.sqlite';
$bundledDb = __DIR__ . '/../database/database.sqlite';

if (!file_exists($sqlitePath)) {
    if (file_exists($bundledDb) && filesize($bundledDb) > 0) {
        @copy($bundledDb, $sqlitePath);
    } else {
        @touch($sqlitePath);
    }
}

// Forward execution to Laravel public/index.php
require __DIR__ . '/../public/index.php';
