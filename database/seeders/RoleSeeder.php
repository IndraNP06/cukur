<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define roles based on ERD enum: 'Pelanggan', 'Kapster', 'Pemilik'
        $roles = [
            'Pelanggan',
            'Kapster',
            'Pemilik'
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        // Create an initial admin/pemilik user
        $admin = User::firstOrCreate([
            'email' => 'admin@rancukurin.com'
        ], [
            'nama' => 'Pemilik Ran Cukurin',
            'password' => bcrypt('password'), // default password for testing
            'no_hp' => '081234567890',
            'role' => 'Pemilik'
        ]);

        if (!$admin->hasRole('Pemilik')) {
            $admin->assignRole('Pemilik');
        }
    }
}
