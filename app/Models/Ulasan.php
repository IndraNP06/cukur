<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ulasan extends Model
{
    use HasFactory;
    protected $table = 'ulasan';
    protected $fillable = ['antrean_id', 'pelanggan_id', 'kapster_id', 'rating', 'komentar'];

    public function antrean() {
        return $this->belongsTo(AntreanPemesanan::class, 'antrean_id');
    }
    public function pelanggan() {
        return $this->belongsTo(User::class, 'pelanggan_id');
    }
    public function kapster() {
        return $this->belongsTo(User::class, 'kapster_id');
    }

    public function getUlasanAttribute() {
        return $this->komentar;
    }
}
