<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin - User yang diminta
        $superAdmin = User::updateOrCreate(
            ['email' => 'gorengan@kitabill.site'],
            [
            'name' => 'Super Admin',
                'password' => Hash::make('gorengan123'),
            'phone' => '081234567890',
            'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        
        // Coba assign role dengan berbagai kemungkinan nama role
        try {
        $superAdmin->assignRole('super_admin');
        } catch (\Exception $e) {
            try {
                $superAdmin->assignRole('Super Admin');
            } catch (\Exception $e2) {
                // Jika role belum ada, akan dibuat saat RolePermissionSeeder dijalankan
            }
        }

        // Admin
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin.user@ispmanager.test',
            'password' => Hash::make('password'),
            'phone' => '081234567891',
            'status' => 'active',
        ]);
        $admin->assignRole('admin');

        // CS
        $cs = User::create([
            'name' => 'CS User',
            'email' => 'cs@ispmanager.test',
            'password' => Hash::make('password'),
            'phone' => '081234567892',
            'status' => 'active',
        ]);
        $cs->assignRole('cs');

        // Teknisi
        $teknisi = User::create([
            'name' => 'Teknisi User',
            'email' => 'teknisi@ispmanager.test',
            'password' => Hash::make('password'),
            'phone' => '081234567893',
            'status' => 'active',
        ]);
        $teknisi->assignRole('teknisi');
    }
}
