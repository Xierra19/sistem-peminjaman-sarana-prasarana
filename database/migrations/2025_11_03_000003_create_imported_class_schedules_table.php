<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('imported_class_schedules', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('course_code', 50);
            $table->string('class_name', 100);
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->index(['room_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imported_class_schedules');
    }
};
