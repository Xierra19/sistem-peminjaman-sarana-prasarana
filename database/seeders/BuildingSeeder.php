<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BuildingSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('buildings')->insert([
            [
                'id' => 2,
                'campus_id' => 4,
                'name' => 'Gedung A',
                'created_at' => '2025-10-05 05:51:54',
                'updated_at' => '2025-10-05 07:43:13',
            ],
            [
                'id' => 15,
                'campus_id' => 4,
                'name' => 'Modular A',
                'created_at' => '2025-10-17 02:05:13',
                'updated_at' => '2025-10-17 02:05:13',
            ],
        ]);
    }
}

