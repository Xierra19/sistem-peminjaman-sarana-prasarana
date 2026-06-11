<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE `item_borrowing_items` MODIFY `borrow_date` DATETIME NOT NULL');
        DB::statement('ALTER TABLE `item_borrowing_items` MODIFY `return_date` DATETIME NOT NULL');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE `item_borrowing_items` MODIFY `borrow_date` DATE NOT NULL');
        DB::statement('ALTER TABLE `item_borrowing_items` MODIFY `return_date` DATE NOT NULL');
    }
};
