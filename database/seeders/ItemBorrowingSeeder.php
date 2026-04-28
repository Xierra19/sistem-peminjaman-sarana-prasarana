<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItemBorrowingSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('item_borrowings')->insert([
            [
                'id' => 1,
                'user_id' => 1,
                'title' => 'Acara Bem',
                'item_id' => 5,
                'borrow_date' => '2026-04-28 00:00:00',
                'return_date' => '2026-04-29 23:59:59',
                'quantity' => 1,
                'description' => 'Acara BEM',
                'status' => 'approved',
                'attachment' => null,
                'approved_at' => '2026-04-10 00:08:12',
                'approved_by' => 1,
                'returned_at' => null,
                'returned_by' => null,
                'created_at' => '2026-04-09 22:11:47',
                'updated_at' => '2026-04-10 00:08:12',
            ],
            [
                'id' => 2,
                'user_id' => 7,
                'title' => 'ACARA GIC',
                'item_id' => 5,
                'borrow_date' => '2026-04-28 00:00:00',
                'return_date' => '2026-04-28 23:59:59',
                'quantity' => 1,
                'description' => 'Test',
                'status' => 'approved',
                'attachment' => null,
                'approved_at' => '2026-04-09 23:45:05',
                'approved_by' => 1,
                'returned_at' => null,
                'returned_by' => null,
                'created_at' => '2026-04-09 23:44:51',
                'updated_at' => '2026-04-09 23:45:05',
            ],
        ]);
    }
}

