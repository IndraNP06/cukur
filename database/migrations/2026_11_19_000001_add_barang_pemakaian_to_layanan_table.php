<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('layanan', function (Blueprint $table) {
            if (!Schema::hasColumn('layanan', 'barang_id')) {
                $table->foreignId('barang_id')->nullable()->after('harga')->constrained('barang')->nullOnDelete();
            }
            if (!Schema::hasColumn('layanan', 'jumlah_pemakaian_barang')) {
                $table->integer('jumlah_pemakaian_barang')->default(1)->after('barang_id');
            }
        });
    }

    public function down(): void {
        Schema::table('layanan', function (Blueprint $table) {
            if (Schema::hasColumn('layanan', 'barang_id')) {
                $table->dropColumn('barang_id');
            }
            if (Schema::hasColumn('layanan', 'jumlah_pemakaian_barang')) {
                $table->dropColumn('jumlah_pemakaian_barang');
            }
        });
    }
};
