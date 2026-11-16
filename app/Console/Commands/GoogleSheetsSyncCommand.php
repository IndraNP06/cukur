<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoogleSheetsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GoogleSheetsSyncCommand extends Command
{
    protected $signature = 'sheets:sync 
                            {--table= : Sinkronkan tabel tertentu (layanan, barang, pengguna, antrean_pemesanan, transaksi)}
                            {--export-csv : Ekspor semua data ke format file CSV di storage/app/sheets_export}';

    protected $description = 'Sinkronisasi data database lokal Ran Cukurin ke Google Spreadsheet';

    protected array $supportedTables = [
        'layanan',
        'barang',
        'pengguna',
        'antrean_pemesanan',
        'transaksi',
        'galeris',
        'banners',
        'faqs',
        'voucher'
    ];

    protected array $hiddenColumns = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes'
    ];

    public function handle(GoogleSheetsService $sheetsService)
    {
        if ($this->option('export-csv')) {
            $this->exportCsv();
            return Command::SUCCESS;
        }

        if (!$sheetsService->isConfigured()) {
            $this->error('GOOGLE_SHEETS_WEBHOOK_URL belum dikonfigurasi di file .env');
            $this->line('');
            $this->info('Langkah mudah untuk menghubungkan:');
            $this->line('1. Buka spreadsheet Anda: https://docs.google.com/spreadsheets/d/' . $sheetsService->getSpreadsheetId());
            $this->line('2. Buka menu Extensions (Ekstensi) > Apps Script.');
            $this->line('3. Tempelkan kode dari file: docs/google_apps_script.js');
            $this->line('4. Klik Deploy > New deployment > Web app (Who has access: Anyone).');
            $this->line('5. Salin Web App URL dan tambahkan ke .env:');
            $this->comment('   GOOGLE_SHEETS_WEBHOOK_URL="https://script.google.com/macros/s/.../exec"');
            $this->line('');
            $this->info('Sementara itu, file CSV data saat ini juga sudah disiapkan di storage/app/sheets_export!');
            $this->comment('   Jalankan: php artisan sheets:sync --export-csv');
            return Command::FAILURE;
        }

        $specificTable = $this->option('table');
        $tablesToSync = $specificTable ? [$specificTable] : $this->supportedTables;

        $this->info('Memulai sinkronisasi data ke Google Spreadsheet...');
        
        foreach ($tablesToSync as $table) {
            if (!Schema::hasTable($table)) {
                $this->warn("Tabel '$table' tidak ditemukan di database. Dilewati.");
                continue;
            }

            $columns = $this->getTableColumns($table);
            $this->line("Mengirim tabel: $table...");
            $result = $sheetsService->syncTable($table, $columns);

            if ($result['success'] ?? false) {
                $this->info("✔ Berhasil sinkronisasi tabel: $table");
            } else {
                $msg = $result['message'] ?? json_encode($result);
                $this->error("✖ Gagal sinkronisasi tabel $table: $msg");
            }
        }

        $this->info('Sinkronisasi selesai.');
        return Command::SUCCESS;
    }

    protected function getTableColumns(string $table): array
    {
        $all = Schema::getColumnListing($table);
        return array_values(array_diff($all, $this->hiddenColumns));
    }

    protected function exportCsv(): void
    {
        $dir = storage_path('app/sheets_export');
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $this->info("Mengekspor tabel ke CSV di direktori: $dir");

        foreach ($this->supportedTables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $columns = $this->getTableColumns($table);
            $rows = DB::table($table)->get($columns);
            $filePath = $dir . DIRECTORY_SEPARATOR . $table . '.csv';
            $fp = fopen($filePath, 'w');

            // Header
            fputcsv($fp, $columns);

            // Data
            foreach ($rows as $row) {
                $rowArray = [];
                foreach ($columns as $col) {
                    $val = $row->$col ?? '';
                    if (is_array($val) || is_object($val)) {
                        $val = json_encode($val);
                    }
                    $rowArray[] = $val;
                }
                fputcsv($fp, $rowArray);
            }

            fclose($fp);
            $this->line("✔ Tersimpan: {$table}.csv (" . count($rows) . " baris)");
        }

        $this->info('Semua data berhasil diekspor ke format CSV!');
        $this->info("Anda dapat mengimpor file ini langsung di Google Sheets: File > Import > Upload.");
    }
}
