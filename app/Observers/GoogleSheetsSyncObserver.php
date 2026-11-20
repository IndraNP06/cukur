<?php

namespace App\Observers;

use App\Services\GoogleSheetsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class GoogleSheetsSyncObserver
{
    public static array $tableMap = [
        \App\Models\Layanan::class          => 'layanan',
        \App\Models\Barang::class           => 'barang',
        \App\Models\User::class             => 'pengguna',
        \App\Models\AntreanPemesanan::class => 'antrean_pemesanan',
        \App\Models\Transaksi::class        => 'transaksi',
        \App\Models\Galeri::class           => 'galeris',
        \App\Models\Banner::class           => 'banners',
        \App\Models\Faq::class              => 'faqs',
        \App\Models\Voucher::class          => 'voucher',
    ];

    /**
     * Dipanggil otomatis saat data baru dibuat di web
     */
    public function created(Model $model): void
    {
        $class = get_class($model);
        if (!isset(self::$tableMap[$class])) {
            return;
        }

        $table = self::$tableMap[$class];

        try {
            $data = $model->getAttributes();
            unset($data['remember_token']);

            /** @var GoogleSheetsService $service */
            $service = app(GoogleSheetsService::class);
            $result = $service->appendRow($table, $data);

            if (!($result['success'] ?? false)) {
                Log::warning("Gagal append data ke sheet [$table]: " . ($result['message'] ?? 'Unknown error'));
            }
        } catch (\Throwable $e) {
            Log::error("Error sync created model to Google Sheets [$table]: " . $e->getMessage());
        }
    }

    /**
     * Dipanggil otomatis saat data diubah
     */
    public function updated(Model $model): void
    {
        $class = get_class($model);
        if (!isset(self::$tableMap[$class])) {
            return;
        }

        $table = self::$tableMap[$class];

        // Untuk tabel antrean_pemesanan, barang, dan pengguna: gunakan updateRow yang sangat cepat (<500ms) untuk memperbarui hanya baris tersebut
        if (in_array($table, ['antrean_pemesanan', 'barang', 'pengguna'])) {
            try {
                $data = $model->getAttributes();
                unset($data['remember_token']);
                /** @var GoogleSheetsService $service */
                $service = app(GoogleSheetsService::class);
                $service->updateRow($table, $model->getKey(), $data);
            } catch (\Throwable $e) {
                Log::warning("Error updateRow to Google Sheets [$table]: " . $e->getMessage());
            }
            return;
        }

        $this->syncWholeTable($model);
    }

    /**
     * Dipanggil otomatis saat data dihapus
     */
    public function deleted(Model $model): void
    {
        $this->syncWholeTable($model);
    }

    protected function syncWholeTable(Model $model): void
    {
        $class = get_class($model);
        if (!isset(self::$tableMap[$class])) {
            return;
        }

        $table = self::$tableMap[$class];

        // Cegah sinkronisasi tabel besar (ribuan baris seperti antrean, transaksi, dan pengguna) secara sinkron pada request HTTP/Livewire
        if (in_array($table, ['antrean_pemesanan', 'transaksi', 'pengguna'])) {
            return;
        }

        try {
            /** @var GoogleSheetsService $service */
            $service = app(GoogleSheetsService::class);
            $service->syncTable($table);
        } catch (\Throwable $e) {
            Log::error("Error sync updated/deleted table to Google Sheets [$table]: " . $e->getMessage());
        }
    }
}
