<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasFactory;
    protected $table = 'voucher';
    protected $fillable = ['kode_voucher', 'tipe_diskon', 'nilai_diskon', 'kuota_maksimal', 'kuota_terpakai', 'tgl_mulai', 'tgl_berakhir', 'is_active'];
}
