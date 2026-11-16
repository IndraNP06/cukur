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

        // Daftarkan observer sinkronisasi Google Sheets otomatis untuk semua model utama
        foreach (\App\Observers\GoogleSheetsSyncObserver::$tableMap as $modelClass => $table) {
            if (class_exists($modelClass)) {
                $modelClass::observe(\App\Observers\GoogleSheetsSyncObserver::class);
            }
        }
    }
}
