<?php

// file: database/migrations/2025_11_05_120000_add_room_to_course_exam_schedules_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('course_exam_schedules')) {
            return;
        }

        Schema::table('course_exam_schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('course_exam_schedules', 'room_id')) {
                $table
                    ->foreignId('room_id')
                    ->nullable()
                    ->after('end_time')
                    ->constrained()
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('course_exam_schedules')) {
            return;
        }

        Schema::table('course_exam_schedules', function (Blueprint $table) {
            if (Schema::hasColumn('course_exam_schedules', 'room_id')) {
                $table->dropForeign(['room_id']);
                $table->dropColumn('room_id');
            }
        });
    }
};
