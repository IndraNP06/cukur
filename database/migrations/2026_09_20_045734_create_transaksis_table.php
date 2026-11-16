<?php
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
            $table->decimal('subtotal', 15, 2);
            $table->decimal('total_diskon', 15, 2)->default(0);
            $table->decimal('total_akhir', 15, 2);
            $table->enum('metode_pembayaran', ['Cash', 'QRIS', 'Transfer']);
            $table->enum('status_bayar', ['BelumLunas', 'Lunas'])->default('BelumLunas');
            $table->timestamp('tanggal_bayar')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void {
        Schema::dropIfExists('transaksi');
    }
};
