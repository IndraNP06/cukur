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
        'voucher',
        'ulasan'
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

            DB::beginTransaction();
            $batch = [];
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

                // Khusus tabel voucher: isi default jika kuota_terpakai atau is_active kosong
                if ($table === 'voucher') {
                    if (!isset($record['kuota_terpakai']) || $record['kuota_terpakai'] === null || $record['kuota_terpakai'] === '') {
                        $record['kuota_terpakai'] = 0;
                    }
                    if (!isset($record['is_active']) || $record['is_active'] === null || $record['is_active'] === '') {
                        $record['is_active'] = 1;
                    }
                }

                // Khusus tabel layanan: isi default jika harga atau durasi kosong
                if ($table === 'layanan') {
                    if (!isset($record['harga']) || $record['harga'] === '' || $record['harga'] === null) {
                        $record['harga'] = 0;
                    }
                    if (!isset($record['estimasi_durasi_menit']) || $record['estimasi_durasi_menit'] === '' || $record['estimasi_durasi_menit'] === null) {
                        $record['estimasi_durasi_menit'] = 30;
                    }
                }

                // Default dan normalisasi boolean is_active untuk semua tabel
                if (array_key_exists('is_active', $record)) {
                    $rawActive = strtolower(trim((string)$record['is_active']));
                    if ($rawActive === '' || $rawActive === '1' || $rawActive === 'true' || $rawActive === 'ya' || $rawActive === 'yes') {
                        $record['is_active'] = 1;
                    } elseif ($rawActive === '0' || $rawActive === 'false' || $rawActive === 'tidak' || $rawActive === 'no') {
                        $record['is_active'] = 0;
                    } else {
                        $record['is_active'] = 1;
                    }
                }

                // Normalisasi created_at dan updated_at agar selalu berformat Y-m-d H:i:s
                foreach (['created_at', 'updated_at'] as $dateCol) {
                    if (!empty($record[$dateCol])) {
                        try {
                            $record[$dateCol] = \Carbon\Carbon::parse($record[$dateCol])->format('Y-m-d H:i:s');
                        } catch (\Throwable $e) {}
                    }
                }

                if (!empty($record)) {
                    $batch[] = $record;
                    if (count($batch) >= 100) {
                        try {
                            DB::table($table)->insert($batch);
                            $imported += count($batch);
                        } catch (\Throwable $e) {
                            Log::warning("Gagal insert batch di $table: " . $e->getMessage());
                        }
                        $batch = [];
                    }
                }
            }

            if (!empty($batch)) {
                try {
                    DB::table($table)->insert($batch);
                    $imported += count($batch);
                } catch (\Throwable $e) {
                    Log::warning("Gagal insert sisa batch di $table: " . $e->getMessage());
                }
            }
            DB::commit();

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
     * Tarik hanya antrean aktif (Diproses dan antrean hari ini/kemarin) dari Google Sheets secara super cepat (<1s)
     * Ini menjamin kontainer serverless selalu memiliki status antrean terbaru tanpa rollback.
     */
    public function pullActiveAntrean(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Google Sheets belum dikonfigurasi'];
        }

        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $tq = "select * where K = 'Diproses' or G = date '{$today}' or (G = date '{$yesterday}' and (K = 'Diproses' or K = 'Selesai'))";
        $url = "https://docs.google.com/spreadsheets/d/{$this->spreadsheetId}/gviz/tq?tqx=out:csv&sheet=antrean_pemesanan&tq=" . urlencode($tq);

        try {
            $response = Http::withoutVerifying()->timeout(5)->get($url);
            if (!$response->successful()) {
                return ['success' => false, 'message' => 'Gagal mengambil data dari Google Sheets'];
            }

            $csv = $response->body();
            if (empty(trim($csv))) {
                return ['success' => true, 'count' => 0];
            }

            $lines = explode("\n", trim($csv));
            $headers = str_getcsv(array_shift($lines));
            $headers = array_map(function($h) { return trim($h, " \t\n\r\0\x0B\""); }, $headers);
            $availableColumns = $this->getTableColumns('antrean_pemesanan');
            $validHeaders = array_intersect($headers, $availableColumns);

            DB::beginTransaction();
            $updated = 0;
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
                if (!empty($record['id'])) {
                    DB::table('antrean_pemesanan')->updateOrInsert(['id' => $record['id']], $record);
                    $updated++;
                }
            }
            DB::commit();

            return ['success' => true, 'count' => $updated];
        } catch (\Throwable $e) {
            Log::warning("Error pullActiveAntrean: " . $e->getMessage());
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
     * Perbarui baris tertentu di Google Sheet berdasarkan ID (Sangat cepat <500ms)
     */
    public function updateRow(string $table, $id, array $data): array
    {
        if (empty($this->webhookUrl)) {
            return ['success' => false, 'message' => 'GOOGLE_SHEETS_WEBHOOK_URL belum diset'];
        }

        try {
            unset($data['password'], $data['remember_token']);
            $response = Http::withoutVerifying()->timeout(5)->post($this->webhookUrl, [
                'action' => 'update_row',
                'table'  => $table,
                'id'     => $id,
                'data'   => $data,
            ]);

            return [
                'success'  => $response->successful(),
                'response' => $response->json() ?? $response->body()
            ];
        } catch (\Throwable $e) {
            Log::warning("Google Sheets UpdateRow Warning [$table]: " . $e->getMessage());
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
