<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_semesters', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year'); // contoh: 2025
            $table->enum('term', ['ganjil','genap']);
            $table->boolean('is_active')->default(false);

            // opsional, berguna kalau nanti mau generator pertemuan
            $table->date('anchor_date')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->tinyInteger('uts_week')->nullable();
            $table->tinyInteger('uas_week')->nullable();

            $table->timestamps();

            $table->unique(['year','term'], 'uq_master_semesters_year_term');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_semesters');
    }
};
