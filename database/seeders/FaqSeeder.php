<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('faqs')->insert([
            ['pertanyaan' => 'Apakah harus booking dulu?', 'jawaban' => 'Sangat disarankan agar tidak menunggu lama.', 'urutan' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['pertanyaan' => 'Apakah bisa bayar pakai QRIS?', 'jawaban' => 'Ya, kami menerima pembayaran via QRIS.', 'urutan' => 2, 'created_at' => now(), 'updated_at' => now()]
        ]);
    }
}
