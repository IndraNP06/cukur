<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoogleSheetsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GoogleSheetsSyncCommand extends Command
{
    protected $signature = 'sheets:sync 
                            {--pull : Tarik data dari Google Spreadsheet ke database}
                            {--table= : Tabel tertentu (layanan, barang, pengguna, antrean_pemesanan, dll.)}
                            {--export-csv : Ekspor semua data ke format file CSV di storage/app/sheets_export}';

    protected $description = 'Sinkronisasi data dua arah antara database lokal dan Google Spreadsheet';

    public function handle(GoogleSheetsService $sheetsService)
    {
        if ($this->option('export-csv')) {
            $this->exportCsv($sheetsService);
            return Command::SUCCESS;
        }

        if ($this->option('pull')) {
            return $this->pullFromSheets($sheetsService);
        }

        // Default: Push ke Google Sheets
        return $this->pushToSheets($sheetsService);
    }

    protected function pullFromSheets(GoogleSheetsService $sheetsService): int
    {
        $specificTable = $this->option('table');
        $tables = $specificTable ? [$specificTable] : $sheetsService->tables;

        $this->info('Memulai penarikan data dari Google Spreadsheet...');

        foreach ($tables as $table) {
            $this->line("Menarik tabel: $table...");
            $res = $sheetsService->pullTable($table);
            if ($res['success']) {
                $count = $res['count'] ?? 0;
                $this->info("✔ $table: Berhasil memuat $count baris.");
            } else {
                $this->error("✖ $table: " . ($res['message'] ?? 'Gagal menarik data'));
            }
        }

        $this->info('Penarikan data selesai.');
        return Command::SUCCESS;
    }

    protected function pushToSheets(GoogleSheetsService $sheetsService): int
    {
        if (empty(env('GOOGLE_SHEETS_WEBHOOK_URL'))) {
            $this->error('GOOGLE_SHEETS_WEBHOOK_URL belum dikonfigurasi di file .env');
            $this->line('Untuk menarik data dari Google Sheets (Pull), gunakan:');
            $this->comment('   php artisan sheets:sync --pull');
            return Command::FAILURE;
        }

        $specificTable = $this->option('table');
        $tablesToSync = $specificTable ? [$specificTable] : $sheetsService->tables;

        $this->info('Mengirim data database ke Google Spreadsheet...');
        foreach ($tablesToSync as $table) {
            if (!Schema::hasTable($table)) continue;
            $this->line("Mengirim tabel: $table...");
            $columns = Schema::getColumnListing($table);
            $columns = array_diff($columns, ['password', 'remember_token']);
            $result = $sheetsService->syncTable($table, array_values($columns));

            if ($result['success'] ?? false) {
                $this->info("✔ Berhasil kirim tabel: $table");
            } else {
                $msg = $result['message'] ?? json_encode($result);
                $this->error("✖ Gagal kirim tabel $table: $msg");
            }
        }

        $this->info('Sinkronisasi push selesai.');
        return Command::SUCCESS;
    }

    protected function exportCsv(GoogleSheetsService $sheetsService): void
    {
        $dir = storage_path('app/sheets_export');
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $this->info("Mengekspor tabel ke CSV di direktori: $dir");

        foreach ($sheetsService->tables as $table) {
            if (!Schema::hasTable($table)) continue;
            $columns = Schema::getColumnListing($table);
            $columns = array_diff($columns, ['password', 'remember_token']);
            $rows = DB::table($table)->get($columns);
            $filePath = $dir . DIRECTORY_SEPARATOR . $table . '.csv';
            $fp = fopen($filePath, 'w');
            fputcsv($fp, array_values($columns));

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

        $this->info('Semua data berhasil diekspor!');
    }
}
