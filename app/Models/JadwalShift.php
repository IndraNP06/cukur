<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JadwalShift extends Model
{
    use HasFactory;
    protected $table = 'jadwal_shift';
    protected $fillable = ['kapster_id', 'tanggal', 'jam_mulai', 'jam_selesai', 'status'];

    public function kapster() {
        return $this->belongsTo(User::class, 'kapster_id');
    }
}
