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

$_SERVER['SCRIPT_NAME'] = '/index.php';

putenv('DB_CONNECTION=sqlite');
$_ENV['DB_CONNECTION'] = 'sqlite';

$sqlitePath = '/tmp/database.sqlite';
putenv("DB_DATABASE={$sqlitePath}");
$_ENV['DB_DATABASE'] = $sqlitePath;

$bundledDb = __DIR__ . '/../database/database.sqlite';
$copiedDb = false;
if (!file_exists($sqlitePath) || filesize($sqlitePath) === 0) {
    if (file_exists($bundledDb) && filesize($bundledDb) > 0) {
        @copy($bundledDb, $sqlitePath);
        $copiedDb = true;
    } else {
        @touch($sqlitePath);
        $freshSetupNeeded = true;
    }
}

// Autoloader Composer
require __DIR__ . '/../vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';

// Inisialisasi container & facade Laravel
$app->make(ConsoleKernel::class)->bootstrap();

// Jika database baru disalin dari bundle, segera tarik antrean aktif dan pengguna terbaru dari Google Sheets
if ($copiedDb) {
    try {
        if (!\Illuminate\Support\Facades\Schema::hasColumn('pengguna', 'email_verified_at')) {
            Artisan::call('migrate', ['--force' => true]);
        }
        app(\App\Services\GoogleSheetsService::class)->pullActiveAntrean();
        app(\App\Services\GoogleSheetsService::class)->pullRecentUsers();
    } catch (\Throwable $e) {
        // Abaikan jika network timeout
    }
}

// Jika bundled database belum ada, jalankan migrasi & sinkronisasi awal
if (!empty($freshSetupNeeded)) {
    try {
        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('db:seed', ['--class' => 'RoleSeeder', '--force' => true]);
        Artisan::call('sheets:sync', ['--pull' => true]);
    } catch (\Throwable $e) {
        // Abaikan jika database sudah diinisialisasi
    }
}

// Tangani HTTP request
$app->handleRequest(Request::capture());
