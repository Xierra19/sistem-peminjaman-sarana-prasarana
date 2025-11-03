<?php

// database/migrations/2025_11_03_000010_add_exam_windows_to_semesters_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('semesters')) {
            Schema::create('semesters', function (Blueprint $t) {
                $t->id();
                $t->date('start_date')->nullable();
                $t->date('end_date')->nullable();
                $t->boolean('is_active')->default(false);
                $t->date('uts_start_date')->nullable();
                $t->date('uts_end_date')->nullable();
                $t->date('uas_start_date')->nullable();
                $t->date('uas_end_date')->nullable();
                $t->unsignedTinyInteger('teaching_weeks_before_uts')->default(7);
                $t->unsignedTinyInteger('teaching_weeks_after_uts')->default(7);
                $t->timestamps();
            });

            return;
        }

        Schema::table('semesters', function (Blueprint $t) {
            $t->date('uts_start_date')->nullable()->after('end_date');
            $t->date('uts_end_date')->nullable()->after('uts_start_date');
            $t->date('uas_start_date')->nullable()->after('uts_end_date');
            $t->date('uas_end_date')->nullable()->after('uas_start_date');

            $t->unsignedTinyInteger('teaching_weeks_before_uts')->default(7)->after('uas_end_date');
            $t->unsignedTinyInteger('teaching_weeks_after_uts')->default(7)->after('teaching_weeks_before_uts');
        });
    }

    public function down(): void
    {
        Schema::table('semesters', function (Blueprint $t) {
            $t->dropColumn([
                'uts_start_date','uts_end_date',
                'uas_start_date','uas_end_date',
                'teaching_weeks_before_uts','teaching_weeks_after_uts'
            ]);
        });
    }
};
