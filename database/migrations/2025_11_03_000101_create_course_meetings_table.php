<?php

// file: database/migrations/2025_11_03_000101_create_course_meetings_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_offering_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('meeting_no');
            $table->date('meeting_date')->nullable();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['course_offering_id', 'meeting_no']);
            $table->index('meeting_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_meetings');
    }
};
