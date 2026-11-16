<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetailTransaksi extends Model
{
    use HasFactory;
    protected $table = 'detail_transaksi';
    protected $fillable = ['transaksi_id', 'tipe_item', 'item_id', 'nama_item_snapshot', 'qty', 'harga_satuan_snapshot', 'subtotal'];

    public function transaksi() {
        return $this->belongsTo(Transaksi::class, 'transaksi_id');
    }
}
