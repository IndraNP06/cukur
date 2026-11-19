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
}
