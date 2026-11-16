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

    /**
     * Hitung sisa antrean yang berada di depan antrean ini.
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
            ->where(function($q) {
                $q->where('tanggal', '<', $this->tanggal)
                  ->orWhere(function($q2) {
                      $q2->where('tanggal', $this->tanggal)
                         ->where('created_at', '<', $this->created_at);
                  });
            })
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
     * Hitung estimasi waktu tunggu umum toko (dalam menit).
     */
    public static function hitungEwtToko(): int
    {
        $today = \Carbon\Carbon::today()->toDateString();
        $menungguCount = self::where('status', 'Menunggu')
            ->whereDate('tanggal', $today)
            ->count();

        if ($menungguCount === 0) {
            $menungguCount = self::where('status', 'Menunggu')->count();
        }

        if ($menungguCount === 0) {
            return 0;
        }

        $kapsterCount = max(1, User::whereHas('roles', fn($q) => $q->where('name', 'Kapster'))->count());
        $turns = ceil(($menungguCount + 1) / $kapsterCount);

        return (int) ($turns * 30);
    }

    /**
     * Hitung estimasi waktu tunggu untuk kapster spesifik (dalam menit).
     */
    public static function hitungEwtKapster($kapsterId): int
    {
        $today = \Carbon\Carbon::today()->toDateString();
        $menungguCount = self::where('status', 'Menunggu')
            ->where('kapster_id', $kapsterId)
            ->whereDate('tanggal', $today)
            ->count();

        if ($menungguCount === 0) {
            $menungguCount = self::where('status', 'Menunggu')
                ->where('kapster_id', $kapsterId)
                ->count();
        }

        $isProcessing = self::where('status', 'Diproses')
            ->where('kapster_id', $kapsterId)
            ->exists();

        if ($menungguCount === 0) {
            return $isProcessing ? 15 : 0;
        }

        return (int) ($menungguCount * 30 + ($isProcessing ? 15 : 0));
    }
}
