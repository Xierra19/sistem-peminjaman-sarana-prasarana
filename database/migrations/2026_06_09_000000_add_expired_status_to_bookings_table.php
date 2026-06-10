<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bookings') || Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("
            ALTER TABLE `bookings`
            MODIFY `status` ENUM('waiting','pending','requested','approved','rejected','cancelled','expired')
            NOT NULL DEFAULT 'waiting'
        ");
    }

    public function down(): void
    {
        if (! Schema::hasTable('bookings') || Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::table('bookings')
            ->whereIn('status', ['pending', 'requested', 'expired'])
            ->update(['status' => 'waiting']);

        DB::statement("
            ALTER TABLE `bookings`
            MODIFY `status` ENUM('waiting','approved','rejected','cancelled')
            NOT NULL DEFAULT 'waiting'
        ");
    }
};
