<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogStok extends Model
{
    use HasFactory;
    protected $table = 'log_stok';
    protected $fillable = ['barang_id', 'pencatat_id', 'referensi_transaksi_id', 'tipe_log', 'qty_perubahan', 'stok_akhir', 'keterangan'];

    public function barang() {
        return $this->belongsTo(Barang::class, 'barang_id');
    }
    public function pencatat() {
        return $this->belongsTo(User::class, 'pencatat_id');
    }
}
