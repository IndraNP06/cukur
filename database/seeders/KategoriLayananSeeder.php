<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KategoriLayananSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('kategori_layanans')->insert([
            ['nama_kategori' => 'Haircut', 'deskripsi' => 'Potong rambut pria', 'created_at' => now(), 'updated_at' => now()],
            ['nama_kategori' => 'Treatment', 'deskripsi' => 'Perawatan rambut & kulit kepala', 'created_at' => now(), 'updated_at' => now()]
        ]);
    }
}
