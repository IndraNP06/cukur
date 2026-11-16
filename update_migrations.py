import os
import glob

migrations_dir = r'C:\\Users\\Indra\\OneDrive\\Desktop\\skripsi1\\ran_cukurin_app\\database\\migrations\\'

schemas = {
    'create_users_table.php': '''<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('pengguna', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('no_hp')->nullable();
            $table->enum('role', ['Pelanggan', 'Kapster', 'Kasir', 'Pemilik']);
            $table->timestamps();
        });
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }
    public function down(): void {
        Schema::dropIfExists('pengguna');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
''',
    'create_jadwal_shifts_table.php': '''<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('jadwal_shift', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kapster_id')->constrained('pengguna')->onDelete('cascade');
            $table->date('tanggal');
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->enum('status', ['Hadir', 'Cuti', 'Libur'])->default('Hadir');
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('jadwal_shift');
    }
};
''',
    'create_layanans_table.php': '''<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('layanan', function (Blueprint $table) {
            $table->id();
            $table->string('nama_layanan');
            $table->text('deskripsi')->nullable();
            $table->decimal('harga', 10, 2);
            $table->integer('estimasi_durasi_menit');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('layanan');
    }
};
''',
    'create_barangs_table.php': '''<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('barang', function (Blueprint $table) {
            $table->id();
            $table->string('nama_barang');
            $table->enum('tipe', ['BahanBaku', 'ProdukJual']);
            $table->integer('stok_sekarang')->default(0);
            $table->integer('batas_minimum')->default(0);
            $table->decimal('harga_beli', 10, 2)->default(0);
            $table->decimal('harga_jual', 10, 2)->default(0);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('barang');
    }
};
''',
    'create_vouchers_table.php': '''<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('voucher', function (Blueprint $table) {
            $table->id();
            $table->string('kode_voucher')->unique();
            $table->enum('tipe_diskon', ['Persen', 'Nominal']);
            $table->decimal('nilai_diskon', 10, 2);
            $table->integer('kuota_maksimal');
            $table->integer('kuota_terpakai')->default(0);
            $table->date('tgl_mulai');
            $table->date('tgl_berakhir');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('voucher');
    }
};
''',
    'create_antrean_pemesanans_table.php': '''<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('antrean_pemesanan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pelanggan_id')->constrained('pengguna')->onDelete('cascade');
            $table->foreignId('kapster_id')->constrained('pengguna')->onDelete('cascade');
            $table->foreignId('layanan_id')->constrained('layanan')->onDelete('cascade');
            $table->enum('tipe_antrean', ['Online', 'WalkIn']);
            $table->string('nomor_antrean')->nullable();
            $table->date('tanggal');
            $table->time('waktu_estimasi_mulai');
            $table->time('waktu_mulai_aktual')->nullable();
            $table->time('waktu_selesai_aktual')->nullable();
            $table->enum('status', ['Menunggu', 'Diproses', 'Selesai', 'Batal'])->default('Menunggu');
            $table->text('alasan_batal')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('antrean_pemesanan');
    }
};
''',
    'create_ulasans_table.php': '''<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('ulasan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('antrean_id')->constrained('antrean_pemesanan')->onDelete('cascade');
            $table->foreignId('pelanggan_id')->constrained('pengguna')->onDelete('cascade');
            $table->foreignId('kapster_id')->constrained('pengguna')->onDelete('cascade');
            $table->integer('rating');
            $table->text('komentar')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('ulasan');
    }
};
''',
    'create_transaksis_table.php': '''<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('transaksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('antrean_id')->constrained('antrean_pemesanan')->onDelete('cascade');
            $table->foreignId('kasir_id')->constrained('pengguna')->onDelete('cascade');
            $table->foreignId('voucher_id')->nullable()->constrained('voucher')->onDelete('set null');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('total_diskon', 10, 2)->default(0);
            $table->decimal('total_akhir', 10, 2);
            $table->enum('metode_pembayaran', ['Cash', 'QRIS', 'Transfer']);
            $table->enum('status_bayar', ['BelumLunas', 'Lunas'])->default('BelumLunas');
            $table->timestamp('tanggal_bayar')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('transaksi');
    }
};
''',
    'create_log_stoks_table.php': '''<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('log_stok', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_id')->constrained('barang')->onDelete('cascade');
            $table->foreignId('pencatat_id')->constrained('pengguna')->onDelete('cascade');
            $table->foreignId('referensi_transaksi_id')->nullable()->constrained('transaksi')->onDelete('cascade');
            $table->enum('tipe_log', ['Masuk', 'KeluarManual', 'OtomatisPOS', 'Opname']);
            $table->integer('qty_perubahan');
            $table->integer('stok_akhir');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('log_stok');
    }
};
''',
    'create_detail_transaksis_table.php': '''<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('detail_transaksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaksi_id')->constrained('transaksi')->onDelete('cascade');
            $table->enum('tipe_item', ['Layanan', 'Barang']);
            $table->unsignedBigInteger('item_id'); // Polymorphic
            $table->string('nama_item_snapshot');
            $table->integer('qty');
            $table->decimal('harga_satuan_snapshot', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('detail_transaksi');
    }
};
'''
}

files = glob.glob(migrations_dir + '*.php')
for f in files:
    filename = os.path.basename(f)
    for key in schemas.keys():
        if key in filename:
            with open(f, 'w', encoding='utf-8') as file:
                file.write(schemas[key])
            print(f"Updated {filename}")
