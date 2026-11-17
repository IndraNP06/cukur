<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('jadwal_shift', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kapster_id')->constrained('pengguna')->onDelete('cascade');
            $table->date('tanggal');
            $table->time('jam_mulai')->nullable();
            $table->time('jam_selesai')->nullable();
            $table->enum('status', ['Hadir', 'Cuti', 'Libur'])->default('Hadir');
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('jadwal_shift');
    }
};
