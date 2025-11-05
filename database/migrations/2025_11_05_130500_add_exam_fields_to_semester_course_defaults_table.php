<?php

// file: database/migrations/2025_11_05_130500_add_exam_fields_to_semester_course_defaults_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('semester_course_defaults')) {
            return;
        }

        Schema::table('semester_course_defaults', function (Blueprint $table) {
            if (!Schema::hasColumn('semester_course_defaults', 'uts_exam_date')) {
                $table->date('uts_exam_date')->nullable()->after('practicum2_room_id');
                $table->time('uts_start_time')->nullable()->after('uts_exam_date');
                $table->time('uts_end_time')->nullable()->after('uts_start_time');
                $table->foreignId('uts_room_id')->nullable()->after('uts_end_time')
                    ->constrained('rooms')->nullOnDelete();
            }

            if (!Schema::hasColumn('semester_course_defaults', 'uas_exam_date')) {
                $table->date('uas_exam_date')->nullable()->after('uts_room_id');
                $table->time('uas_start_time')->nullable()->after('uas_exam_date');
                $table->time('uas_end_time')->nullable()->after('uas_start_time');
                $table->foreignId('uas_room_id')->nullable()->after('uas_end_time')
                    ->constrained('rooms')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('semester_course_defaults')) {
            return;
        }

        Schema::table('semester_course_defaults', function (Blueprint $table) {
            if (Schema::hasColumn('semester_course_defaults', 'uas_room_id')) {
                $table->dropForeign(['uas_room_id']);
            }
            if (Schema::hasColumn('semester_course_defaults', 'uts_room_id')) {
                $table->dropForeign(['uts_room_id']);
            }

            $columns = [
                'uts_exam_date',
                'uts_start_time',
                'uts_end_time',
                'uts_room_id',
                'uas_exam_date',
                'uas_start_time',
                'uas_end_time',
                'uas_room_id',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('semester_course_defaults', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
