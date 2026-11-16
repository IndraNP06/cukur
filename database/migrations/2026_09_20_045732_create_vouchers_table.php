<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('voucher', function (Blueprint $table) {
            $table->id();
            $table->string('kode_voucher')->unique();
            $table->enum('tipe_diskon', ['Persen', 'Nominal']);
            $table->decimal('nilai_diskon', 15, 2);
            $table->integer('kuota_maksimal');
            $table->integer('kuota_terpakai')->default(0);
            $table->date('tgl_mulai');
            $table->date('tgl_berakhir');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void {
        Schema::dropIfExists('voucher');
    }
};
