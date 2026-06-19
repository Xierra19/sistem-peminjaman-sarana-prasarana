<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('users')->insert([
            [
                'id' => 3,
                'name' => 'Leo',
                'nim' => '20220801005',
                'email' => 'leomarhadi13@student.esaunggul.ac.id',
                'phone' => '087851327550',
                'role' => 'user',
                'email_verified_at' => '2025-11-14 22:53:04',
                'password' => '$2y$12$FtkWQuzuFIfBowLaD3nWTu1PKIoz.ytEE8rVai9OjmMsk6Do1tTSa',
                'remember_token' => null,
                'created_at' => '2025-11-14 22:51:36',
                'updated_at' => '2025-11-14 22:53:04',
            ],
            [
                'id' => 4,
                'name' => 'ADMIN SARPRAS',
                'nim' => null,
                'email' => 'screamyd19@gmail.com',
                'phone' => null,
                'role' => 'admin_sarpras',
                'email_verified_at' => '2026-04-17 06:44:54',
                'password' => '$2y$12$r0ZsGA2hB74oIbtwh0KrDO/UoVuqSGblsUkfKwvn/zo5Pxr4zHFSu',
                'remember_token' => 'eQ06z0lHMFNFjqoQETwHwf6U7RIkCpEGVl7jPX1XY1AzlwbQUhC12e1fZcH6',
                'created_at' => '2026-04-17 06:45:00',
                'updated_at' => '2026-04-17 00:20:27',
            ],
        ]);
    }
}
