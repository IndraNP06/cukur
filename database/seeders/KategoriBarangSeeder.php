<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KategoriBarangSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('kategori_barangs')->insert([
            ['nama_kategori' => 'Pomade', 'deskripsi' => 'Minyak rambut pria', 'created_at' => now(), 'updated_at' => now()],
            ['nama_kategori' => 'Hair Care', 'deskripsi' => 'Perawatan rambut (Shampoo, Tonic)', 'created_at' => now(), 'updated_at' => now()]
        ]);
    }
}
