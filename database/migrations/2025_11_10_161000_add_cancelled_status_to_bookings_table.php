<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table): void {
            DB::statement("
                ALTER TABLE `bookings`
                MODIFY `status` ENUM('waiting','approved','rejected','cancelled') NOT NULL DEFAULT 'waiting'
            ");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table): void {
            DB::table('bookings')
                ->where('status', 'cancelled')
                ->update(['status' => 'waiting']);

            DB::statement("
                ALTER TABLE `bookings`
                MODIFY `status` ENUM('waiting','approved','rejected') NOT NULL DEFAULT 'waiting'
            ");
        });
    }
};
