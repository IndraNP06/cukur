<?php

/**
 * Entry point untuk Serverless Deployment di Vercel (Laravel 12).
 * Database aplikasi menggunakan Google Spreadsheet sebagai single source of truth,
 * dengan SQLite di direktori /tmp sebagai database cache serverless berkecepatan tinggi.
 */

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

define('LARAVEL_START', microtime(true));

// Pastikan direktori storage sementara di /tmp tersedia
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

// Konfigurasi storage path dan SQLite untuk Vercel
putenv('APP_STORAGE=/tmp/storage');
$_ENV['APP_STORAGE'] = '/tmp/storage';

putenv('ASSET_URL=/');
$_ENV['ASSET_URL'] = '/';

putenv('DB_CONNECTION=sqlite');
$_ENV['DB_CONNECTION'] = 'sqlite';

$sqlitePath = '/tmp/database.sqlite';
putenv("DB_DATABASE={$sqlitePath}");
$_ENV['DB_DATABASE'] = $sqlitePath;

$isFreshDb = !file_exists($sqlitePath) || filesize($sqlitePath) === 0;

// Autoloader Composer
require __DIR__ . '/../vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';

// Inisialisasi container & facade Laravel
$app->make(ConsoleKernel::class)->bootstrap();

// Jika container baru (database SQLite belum siap), lakukan migrasi dan tarik data dari Google Sheets
if ($isFreshDb) {
    try {
        @touch($sqlitePath);
        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('db:seed', ['--class' => 'RoleSeeder', '--force' => true]);
        Artisan::call('sheets:sync', ['--pull' => true]);
    } catch (\Throwable $e) {
        // Abaikan jika database sudah diinisialisasi
    }
}

// Tangani HTTP request
$app->handleRequest(Request::capture());
