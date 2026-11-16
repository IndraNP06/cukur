<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Barang extends Model
{
    use HasFactory;
    protected $table = 'barang';
    protected $fillable = ['nama_barang', 'tipe', 'stok_sekarang', 'batas_minimum', 'harga_beli', 'harga_jual', 'gambar'];

    public function getGambarUrlAttribute(): string
    {
        if (!empty($this->gambar)) {
            if (str_starts_with($this->gambar, 'http://') || str_starts_with($this->gambar, 'https://')) {
                return $this->gambar;
            }
            return asset(ltrim($this->gambar, '/'));
        }

        return asset('images/barang/default.jpg');
    }
}
