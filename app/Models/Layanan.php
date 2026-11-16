<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Layanan extends Model
{
    use HasFactory;
    protected $table = 'layanan';
    protected $fillable = ['nama_layanan', 'deskripsi', 'harga', 'estimasi_durasi_menit', 'is_active', 'gambar'];

    public function getGambarUrlAttribute(): string
    {
        if (!empty($this->gambar)) {
            if (str_starts_with($this->gambar, 'http://') || str_starts_with($this->gambar, 'https://')) {
                return $this->gambar;
            }
            return asset(ltrim($this->gambar, '/'));
        }

        return asset('images/layanan/default.jpg');
    }
}
