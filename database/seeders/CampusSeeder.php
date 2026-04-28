<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CampusSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('campuses')->insert([
            [
                'id' => 4,
                'name' => 'Kampus Bekasi',
                'address' => 'Jl. Harapan Indah',
                'phone' => '081212121212',
                'created_at' => '2025-10-05 02:27:20',
                'updated_at' => '2025-10-05 02:27:20',
            ],
        ]);
    }
}

