<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BarangSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('barang')->insert([
            ['nama_barang' => 'Suavecito Firme Hold', 'tipe' => 'ProdukJual', 'stok_sekarang' => 20, 'batas_minimum' => 5, 'harga_beli' => 120000, 'harga_jual' => 150000, 'created_at' => now(), 'updated_at' => now()],
            ['nama_barang' => 'Hair Tonic Salon', 'tipe' => 'BahanBaku', 'stok_sekarang' => 5, 'batas_minimum' => 1, 'harga_beli' => 50000, 'harga_jual' => 0, 'created_at' => now(), 'updated_at' => now()]
        ]);
    }
}
