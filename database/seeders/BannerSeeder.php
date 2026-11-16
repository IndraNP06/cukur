<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BannerSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('banners')->insert([
            ['judul' => 'Promo Lebaran', 'gambar_url' => '/images/banners/promo-lebaran.jpg', 'urutan' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]
        ]);
    }
}
