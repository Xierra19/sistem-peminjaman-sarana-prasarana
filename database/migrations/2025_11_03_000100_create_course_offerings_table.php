<?php

// file: database/migrations/2025_11_03_000100_create_course_offerings_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->string('course_code', 50);
            $table->string('course_name', 150);
            $table->string('class_group', 20)->nullable();
            $table->timestamps();

            $table->unique(['semester_id', 'course_code', 'class_group']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_offerings');
    }
};
