<?php
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
