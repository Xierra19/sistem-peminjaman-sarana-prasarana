<?php

// file: database/migrations/2025_11_03_000103_add_exam_windows_to_master_semesters_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_semesters', function (Blueprint $table) {
            $table->date('uts_start_date')->nullable()->after('end_date');
            $table->date('uts_end_date')->nullable()->after('uts_start_date');
            $table->date('uas_start_date')->nullable()->after('uts_end_date');
            $table->date('uas_end_date')->nullable()->after('uas_start_date');
        });
    }

    public function down(): void
    {
        Schema::table('master_semesters', function (Blueprint $table) {
            $table->dropColumn(['uts_start_date', 'uts_end_date', 'uas_start_date', 'uas_end_date']);
        });
    }
};
