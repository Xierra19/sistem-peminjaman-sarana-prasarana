<?php

// file: database/migrations/2025_11_03_000110_add_blocks_to_semesters_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('semesters', function (Blueprint $table) {
            if (!Schema::hasColumn('semesters', 'teaching_1_7_start_date')) {
                $table->date('teaching_1_7_start_date')->nullable()->after('end_date');
            }

            if (!Schema::hasColumn('semesters', 'teaching_1_7_end_date')) {
                $table->date('teaching_1_7_end_date')->nullable()->after('teaching_1_7_start_date');
            }

            if (!Schema::hasColumn('semesters', 'teaching_8_14_start_date')) {
                $table->date('teaching_8_14_start_date')->nullable()->after('teaching_1_7_end_date');
            }

            if (!Schema::hasColumn('semesters', 'teaching_8_14_end_date')) {
                $table->date('teaching_8_14_end_date')->nullable()->after('teaching_8_14_start_date');
            }

            if (!Schema::hasColumn('semesters', 'uts_start_date')) {
                $table->date('uts_start_date')->nullable()->after('teaching_8_14_end_date');
            }

            if (!Schema::hasColumn('semesters', 'uts_end_date')) {
                $table->date('uts_end_date')->nullable()->after('uts_start_date');
            }

            if (!Schema::hasColumn('semesters', 'uas_start_date')) {
                $table->date('uas_start_date')->nullable()->after('uts_end_date');
            }

            if (!Schema::hasColumn('semesters', 'uas_end_date')) {
                $table->date('uas_end_date')->nullable()->after('uas_start_date');
            }
        });

        Schema::table('semesters', function (Blueprint $table) {
            if (Schema::hasColumn('semesters', 'teaching_weeks_before_uts')) {
                $table->dropColumn('teaching_weeks_before_uts');
            }

            if (Schema::hasColumn('semesters', 'teaching_weeks_after_uts')) {
                $table->dropColumn('teaching_weeks_after_uts');
            }
        });
    }

    public function down(): void
    {
        Schema::table('semesters', function (Blueprint $table) {
            if (Schema::hasColumn('semesters', 'teaching_1_7_start_date')) {
                $table->dropColumn('teaching_1_7_start_date');
            }

            if (Schema::hasColumn('semesters', 'teaching_1_7_end_date')) {
                $table->dropColumn('teaching_1_7_end_date');
            }

            if (Schema::hasColumn('semesters', 'teaching_8_14_start_date')) {
                $table->dropColumn('teaching_8_14_start_date');
            }

            if (Schema::hasColumn('semesters', 'teaching_8_14_end_date')) {
                $table->dropColumn('teaching_8_14_end_date');
            }
        });

        Schema::table('semesters', function (Blueprint $table) {
            if (!Schema::hasColumn('semesters', 'teaching_weeks_before_uts')) {
                $table->unsignedTinyInteger('teaching_weeks_before_uts')->default(7)->after('end_date');
            }

            if (!Schema::hasColumn('semesters', 'teaching_weeks_after_uts')) {
                $table->unsignedTinyInteger('teaching_weeks_after_uts')->default(7)->after('teaching_weeks_before_uts');
            }
        });
    }
};
