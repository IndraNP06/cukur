<?php

/**
 * Entry point untuk Serverless Deployment di Vercel.
 * Filesystem Vercel bersifat Read-Only kecuali di direktori /tmp.
 * Script ini memastikan direktori storage runtime tersedia di /tmp.
 */

$storageDirs = [
    '/tmp/storage',
    '/tmp/storage/app',
    '/tmp/storage/app/public',
    '/tmp/storage/framework',
    '/tmp/storage/framework/cache',
    '/tmp/storage/framework/cache/data',
    '/tmp/storage/framework/views',
    '/tmp/storage/framework/sessions',
    '/tmp/storage/logs',
];

foreach ($storageDirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
}

// Set storage path ke /tmp/storage
putenv('APP_STORAGE=/tmp/storage');
$_ENV['APP_STORAGE'] = '/tmp/storage';

// Forward request ke public/index.php Laravel
require __DIR__ . '/../public/index.php';
