<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Buat akun Admin
        User::create([
            'name' => 'ADMIN Leo Saputra Marhadi',
            'email' => 'leomarhadi13@gmail.com',
            'phone' => '081234567890',
            'password' => Hash::make('mamaratu20'),
            'role' => 'admin',
        ]);

        // Buat akun User Biasa
        User::create([
            'name' => 'User Leo',
            'email' => 'leomarhadi19@gmail.com',
            'phone' => '081234567891',
            'password' => Hash::make('mamaratu20'),
            'role' => 'user',
        ]);
    }
}
