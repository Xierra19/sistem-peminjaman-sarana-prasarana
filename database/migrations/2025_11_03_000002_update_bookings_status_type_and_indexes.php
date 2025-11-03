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
        if (! Schema::hasColumn('bookings', 'date')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->date('date')->nullable()->after('room_id');
            });

            DB::statement('UPDATE `bookings` SET `date` = DATE(`start_time`) WHERE `start_time` IS NOT NULL AND `date` IS NULL');
        }

        Schema::table('bookings', function (Blueprint $table): void {
            DB::table('bookings')
                ->whereNotIn('status', ['waiting', 'approved', 'rejected'])
                ->update(['status' => 'waiting']);

            $table->enum('status', ['waiting', 'approved', 'rejected'])->default('waiting')->change();

            if (! Schema::hasColumn('bookings', 'type')) {
                $table->enum('type', ['CLASS', 'EXAM', 'KP', 'OTHER'])
                    ->default('OTHER')
                    ->after('status');
            } else {
                $table->enum('type', ['CLASS', 'EXAM', 'KP', 'OTHER'])->default('OTHER')->change();
            }

            $table->dropForeign(['room_id']);
            $table->foreign('room_id')
                ->references('id')
                ->on('rooms')
                ->cascadeOnDelete();

            $table->index(['room_id', 'date'], 'bookings_room_id_date_index');
            $table->index(['status', 'date'], 'bookings_status_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex('bookings_room_id_date_index');
            $table->dropIndex('bookings_status_date_index');

            $table->dropForeign(['room_id']);
            $table->foreign('room_id')
                ->references('id')
                ->on('rooms')
                ->cascadeOnDelete();

            $table->dropColumn('type');

            DB::table('bookings')
                ->where('status', 'waiting')
                ->update(['status' => 'pending']);

            $table->string('status')->default('pending')->change();
        });

        if (Schema::hasColumn('bookings', 'date')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->dropColumn('date');
            });
        }
    }
};
