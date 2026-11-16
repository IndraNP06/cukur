<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LayananSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('layanan')->insert([
            ['nama_layanan' => 'Gentleman Haircut', 'deskripsi' => 'Potong rambut dewasa standard', 'harga' => 50000, 'estimasi_durasi_menit' => 45, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nama_layanan' => 'Kids Haircut', 'deskripsi' => 'Potong rambut anak-anak', 'harga' => 40000, 'estimasi_durasi_menit' => 30, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]
        ]);
    }
}
