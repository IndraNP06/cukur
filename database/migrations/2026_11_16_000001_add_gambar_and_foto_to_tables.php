<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('layanan', function (Blueprint $table) {
            if (!Schema::hasColumn('layanan', 'gambar')) {
                $table->string('gambar')->nullable()->after('deskripsi');
            }
        });

        Schema::table('pengguna', function (Blueprint $table) {
            if (!Schema::hasColumn('pengguna', 'foto')) {
                $table->string('foto')->nullable()->after('no_hp');
            }
        });

        Schema::table('barang', function (Blueprint $table) {
            if (!Schema::hasColumn('barang', 'gambar')) {
                $table->string('gambar')->nullable()->after('tipe');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('layanan', function (Blueprint $table) {
            if (Schema::hasColumn('layanan', 'gambar')) {
                $table->dropColumn('gambar');
            }
        });

        Schema::table('pengguna', function (Blueprint $table) {
            if (Schema::hasColumn('pengguna', 'foto')) {
                $table->dropColumn('foto');
            }
        });

        Schema::table('barang', function (Blueprint $table) {
            if (Schema::hasColumn('barang', 'gambar')) {
                $table->dropColumn('gambar');
            }
        });
    }
};
