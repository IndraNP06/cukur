<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('production') || isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            URL::forceScheme('https');
        }

        // Otomatis sesuaikan Root URL untuk Vercel Serverless agar tautan email (verifikasi & reset password) akurat
        if (isset($_SERVER['HTTP_X_FORWARDED_HOST']) || isset($_SERVER['HTTP_HOST'])) {
            $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'];
            $proto = (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'http') ? 'http' : 'https';
            URL::forceRootUrl($proto . '://' . $host);
        }

        // Daftarkan observer sinkronisasi Google Sheets otomatis untuk semua model utama
        foreach (\App\Observers\GoogleSheetsSyncObserver::$tableMap as $modelClass => $table) {
            if (class_exists($modelClass)) {
                $modelClass::observe(\App\Observers\GoogleSheetsSyncObserver::class);
            }
        }

        // Daftarkan custom email channel untuk pengiriman multi-driver (SMTP / Webhook / Log)
        \Illuminate\Support\Facades\Notification::extend('custom_email', function ($app) {
            return new \App\Notifications\Channels\CustomEmailChannel();
        });
    }
}
