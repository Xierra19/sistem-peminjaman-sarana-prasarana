<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItemSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('items')->insert([
            [
                'id' => 1,
                'code' => '01',
                'name' => 'Speaker',
                'category' => 'Milik Kampus Bekasi',
                'quantity' => 2,
                'is_available' => 1,
                'created_at' => '2026-01-30 01:49:49',
                'updated_at' => '2026-04-05 23:56:28',
            ],
            [
                'id' => 2,
                'code' => '02',
                'name' => 'Meja',
                'category' => 'Tidak Habis Pakai',
                'quantity' => 10,
                'is_available' => 1,
                'created_at' => '2026-04-06 20:19:57',
                'updated_at' => '2026-04-06 20:19:57',
            ],
            [
                'id' => 3,
                'code' => '03',
                'name' => 'Bangku',
                'category' => 'Tidak Habis Pakai',
                'quantity' => 50,
                'is_available' => 1,
                'created_at' => '2026-04-06 20:20:17',
                'updated_at' => '2026-04-06 20:20:17',
            ],
            [
                'id' => 4,
                'code' => '04',
                'name' => 'Papan Tulis',
                'category' => 'Tidak Habis Pakai',
                'quantity' => 6,
                'is_available' => 1,
                'created_at' => '2026-04-06 20:20:49',
                'updated_at' => '2026-04-06 20:20:49',
            ],
            [
                'id' => 5,
                'code' => '05',
                'name' => 'Mic',
                'category' => 'Tidak Habis Pakai',
                'quantity' => 2,
                'is_available' => 1,
                'created_at' => '2026-04-06 20:21:15',
                'updated_at' => '2026-04-06 20:21:15',
            ],
        ]);
    }
}

