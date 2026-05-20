<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Safety: normalize legacy value
        DB::table('item_borrowings')
            ->where('status', 'requested')
            ->update(['status' => 'waiting']);

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            // SQLite doesn't support ENUM in the same way; keep existing column type.
            return;
        }

        $enumValues = "('waiting','approved','rejected','cancelled','returned')";

        // MySQL-style
        $table = 'item_borrowings';
        $col = 'status';
        $default = "DEFAULT 'waiting'";

        // Use MODIFY for MySQL/MariaDB; for PostgreSQL we'd need different syntax.
        // This project already uses MySQL-style ALTER in existing migrations.
        DB::statement("ALTER TABLE `$table` MODIFY `$col` ENUM $enumValues NOT NULL $default");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE `item_borrowings` MODIFY `status` VARCHAR(255) NOT NULL DEFAULT 'waiting'");
    }
};

