<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('schedule_mode')->default('continuous')->after('end_time');
            $table->date('schedule_start_date')->nullable()->after('schedule_mode');
            $table->date('schedule_end_date')->nullable()->after('schedule_start_date');
            $table->time('schedule_start_clock')->nullable()->after('schedule_end_date');
            $table->time('schedule_end_clock')->nullable()->after('schedule_start_clock');
        });

        DB::table('bookings')
            ->orderBy('id')
            ->chunkById(100, function ($bookings): void {
                foreach ($bookings as $booking) {
                    $start = Carbon::parse($booking->start_time);
                    $end = Carbon::parse($booking->end_time);

                    DB::table('bookings')
                        ->where('id', $booking->id)
                        ->update([
                            'schedule_mode' => 'continuous',
                            'schedule_start_date' => $start->toDateString(),
                            'schedule_end_date' => $end->toDateString(),
                            'schedule_start_clock' => $start->format('H:i:s'),
                            'schedule_end_clock' => $end->format('H:i:s'),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn([
                'schedule_mode',
                'schedule_start_date',
                'schedule_end_date',
                'schedule_start_clock',
                'schedule_end_clock',
            ]);
        });
    }
};
