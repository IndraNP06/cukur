<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class GoogleSheetsService
{
    protected ?string $webhookUrl;
    protected string $spreadsheetId;

    public function __construct()
    {
        $this->webhookUrl = config('services.google_sheets.webhook_url', env('GOOGLE_SHEETS_WEBHOOK_URL'));
        $this->spreadsheetId = config('services.google_sheets.spreadsheet_id', env('GOOGLE_SHEETS_ID', '16oP7uh_2RwNdQwW7ZBJFk12gq0Lv94dH4xPxU54ub0k'));
    }

    /**
     * Check whether webhook URL is configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->webhookUrl);
    }

    /**
     * Get spreadsheet ID.
     */
    public function getSpreadsheetId(): string
    {
        return $this->spreadsheetId;
    }

    /**
     * Sync an entire database table to the corresponding sheet tab.
     */
    public function syncTable(string $table, ?array $customColumns = null): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'GOOGLE_SHEETS_WEBHOOK_URL belum diset di file .env'
            ];
        }

        $query = DB::table($table);
        $records = $query->get();

        if ($records->isEmpty()) {
            return [
                'success' => true,
                'message' => "Tabel $table kosong, tidak ada data yang dikirim."
            ];
        }

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
                $rows[] = $row;
            }
        }

        try {
            $response = Http::timeout(30)->post($this->webhookUrl, [
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
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Append a single new record to a sheet tab (e.g. on new Booking).
     */
    public function appendRow(string $table, array $data): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Webhook URL not configured'];
        }

        try {
            $response = Http::timeout(10)->post($this->webhookUrl, [
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
     * Read data from a sheet tab if published/served by webhook.
     */
    public function fetchSheet(string $table): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        try {
            $response = Http::timeout(15)->get($this->webhookUrl, [
                'action' => 'get_table',
                'table'  => $table
            ]);

            if ($response->successful()) {
                return $response->json('data') ?? [];
            }
        } catch (\Throwable $e) {
            Log::error("Google Sheets Fetch Error [$table]: " . $e->getMessage());
        }

        return [];
    }
}
