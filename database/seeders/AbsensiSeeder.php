<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AbsensiSeeder extends Seeder
{
    public function run(): void
    {
        $kapsterId = DB::table('pengguna')->where('role', 'Kapster')->value('id');
        if ($kapsterId) {
            DB::table('absensis')->insert([
                ['user_id' => $kapsterId, 'tanggal' => now()->toDateString(), 'status' => 'Hadir', 'created_at' => now(), 'updated_at' => now()]
            ]);
        }
    }
}
