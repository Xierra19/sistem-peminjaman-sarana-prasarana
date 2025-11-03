<?php

// file: database/migrations/2025_11_03_000102_create_course_exam_schedules_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_exam_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_offering_id')->constrained()->cascadeOnDelete();
            $table->enum('exam_type', ['UTS', 'UAS']);
            $table->unsignedTinyInteger('week_seq')->nullable();
            $table->date('exam_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['course_offering_id', 'exam_type']);
            $table->index('exam_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_exam_schedules');
    }
};
