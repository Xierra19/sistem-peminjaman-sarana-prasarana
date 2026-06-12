<?php

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_room_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->timestamps();

            $table->index(['room_id', 'start_time', 'end_time'], 'booking_room_schedule_lookup');
        });

        DB::table('bookings')
            ->orderBy('id')
            ->chunkById(100, function ($bookings): void {
                foreach ($bookings as $booking) {
                    if (! $booking->room_id || ! $booking->start_time || ! $booking->end_time) {
                        continue;
                    }

                    if ($booking->schedule_mode === 'same_hours_daily') {
                        $startDate = Carbon::parse($booking->schedule_start_date ?? $booking->start_time);
                        $endDate = Carbon::parse($booking->schedule_end_date ?? $booking->end_time);
                        $startClock = $booking->schedule_start_clock
                            ?: Carbon::parse($booking->start_time)->format('H:i:s');
                        $endClock = $booking->schedule_end_clock
                            ?: Carbon::parse($booking->end_time)->format('H:i:s');

                        foreach (CarbonPeriod::create($startDate->startOfDay(), $endDate->startOfDay()) as $date) {
                            DB::table('booking_room_schedules')->insert([
                                'booking_id' => $booking->id,
                                'room_id' => $booking->room_id,
                                'start_time' => Carbon::parse($date->toDateString().' '.$startClock),
                                'end_time' => Carbon::parse($date->toDateString().' '.$endClock),
                                'created_at' => $booking->created_at,
                                'updated_at' => $booking->updated_at,
                            ]);
                        }

                        continue;
                    }

                    DB::table('booking_room_schedules')->insert([
                        'booking_id' => $booking->id,
                        'room_id' => $booking->room_id,
                        'start_time' => $booking->start_time,
                        'end_time' => $booking->end_time,
                        'created_at' => $booking->created_at,
                        'updated_at' => $booking->updated_at,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_room_schedules');
    }
};
