<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('pengguna')->insert([
            ['nama' => 'Andi Kapster', 'email' => 'andi@rancukurin.com', 'password' => Hash::make('password'), 'no_hp' => '081234567892', 'role' => 'Kapster', 'created_at' => now(), 'updated_at' => now()],
            ['nama' => 'Pelanggan Satu', 'email' => 'pelanggan@rancukurin.com', 'password' => Hash::make('password'), 'no_hp' => '081234567893', 'role' => 'Pelanggan', 'created_at' => now(), 'updated_at' => now()]
        ]);
    }
}
