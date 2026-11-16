<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PengeluaranSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('pengeluarans')->insert([
            ['tanggal' => now()->toDateString(), 'keterangan' => 'Bayar Listrik', 'nominal' => 200000, 'created_at' => now(), 'updated_at' => now()]
        ]);
    }
}
