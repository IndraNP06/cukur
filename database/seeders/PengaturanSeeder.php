<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PengaturanSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('pengaturans')->insert([
            ['key' => 'app_name', 'value' => 'Ran Cukurin', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'contact_whatsapp', 'value' => '081234567890', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'address', 'value' => 'Jl. Contoh Alamat Barbershop', 'created_at' => now(), 'updated_at' => now()]
        ]);
    }
}
