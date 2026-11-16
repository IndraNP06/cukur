<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaksi extends Model
{
    use HasFactory;
    protected $table = 'transaksi';
    protected $fillable = ['antrean_id', 'kasir_id', 'voucher_id', 'subtotal', 'total_diskon', 'total_akhir', 'metode_pembayaran', 'status_bayar', 'tanggal_bayar'];

    public function antrean() {
        return $this->belongsTo(AntreanPemesanan::class, 'antrean_id');
    }
    public function kasir() {
        return $this->belongsTo(User::class, 'kasir_id');
    }
    public function detailTransaksi() {
        return $this->hasMany(DetailTransaksi::class, 'transaksi_id');
    }
}
