<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
$this->call([
            CampusSeeder::class,
            BuildingSeeder::class,
            RoomSeeder::class,
            ItemSeeder::class,
            UserSeeder::class,
            ItemBorrowingSeeder::class,
        ]);
    }
}

