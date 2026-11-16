<?php
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
