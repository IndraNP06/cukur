<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GaleriSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('galeris')->insert([
            ['judul' => 'French Crop Style', 'gambar_url' => '/images/gallery/french-crop.jpg', 'tipe' => 'Rambut', 'created_at' => now(), 'updated_at' => now()],
            ['judul' => 'Interior Nyaman', 'gambar_url' => '/images/gallery/interior.jpg', 'tipe' => 'Interior', 'created_at' => now(), 'updated_at' => now()]
        ]);
    }
}
