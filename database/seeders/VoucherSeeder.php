<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VoucherSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('voucher')->insert([
            ['kode_voucher' => 'NEWCUST10', 'tipe_diskon' => 'Persen', 'nilai_diskon' => 10, 'kuota_maksimal' => 100, 'kuota_terpakai' => 0, 'tgl_mulai' => now()->toDateString(), 'tgl_berakhir' => now()->addDays(30)->toDateString(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]
        ]);
    }
}
