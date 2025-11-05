<?php

// file: database/migrations/2025_11_03_000112_create_course_exam_schedules_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('course_exam_schedules')) {
            Schema::create('course_exam_schedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('course_offering_id')->constrained()->cascadeOnDelete();
                $table->enum('exam_type', ['UTS', 'UAS']);
                $table->date('exam_date')->nullable();
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
                $table->timestamps();

                $table->unique(['course_offering_id', 'exam_type']);
            });

            return;
        }

        Schema::table('course_exam_schedules', function (Blueprint $table) {
            if (Schema::hasColumn('course_exam_schedules', 'room_id')) {
                $table->dropForeign(['room_id']);
                $table->dropColumn('room_id');
            }

            if (Schema::hasColumn('course_exam_schedules', 'week_seq')) {
                $table->dropColumn('week_seq');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('course_exam_schedules')) {
            return;
        }

        Schema::table('course_exam_schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('course_exam_schedules', 'week_seq')) {
                $table->unsignedTinyInteger('week_seq')->nullable()->after('exam_type');
            }

            if (!Schema::hasColumn('course_exam_schedules', 'exam_date')) {
                $table->date('exam_date')->nullable()->after('week_seq');
            }

            if (!Schema::hasColumn('course_exam_schedules', 'start_time')) {
                $table->time('start_time')->nullable()->after('exam_date');
            }

            if (!Schema::hasColumn('course_exam_schedules', 'end_time')) {
                $table->time('end_time')->nullable()->after('start_time');
            }

            if (!Schema::hasColumn('course_exam_schedules', 'room_id')) {
                $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete()->after('end_time');
            }
        });
    }
};
