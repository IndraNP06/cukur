<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JadwalShiftSeeder extends Seeder
{
    public function run(): void
    {
        $kapsterId = DB::table('pengguna')->where('role', 'Kapster')->value('id');
        if ($kapsterId) {
            DB::table('jadwal_shift')->insert([
                ['kapster_id' => $kapsterId, 'tanggal' => now()->toDateString(), 'jam_mulai' => '09:00:00', 'jam_selesai' => '17:00:00', 'status' => 'Hadir']
            ]);
        }
    }
}
