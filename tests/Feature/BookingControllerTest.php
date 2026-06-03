<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Booking;
use App\Models\Campus;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_availability_returns_daily_booking_summary_for_selected_range(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $campus = Campus::query()->create([
            'name' => 'Kampus A',
            'address' => 'Jl. Kampus',
            'phone' => '021000000',
        ]);

        $building = Building::query()->create([
            'campus_id' => $campus->id,
            'name' => 'Gedung 1',
        ]);

        $room = Room::query()->create([
            'building_id' => $building->id,
            'name' => 'Ruang 101',
            'capacity' => 40,
            'is_available' => true,
        ]);

        Booking::query()->create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'title' => 'Kegiatan 2 Hari',
            'description' => 'Bentrok lintas tanggal',
            'start_time' => '2026-06-10 09:00:00',
            'end_time' => '2026-06-11 11:30:00',
            'status' => 'approved',
        ]);

        Booking::query()->create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'title' => 'Rapat Siang',
            'description' => 'Bentrok tambahan',
            'start_time' => '2026-06-11 13:00:00',
            'end_time' => '2026-06-11 15:00:00',
            'status' => 'waiting',
        ]);

        $response = $this->actingAs($user)->getJson(route('rooms.availability', [
            'room' => $room->id,
            'date' => '2026-06-11',
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-12',
        ]));

        $response->assertOk();
        $response->assertJsonPath('bookings.0.start', '00:00');
        $response->assertJsonPath('bookings.0.end', '11:30');
        $response->assertJsonPath('bookings.1.start', '13:00');
        $response->assertJsonPath('bookings.1.end', '15:00');
        $response->assertJsonCount(2, 'daily_bookings');
        $response->assertJsonPath('daily_bookings.0.date', '2026-06-10');
        $response->assertJsonPath('daily_bookings.0.bookings.0.start', '09:00');
        $response->assertJsonPath('daily_bookings.0.bookings.0.end', '23:59');
        $response->assertJsonPath('daily_bookings.1.date', '2026-06-11');
        $response->assertJsonPath('daily_bookings.1.bookings.0.start', '00:00');
        $response->assertJsonPath('daily_bookings.1.bookings.0.end', '11:30');
    }

    public function test_store_rejects_multi_day_booking_that_conflicts_on_a_later_day(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $campus = Campus::query()->create([
            'name' => 'Kampus A',
            'address' => 'Jl. Kampus',
            'phone' => '021000000',
        ]);

        $building = Building::query()->create([
            'campus_id' => $campus->id,
            'name' => 'Gedung 1',
        ]);

        $room = Room::query()->create([
            'building_id' => $building->id,
            'name' => 'Ruang 101',
            'capacity' => 40,
            'is_available' => true,
        ]);

        $startDay = now()->addDays(3)->next(Carbon::MONDAY)->startOfDay();
        $endDay = $startDay->copy()->addDay();

        Booking::query()->create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'title' => 'Booking Existing',
            'description' => 'Bentrok pada hari kedua',
            'start_time' => $endDay->copy()->setTime(9, 0)->toDateTimeString(),
            'end_time' => $endDay->copy()->setTime(10, 0)->toDateTimeString(),
            'status' => 'approved',
        ]);

        $response = $this->actingAs($user)
            ->from(route('bookings.create'))
            ->post(route('bookings.store'), [
                'room_id' => $room->id,
                'schedule_mode' => Booking::MODE_CONTINUOUS,
                'title' => 'Booking Multi Hari',
                'description' => 'Uji bentrok hari kedua',
                'start_date' => $startDay->toDateString(),
                'end_date' => $endDay->toDateString(),
                'start_time' => '09:00',
                'end_time' => '09:30',
            ]);

        $response->assertRedirect(route('bookings.create'));
        $response->assertSessionHasErrors('room_id');
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_store_rejects_same_hours_daily_booking_when_one_day_conflicts(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $campus = Campus::query()->create([
            'name' => 'Kampus A',
            'address' => 'Jl. Kampus',
            'phone' => '021000000',
        ]);

        $building = Building::query()->create([
            'campus_id' => $campus->id,
            'name' => 'Gedung 1',
        ]);

        $room = Room::query()->create([
            'building_id' => $building->id,
            'name' => 'Ruang 101',
            'capacity' => 40,
            'is_available' => true,
        ]);

        $startDay = now()->addDays(3)->next(Carbon::MONDAY)->startOfDay();
        $endDay = $startDay->copy()->addDays(2);

        Booking::query()->create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'title' => 'Kelas Pagi',
            'description' => 'Bentrok di hari kedua',
            'start_time' => $startDay->copy()->addDay()->setTime(8, 30)->toDateTimeString(),
            'end_time' => $startDay->copy()->addDay()->setTime(10, 0)->toDateTimeString(),
            'schedule_mode' => Booking::MODE_CONTINUOUS,
            'schedule_start_date' => $startDay->copy()->addDay()->toDateString(),
            'schedule_end_date' => $startDay->copy()->addDay()->toDateString(),
            'schedule_start_clock' => '08:30:00',
            'schedule_end_clock' => '10:00:00',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($user)
            ->from(route('bookings.create'))
            ->post(route('bookings.store'), [
                'room_id' => $room->id,
                'schedule_mode' => Booking::MODE_SAME_HOURS_DAILY,
                'title' => 'Booking Berulang',
                'description' => 'Jam sama setiap hari',
                'start_date' => $startDay->toDateString(),
                'end_date' => $endDay->toDateString(),
                'start_time' => '09:00',
                'end_time' => '11:00',
            ]);

        $response->assertRedirect(route('bookings.create'));
        $response->assertSessionHasErrors('room_id');
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_store_persists_same_hours_daily_booking_as_single_record(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $campus = Campus::query()->create([
            'name' => 'Kampus A',
            'address' => 'Jl. Kampus',
            'phone' => '021000000',
        ]);

        $building = Building::query()->create([
            'campus_id' => $campus->id,
            'name' => 'Gedung 1',
        ]);

        $room = Room::query()->create([
            'building_id' => $building->id,
            'name' => 'Ruang 101',
            'capacity' => 40,
            'is_available' => true,
        ]);

        $startDay = now()->addDays(3)->next(Carbon::MONDAY)->startOfDay();
        $endDay = $startDay->copy()->addDays(2);

        $response = $this->actingAs($user)
            ->post(route('bookings.store'), [
                'room_id' => $room->id,
                'schedule_mode' => Booking::MODE_SAME_HOURS_DAILY,
                'title' => 'Workshop Harian',
                'description' => 'Satu jam yang sama setiap hari',
                'start_date' => $startDay->toDateString(),
                'end_date' => $endDay->toDateString(),
                'start_time' => '09:00',
                'end_time' => '11:00',
            ]);

        $response->assertRedirect(route('bookings.index'));
        $this->assertDatabaseHas('bookings', [
            'room_id' => $room->id,
            'title' => 'Workshop Harian',
            'schedule_mode' => Booking::MODE_SAME_HOURS_DAILY,
            'schedule_start_date' => $startDay->toDateString(),
            'schedule_end_date' => $endDay->toDateString(),
            'schedule_start_clock' => '09:00:00',
            'schedule_end_clock' => '11:00:00',
        ]);
    }
}
