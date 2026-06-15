<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE `bookings`
                MODIFY `status` ENUM('waiting','pending','requested','needs_revision','approved','rejected','cancelled','expired')
                NOT NULL DEFAULT 'waiting'
            ");

            DB::statement("
                ALTER TABLE `item_borrowings`
                MODIFY `status` ENUM('waiting','needs_revision','approved','rejected','cancelled','returned')
                NOT NULL DEFAULT 'waiting'
            ");
        }

        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('resubmitted_from_id')
                ->nullable()
                ->after('user_id')
                ->constrained('bookings')
                ->nullOnDelete();
        });

        Schema::table('item_borrowings', function (Blueprint $table): void {
            $table->foreignId('resubmitted_from_id')
                ->nullable()
                ->after('user_id')
                ->constrained('item_borrowings')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('resubmitted_from_id');
        });

        Schema::table('item_borrowings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('resubmitted_from_id');
        });

        DB::table('bookings')
            ->where('status', 'needs_revision')
            ->update(['status' => 'rejected']);
        DB::table('item_borrowings')
            ->where('status', 'needs_revision')
            ->update(['status' => 'rejected']);

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE `bookings`
                MODIFY `status` ENUM('waiting','pending','requested','approved','rejected','cancelled','expired')
                NOT NULL DEFAULT 'waiting'
            ");

            DB::statement("
                ALTER TABLE `item_borrowings`
                MODIFY `status` ENUM('waiting','approved','rejected','cancelled','returned')
                NOT NULL DEFAULT 'waiting'
            ");
        }
    }
};
