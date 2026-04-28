<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('rooms')->insert([
            [
                'id' => 4,
                'building_id' => 2,
                'name' => 'KHIB101',
                'capacity' => 60,
                'features' => null,
                'is_available' => 1,
                'created_at' => '2025-10-05 07:43:59',
                'updated_at' => '2025-10-05 07:43:59',
            ],
            [
                'id' => 6,
                'building_id' => 2,
                'name' => 'KHIB102',
                'capacity' => 40,
                'features' => null,
                'is_available' => 1,
                'created_at' => '2025-10-16 09:13:40',
                'updated_at' => '2025-10-16 09:13:40',
            ],
            [
                'id' => 7,
                'building_id' => 15,
                'name' => 'KBM101',
                'capacity' => 50,
                'features' => null,
                'is_available' => 1,
                'created_at' => '2025-10-17 02:05:41',
                'updated_at' => '2025-10-17 02:05:41',
            ],
        ]);
    }
}

