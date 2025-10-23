<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semester_course_defaults', function (Blueprint $table) {
            $table->id();

            // Semester konteks
            $table->foreignId('semester_id')
                  ->constrained('master_semesters')
                  ->cascadeOnDelete();

            // Identitas mata kuliah / kelas
            $table->string('course_name', 255);      // "Jaringan Komputer Lanjut"
            $table->string('course_code', 64);       // "KH001" (atau kode matkul lain)

            // Hari pelaksanaan (pola mingguan)
            $table->enum('day_of_week', ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu']);

            // TEORI (wajib punya jam teori)
            $table->time('theory_start_time');
            $table->time('theory_end_time');
            $table->foreignId('theory_room_id')->nullable()
                  ->constrained('rooms')            // pakai tabel rooms yang sudah ada
                  ->nullOnDelete();

            // PRAKTIKUM 1 (opsional)
            $table->time('practicum1_start_time')->nullable();
            $table->time('practicum1_end_time')->nullable();
            $table->foreignId('practicum1_room_id')->nullable()
                  ->constrained('rooms')
                  ->nullOnDelete();

            // PRAKTIKUM 2 (opsional)
            $table->time('practicum2_start_time')->nullable();
            $table->time('practicum2_end_time')->nullable();
            $table->foreignId('practicum2_room_id')->nullable()
                  ->constrained('rooms')
                  ->nullOnDelete();

            $table->timestamps();

            // Index untuk bantu validasi overlap cepat (per hari + ruang teori)
            $table->index(['semester_id','day_of_week','theory_room_id','theory_start_time','theory_end_time'], 'idx_sem_theory_overlap');

            // Index untuk praktikum (jika diisi ruangnya)
            $table->index(['semester_id','day_of_week','practicum1_room_id','practicum1_start_time','practicum1_end_time'], 'idx_sem_prac1_overlap');
            $table->index(['semester_id','day_of_week','practicum2_room_id','practicum2_start_time','practicum2_end_time'], 'idx_sem_prac2_overlap');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semester_course_defaults');
    }
};
