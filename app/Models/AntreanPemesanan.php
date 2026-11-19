<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AntreanPemesanan extends Model
{
    use HasFactory;
    protected $table = 'antrean_pemesanan';
    protected $fillable = ['pelanggan_id', 'kapster_id', 'layanan_id', 'tipe_antrean', 'nomor_antrean', 'tanggal', 'waktu_estimasi_mulai', 'waktu_mulai_aktual', 'waktu_selesai_aktual', 'status', 'alasan_batal'];

    public function pelanggan() {
        return $this->belongsTo(User::class, 'pelanggan_id');
    }
    public function kapster() {
        return $this->belongsTo(User::class, 'kapster_id');
    }
    public function layanan() {
        return $this->belongsTo(Layanan::class, 'layanan_id');
    }
    public function detailTransaksi() {
        return $this->hasOne(Transaksi::class, 'antrean_id');
    }
    public function ulasan() {
        return $this->hasOne(Ulasan::class, 'antrean_id');
    }

    /**
     * Potong stok bahan / barang yang terhubung ke layanan ini secara otomatis saat pesanan dibuat.
     */
    public function potongStokBahan(): bool
    {
        $layanan = $this->layanan ?? Layanan::find($this->layanan_id);
        if (!$layanan || empty($layanan->barang_id)) {
            return false;
        }

        $barang = Barang::find($layanan->barang_id);
        if (!$barang) {
            return false;
        }

        $qtyDibutuhkan = max(1, (int)($layanan->jumlah_pemakaian_barang ?? 1));
        $stokAkhir = max(0, (int)$barang->stok_sekarang - $qtyDibutuhkan);
        $barang->update(['stok_sekarang' => $stokAkhir]);

        try {
            LogStok::create([
                'barang_id' => $barang->id,
                'pencatat_id' => auth()->id() ?? ($this->pelanggan_id ?? 1),
                'referensi_transaksi_id' => null,
                'tipe_log' => 'KeluarManual',
                'qty_perubahan' => -$qtyDibutuhkan,
                'stok_akhir' => $stokAkhir,
                'keterangan' => 'Pemakaian bahan otomatis layanan ' . $layanan->nama_layanan . ' (Antrean #' . $this->nomor_antrean . ')'
            ]);
        } catch (\Throwable $e) {}

        // Sinkronisasi pembaruan stok ke Google Spreadsheet
        try {
            app(\App\Services\GoogleSheetsService::class)->updateRow('barang', $barang->id, [
                'stok_sekarang' => $stokAkhir,
                'updated_at' => now()->toDateTimeString(),
            ]);
        } catch (\Throwable $e) {}

        return true;
    }

    /**
     * Kembalikan stok bahan / barang jika antrean dibatalkan.
     */
    public function kembalikanStokBahan(): bool
    {
        $layanan = $this->layanan ?? Layanan::find($this->layanan_id);
        if (!$layanan || empty($layanan->barang_id)) {
            return false;
        }

        $barang = Barang::find($layanan->barang_id);
        if (!$barang) {
            return false;
        }

        $qtyDibutuhkan = max(1, (int)($layanan->jumlah_pemakaian_barang ?? 1));
        $stokAkhir = (int)$barang->stok_sekarang + $qtyDibutuhkan;
        $barang->update(['stok_sekarang' => $stokAkhir]);

        try {
            LogStok::create([
                'barang_id' => $barang->id,
                'pencatat_id' => auth()->id() ?? 1,
                'referensi_transaksi_id' => null,
                'tipe_log' => 'Masuk',
                'qty_perubahan' => $qtyDibutuhkan,
                'stok_akhir' => $stokAkhir,
                'keterangan' => 'Pengembalian bahan pembatalan antrean #' . $this->nomor_antrean . ' (' . $layanan->nama_layanan . ')'
            ]);
        } catch (\Throwable $e) {}

        // Sinkronisasi pembaruan stok ke Google Spreadsheet
        try {
            app(\App\Services\GoogleSheetsService::class)->updateRow('barang', $barang->id, [
                'stok_sekarang' => $stokAkhir,
                'updated_at' => now()->toDateTimeString(),
            ]);
        } catch (\Throwable $e) {}

        return true;
    }

    /**
     * Hitung sisa antrean yang berada di depan antrean ini pada hari yang sama.
     */
    public function getSisaAntreanDiDepanAttribute(): int
    {
        if ($this->status !== 'Menunggu') {
            return 0;
        }

        return self::where('status', 'Menunggu')
            ->when($this->kapster_id, function($q) {
                $q->where('kapster_id', $this->kapster_id);
            })
            ->whereDate('tanggal', $this->tanggal)
            ->where('created_at', '<', $this->created_at)
            ->count();
    }

    /**
     * Hitung estimasi waktu tunggu (dalam menit) untuk antrean spesifik ini.
     */
    public function getEstimasiTungguMenitAttribute(): int
    {
        if ($this->status === 'Diproses') {
            return 0;
        }

        if ($this->status !== 'Menunggu') {
            return 0;
        }

        $sisaDiDepan = $this->sisa_antrean_di_depan;
        if ($this->kapster_id) {
            return max(5, ($sisaDiDepan + 1) * 30);
        }

        $kapsterCount = max(1, User::whereHas('roles', fn($q) => $q->where('name', 'Kapster'))->count());
        $turns = ceil(($sisaDiDepan + 1) / $kapsterCount);

        return (int) max(5, $turns * 30);
    }

    /**
     * Hitung estimasi waktu tunggu umum toko (dalam menit) khusus hari yang ditentukan (default hari ini).
     */
    public static function hitungEwtToko(?string $tanggal = null): int
    {
        $today = $tanggal ?? \Carbon\Carbon::today()->toDateString();
        $menungguCount = self::where('status', 'Menunggu')
            ->whereDate('tanggal', $today)
            ->count();

        if ($menungguCount === 0) {
            $isProcessing = self::where('status', 'Diproses')
                ->whereDate('tanggal', $today)
                ->exists();
            return $isProcessing ? 15 : 0;
        }

        $kapsterCount = max(1, User::whereHas('roles', fn($q) => $q->where('name', 'Kapster'))->count());
        $turns = ceil(($menungguCount + 1) / $kapsterCount);

        return (int) ($turns * 30);
    }

    /**
     * Hitung estimasi waktu tunggu untuk kapster spesifik (dalam menit) khusus hari yang ditentukan (default hari ini).
     */
    public static function hitungEwtKapster($kapsterId, ?string $tanggal = null): int
    {
        $today = $tanggal ?? \Carbon\Carbon::today()->toDateString();
        $menungguCount = self::where('status', 'Menunggu')
            ->where('kapster_id', $kapsterId)
            ->whereDate('tanggal', $today)
            ->count();

        $isProcessing = self::where('status', 'Diproses')
            ->where('kapster_id', $kapsterId)
            ->whereDate('tanggal', $today)
            ->exists();

        if ($menungguCount === 0) {
            return $isProcessing ? 15 : 0;
        }

        return (int) ($menungguCount * 30 + ($isProcessing ? 15 : 0));
    }

    /**
     * Dapatkan daftar rentang jadwal yang sudah terisi (booked) pada tanggal & kapster tertentu.
     * Mengembalikan list array ['id', 'nomor_antrean', 'start', 'end', 'durasi', 'layanan', 'kapster_id', 'kapster_nama']
     */
    public static function getBookedSlots(?string $tanggal, $kapsterId = null, $excludeId = null): array
    {
        if (empty($tanggal)) {
            return [];
        }

        $query = self::with(['layanan', 'kapster'])
            ->whereDate('tanggal', $tanggal)
            ->where('status', '!=', 'Batal')
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId));

        if (!empty($kapsterId) && $kapsterId !== 'any') {
            $query->where('kapster_id', $kapsterId);
        }

        $bookings = $query->orderBy('waktu_estimasi_mulai')->get();

        $slots = [];
        foreach ($bookings as $b) {
            if (empty($b->waktu_estimasi_mulai)) {
                continue;
            }

            $rawTime = trim($b->waktu_estimasi_mulai);
            $parts = explode(':', $rawTime);
            if (count($parts) < 2) {
                continue;
            }
            $startTime = sprintf('%02d:%02d', (int)$parts[0], (int)$parts[1]);

            $durasi = $b->layanan ? (int)$b->layanan->estimasi_durasi_menit : 30;
            if ($durasi < 1) {
                $durasi = 30;
            }

            try {
                $startCarbon = \Carbon\Carbon::createFromFormat('H:i', $startTime);
                $endTime = $startCarbon->copy()->addMinutes($durasi)->format('H:i');
            } catch (\Throwable $e) {
                continue;
            }

            $slots[] = [
                'id' => $b->id,
                'nomor_antrean' => $b->nomor_antrean,
                'start' => $startTime,
                'end' => $endTime,
                'durasi' => $durasi,
                'layanan' => $b->layanan ? $b->layanan->nama_layanan : 'Layanan Cukur',
                'kapster_id' => $b->kapster_id,
                'kapster_nama' => $b->kapster ? $b->kapster->nama : 'Kapster',
            ];
        }

        return $slots;
    }

    /**
     * Periksa apakah waktu mulai dan durasi yang dipilih bertabrakan (overlap) dengan jadwal lain.
     * Mengembalikan null jika waktu TERSEDIA, atau array detail bentrok jika TIDAK TERSEDIA.
     */
    public static function checkConflict(?string $tanggal, ?string $startTime, int $durasiMenit = 30, $kapsterId = null, $excludeId = null): ?array
    {
        if (empty($tanggal) || empty($startTime)) {
            return null;
        }

        $rawTime = trim($startTime);
        $parts = explode(':', $rawTime);
        if (count($parts) < 2) {
            return [
                'conflict' => true,
                'slot' => null,
                'message' => 'Format jam tidak valid (contoh: 13:00).'
            ];
        }
        $formattedStart = sprintf('%02d:%02d', (int)$parts[0], (int)$parts[1]);
        $durasiMenit = max(1, (int)$durasiMenit);

        try {
            $pStart = \Carbon\Carbon::createFromFormat('H:i', $formattedStart);
            $pEnd = $pStart->copy()->addMinutes($durasiMenit);
        } catch (\Throwable $e) {
            return [
                'conflict' => true,
                'slot' => null,
                'message' => 'Format jam tidak valid.'
            ];
        }

        $allKapsters = User::where('role', 'Kapster')
            ->orWhereHas('roles', fn($q) => $q->where('name', 'Kapster'))
            ->get();

        // 1. Jika kapster tertentu dipilih
        if (!empty($kapsterId) && $kapsterId !== 'any') {
            $slots = self::getBookedSlots($tanggal, $kapsterId, $excludeId);
            foreach ($slots as $slot) {
                try {
                    $eStart = \Carbon\Carbon::createFromFormat('H:i', $slot['start']);
                    $eEnd = \Carbon\Carbon::createFromFormat('H:i', $slot['end']);

                    // Overlap: proposed start < existing end AND proposed end > existing start
                    if ($pStart->lt($eEnd) && $pEnd->gt($eStart)) {
                        return [
                            'conflict' => true,
                            'slot' => $slot,
                            'message' => "Jadwal {$formattedStart} - {$pEnd->format('H:i')} bertabrakan dengan antrean {$slot['kapster_nama']} pada pukul {$slot['start']} - {$slot['end']} ({$slot['layanan']})."
                        ];
                    }
                } catch (\Throwable $e) {}
            }
            return null;
        }

        // 2. Jika 'any' / Bebas Kapster: cek apakah SEMUA kapster sibuk pada rentang waktu ini
        $availableKapsterFound = false;
        foreach ($allKapsters as $k) {
            $kSlots = self::getBookedSlots($tanggal, $k->id, $excludeId);
            $kBusy = false;
            foreach ($kSlots as $slot) {
                try {
                    $eStart = \Carbon\Carbon::createFromFormat('H:i', $slot['start']);
                    $eEnd = \Carbon\Carbon::createFromFormat('H:i', $slot['end']);
                    if ($pStart->lt($eEnd) && $pEnd->gt($eStart)) {
                        $kBusy = true;
                        break;
                    }
                } catch (\Throwable $e) {}
            }
            if (!$kBusy) {
                $availableKapsterFound = true;
                break;
            }
        }

        if (!$availableKapsterFound && $allKapsters->count() > 0) {
            return [
                'conflict' => true,
                'slot' => null,
                'message' => "Seluruh kapster sedang penuh pada rentang waktu {$formattedStart} - {$pEnd->format('H:i')}. Silakan pilih jam lain."
            ];
        }

        return null;
    }

    /**
     * Cari kapster yang jadwalnya kosong pada tanggal & rentang waktu yang dipilih.
     */
    public static function findAvailableKapster(?string $tanggal, ?string $startTime, int $durasiMenit = 30, $excludeId = null): ?int
    {
        $allKapsters = User::where('role', 'Kapster')
            ->orWhereHas('roles', fn($q) => $q->where('name', 'Kapster'))
            ->get();

        if ($allKapsters->isEmpty()) {
            return 1;
        }

        if (empty($tanggal) || empty($startTime)) {
            return $allKapsters->first()->id;
        }

        $rawTime = trim($startTime);
        $parts = explode(':', $rawTime);
        if (count($parts) < 2) {
            return $allKapsters->first()->id;
        }
        $formattedStart = sprintf('%02d:%02d', (int)$parts[0], (int)$parts[1]);

        try {
            $pStart = \Carbon\Carbon::createFromFormat('H:i', $formattedStart);
            $pEnd = $pStart->copy()->addMinutes(max(1, $durasiMenit));
        } catch (\Throwable $e) {
            return $allKapsters->first()->id;
        }

        foreach ($allKapsters as $k) {
            $kSlots = self::getBookedSlots($tanggal, $k->id, $excludeId);
            $isBusy = false;
            foreach ($kSlots as $slot) {
                try {
                    $eStart = \Carbon\Carbon::createFromFormat('H:i', $slot['start']);
                    $eEnd = \Carbon\Carbon::createFromFormat('H:i', $slot['end']);
                    if ($pStart->lt($eEnd) && $pEnd->gt($eStart)) {
                        $isBusy = true;
                        break;
                    }
                } catch (\Throwable $e) {}
            }
            if (!$isBusy) {
                return $k->id;
            }
        }

        return $allKapsters->first()->id;
    }
}
