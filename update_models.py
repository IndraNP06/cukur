import os

models_dir = 'C:/Users/Indra/OneDrive/Desktop/skripsi1/ran_cukurin_app/app/Models/'

models = {
    'User.php': r'''<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $table = 'pengguna';

    protected $fillable = [
        'nama',
        'email',
        'password',
        'no_hp',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
''',
    'JadwalShift.php': r'''<?php
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
''',
    'Layanan.php': r'''<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Layanan extends Model
{
    use HasFactory;
    protected $table = 'layanan';
    protected $fillable = ['nama_layanan', 'deskripsi', 'harga', 'estimasi_durasi_menit', 'is_active'];
}
''',
    'Barang.php': r'''<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Barang extends Model
{
    use HasFactory;
    protected $table = 'barang';
    protected $fillable = ['nama_barang', 'tipe', 'stok_sekarang', 'batas_minimum', 'harga_beli', 'harga_jual'];
}
''',
    'Voucher.php': r'''<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasFactory;
    protected $table = 'voucher';
    protected $fillable = ['kode_voucher', 'tipe_diskon', 'nilai_diskon', 'kuota_maksimal', 'kuota_terpakai', 'tgl_mulai', 'tgl_berakhir', 'is_active'];
}
''',
    'AntreanPemesanan.php': r'''<?php
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
}
''',
    'Ulasan.php': r'''<?php
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
}
''',
    'Transaksi.php': r'''<?php
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
    public function detail() {
        return $this->hasMany(DetailTransaksi::class, 'transaksi_id');
    }
}
''',
    'DetailTransaksi.php': r'''<?php
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
''',
    'LogStok.php': r'''<?php
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
'''
}

for filename, content in models.items():
    filepath = os.path.join(models_dir, filename)
    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)
    print(f"Updated {filename}")
