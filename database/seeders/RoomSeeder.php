<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $timestamp = '2025-10-17 02:05:13';

        DB::table('rooms')->insert(array_map(
            fn (array $room): array => $room + [
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
            ['building_id' => 7, 'name' => 'KBA301', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA302', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA304', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA305', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA306', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA307', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA308', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA309', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA310', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA311', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA401', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA402', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA403', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA404', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA405', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA406', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA407', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA408', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA501', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA502', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA503', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA504', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA505', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA506', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA507', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA508', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA509', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA601', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA602', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA603', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA604', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA605', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA606', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA607', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA608', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA609', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA701', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA702', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA703', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA704', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA705', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA706', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA707', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA708', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA801', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA802', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA803', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA804', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA805', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 7, 'name' => 'KBA806', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 5, 'name' => 'KBM107', 'capacity' => 40, 'is_available' => 1],
            ['building_id' => 5, 'name' => 'KBM108', 'capacity' => 40, 'is_available' => 1],
            ['building_id' => 5, 'name' => 'KBM109', 'capacity' => 40, 'is_available' => 1],
            ['building_id' => 5, 'name' => 'KBM110', 'capacity' => 40, 'is_available' => 1],
            ['building_id' => 5, 'name' => 'KBM201', 'capacity' => 40, 'is_available' => 1],
            ['building_id' => 5, 'name' => 'KBM202', 'capacity' => 40, 'is_available' => 1],
            ['building_id' => 5, 'name' => 'KBM203', 'capacity' => 40, 'is_available' => 1],
            ['building_id' => 5, 'name' => 'KBM204', 'capacity' => 40, 'is_available' => 1],
            ['building_id' => 5, 'name' => 'KBM205', 'capacity' => 40, 'is_available' => 1],
            ['building_id' => 5, 'name' => 'KBM206', 'capacity' => 40, 'is_available' => 1],
            ['building_id' => 5, 'name' => 'KBM207', 'capacity' => 40, 'is_available' => 1],
            ['building_id' => 5, 'name' => 'KBM208', 'capacity' => 40, 'is_available' => 1],
            ['building_id' => 5, 'name' => 'KBM209', 'capacity' => 40, 'is_available' => 1],
            ['building_id' => 5, 'name' => 'KBM210', 'capacity' => 40, 'is_available' => 1],
            ['building_id' => 6, 'name' => 'KHIB201', 'capacity' => 60, 'is_available' => 1],
            ['building_id' => 6, 'name' => 'KHIB202', 'capacity' => 45, 'is_available' => 1],
            ['building_id' => 6, 'name' => 'KHIB203', 'capacity' => 55, 'is_available' => 1],
            ['building_id' => 6, 'name' => 'KHIB204', 'capacity' => 60, 'is_available' => 1],
            ['building_id' => 6, 'name' => 'KHIB205', 'capacity' => 60, 'is_available' => 1],
            ['building_id' => 6, 'name' => 'KHIB206', 'capacity' => 60, 'is_available' => 1],
            ['building_id' => 6, 'name' => 'KHIB207', 'capacity' => 50, 'is_available' => 1],
            ['building_id' => 6, 'name' => 'KHIB208', 'capacity' => 50, 'is_available' => 1],
            ]
        ));
    }
}
