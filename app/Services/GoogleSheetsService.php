<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Spatie\Permission\Models\Role;

class GoogleSheetsService
{
    protected ?string $webhookUrl;
    protected string $spreadsheetId;

    public array $tables = [
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

    public function __construct()
    {
        $this->webhookUrl = config('services.google_sheets.webhook_url', env('GOOGLE_SHEETS_WEBHOOK_URL'));
        $this->spreadsheetId = config('services.google_sheets.spreadsheet_id', env('GOOGLE_SHEETS_ID', '16oP7uh_2RwNdQwW7ZBJFk12gq0Lv94dH4xPxU54ub0k'));
    }

    public function isConfigured(): bool
    {
        return !empty($this->spreadsheetId);
    }

    public function getSpreadsheetId(): string
    {
        return $this->spreadsheetId;
    }

    public function tableExists(string $table): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            $result = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name = ?", [$table]);
            return !empty($result);
        }
        return Schema::hasTable($table);
    }

    public function getTableColumns(string $table): array
    {
        if (DB::getDriverName() === 'sqlite') {
            $cols = DB::select("PRAGMA table_info(`{$table}`)");
            return array_map(function($c) {
                return is_array($c) ? $c['name'] : $c->name;
            }, $cols);
        }
        return Schema::getColumnListing($table);
    }

    /**
     * Tarik data dari tab Google Sheet langsung menggunakan Google Visualization CSV API
     */
    public function pullTable(string $table): array
    {
        if (!$this->tableExists($table)) {
            return ['success' => false, 'message' => "Tabel $table tidak ditemukan di skema database."];
        }

        $url = "https://docs.google.com/spreadsheets/d/{$this->spreadsheetId}/gviz/tq?tqx=out:csv&sheet=" . urlencode($table);

        try {
            $csvData = false;
            try {
                $response = Http::withoutVerifying()->timeout(15)->get($url);
                if ($response->successful()) {
                    $csvData = $response->body();
                }
            } catch (\Throwable $e) {
                // cURL fallback
            }

            if ($csvData === false || empty(trim($csvData))) {
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 15,
                        'header'  => "User-Agent: Mozilla/5.0\r\n"
                    ],
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ]
                ]);
                $csvData = @file_get_contents($url, false, $context);
            }

            if ($csvData === false || empty(trim($csvData))) {
                return ['success' => false, 'message' => "Gagal mengambil data dari Google Sheets untuk sheet: $table"];
            }

            $lines = explode("\n", trim($csvData));
            if (empty($lines)) {
                return ['success' => true, 'count' => 0, 'message' => "Sheet $table kosong."];
            }

            $headers = str_getcsv(array_shift($lines));
            $headers = array_map(function($h) {
                return trim($h, " \t\n\r\0\x0B\"");
            }, $headers);

            $availableColumns = $this->getTableColumns($table);
            $validHeaders = array_intersect($headers, $availableColumns);

            $imported = 0;
            if (DB::getDriverName() === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = OFF;');
            }
            if (DB::getDriverName() === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            }

            // Bersihkan data lama agar sinkron persis dengan spreadsheet
            DB::table($table)->truncate();

            foreach ($lines as $line) {
                if (empty(trim($line))) continue;
                $row = str_getcsv($line);
                if (count($row) < count($headers)) {
                    $row = array_pad($row, count($headers), '');
                }

                $record = [];
                foreach ($headers as $idx => $colName) {
                    if (in_array($colName, $validHeaders)) {
                        $val = $row[$idx] ?? null;
                        if ($val === '' || $val === 'null' || $val === 'NULL') {
                            $val = null;
                        }
                        $record[$colName] = $val;
                    }
                }

                // Khusus tabel pengguna: isi password default jika kosong
                if ($table === 'pengguna') {
                    if (empty($record['password'])) {
                        $record['password'] = Hash::make('password123');
                    }
                }

                if (!empty($record)) {
                    try {
                        DB::table($table)->insert($record);
                        $imported++;
                    } catch (\Throwable $e) {
                        Log::warning("Gagal insert baris di $table: " . $e->getMessage());
                    }
                }
            }

            // Pastikan role Spatie terhubung untuk tabel pengguna
            if ($table === 'pengguna') {
                $this->assignRolesForUsers();
            }

            if (DB::getDriverName() === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON;');
            }
            if (DB::getDriverName() === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            }

            return [
                'success' => true,
                'count'   => $imported,
                'message' => "Berhasil memuat $imported baris ke tabel $table."
            ];

        } catch (\Throwable $e) {
            Log::error("Error pullTable [$table]: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Tarik seluruh tabel dari Google Sheets ke database lokal/serverless
     */
    public function pullAll(): array
    {
        $results = [];
        foreach ($this->tables as $table) {
            $results[$table] = $this->pullTable($table);
        }
        return $results;
    }

    /**
     * Pastikan role Spatie ter-assign untuk semua pengguna
     */
    protected function assignRolesForUsers(): void
    {
        $roles = ['Pelanggan', 'Kapster', 'Pemilik'];
        foreach ($roles as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }

        $users = User::all();
        foreach ($users as $user) {
            if (!empty($user->role) && in_array($user->role, $roles)) {
                if (!$user->hasRole($user->role)) {
                    $user->assignRole($user->role);
                }
            }
        }
    }

    /**
     * Kirim baris baru ke Google Sheet (melalui Apps Script Webhook)
     */
    public function appendRow(string $table, array $data): array
    {
        if (empty($this->webhookUrl)) {
            return ['success' => false, 'message' => 'GOOGLE_SHEETS_WEBHOOK_URL belum diset'];
        }

        try {
            $response = Http::withoutVerifying()->timeout(15)->post($this->webhookUrl, [
                'action' => 'append_row',
                'table'  => $table,
                'data'   => $data,
            ]);

            return [
                'success'  => $response->successful(),
                'response' => $response->json() ?? $response->body()
            ];
        } catch (\Throwable $e) {
            Log::error("Google Sheets Append Error [$table]: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Sinkronkan tabel lokal ke Google Sheet (Push)
     */
    public function syncTable(string $table, ?array $customColumns = null): array
    {
        if (empty($this->webhookUrl)) {
            return ['success' => false, 'message' => 'GOOGLE_SHEETS_WEBHOOK_URL belum diset'];
        }

        if (!$this->tableExists($table)) {
            return ['success' => false, 'message' => "Tabel $table tidak ditemukan"];
        }

        $query = DB::table($table);
        $records = $query->get();

        $rows = [];
        foreach ($records as $record) {
            $row = (array) $record;
            if ($customColumns) {
                $filtered = [];
                foreach ($customColumns as $col) {
                    $filtered[$col] = $row[$col] ?? '';
                }
                $rows[] = $filtered;
            } else {
                unset($row['password'], $row['remember_token']);
                $rows[] = $row;
            }
        }

        try {
            $response = Http::withoutVerifying()->timeout(30)->post($this->webhookUrl, [
                'action' => 'sync_table',
                'table'  => $table,
                'rows'   => $rows,
            ]);

            return [
                'success'  => $response->successful(),
                'status'   => $response->status(),
                'response' => $response->json() ?? $response->body()
            ];
        } catch (\Throwable $e) {
            Log::error("Google Sheets Sync Error [$table]: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
