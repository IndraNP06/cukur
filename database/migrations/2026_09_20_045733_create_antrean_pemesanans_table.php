<?php
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
            $table->softDeletes();
        });
    }
    public function down(): void {
        Schema::dropIfExists('antrean_pemesanan');
    }
};
