<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Room;
use App\Models\User;
use App\Services\ExpirePendingBookings;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpirePendingBookingsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_booking_remains_waiting_until_the_final_borrowing_day_ends(): void
    {
        $booking = $this->createBooking([
            'end_time' => '2026-06-10 08:00:00',
            'schedule_end_date' => '2026-06-10',
        ]);

        $expiredCount = app(ExpirePendingBookings::class)->handle(
            Carbon::parse('2026-06-10 23:59:59', ExpirePendingBookings::TIMEZONE)
        );

        $this->assertSame(0, $expiredCount);
        $this->assertSame('waiting', $booking->fresh()->status);
        $this->assertDatabaseMissing('log_histories', [
            'booking_id' => $booking->id,
            'action' => 'expired',
        ]);
    }

    public function test_booking_expires_at_midnight_after_the_final_borrowing_day(): void
    {
        $booking = $this->createBooking([
            'end_time' => '2026-06-09 18:00:00',
            'schedule_end_date' => '2026-06-09',
        ]);

        $expiredCount = app(ExpirePendingBookings::class)->handle(
            Carbon::parse('2026-06-10 00:00:00', ExpirePendingBookings::TIMEZONE)
        );

        $this->assertSame(1, $expiredCount);
        $this->assertSame('expired', $booking->fresh()->status);
        $this->assertDatabaseHas('log_histories', [
            'booking_id' => $booking->id,
            'user_id' => null,
            'action' => 'expired',
            'description' => 'Permintaan kedaluwarsa karena belum diproses hingga hari peminjaman terakhir berakhir.',
        ]);
    }

    public function test_expiration_falls_back_to_end_time_for_legacy_booking_and_is_idempotent(): void
    {
        $booking = $this->createBooking([
            'end_time' => '2026-06-09 23:00:00',
            'schedule_end_date' => null,
        ]);
        $now = Carbon::parse('2026-06-10 00:05:00', ExpirePendingBookings::TIMEZONE);
        $service = app(ExpirePendingBookings::class);

        $this->assertSame(1, $service->handle($now));
        $this->assertSame(0, $service->handle($now));
        $this->assertDatabaseCount('log_histories', 1);
        $this->assertSame('expired', $booking->fresh()->status);
    }

    public function test_admin_cannot_approve_booking_after_expiration_cutoff(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-10 00:05:00', ExpirePendingBookings::TIMEZONE));

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_BAP,
        ]);
        $booking = $this->createBooking([
            'end_time' => '2026-06-09 18:00:00',
            'schedule_end_date' => '2026-06-09',
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.bookings.show', $booking))
            ->post(route('admin.bookings.update-status', $booking), [
                'status' => 'approved',
            ]);

        $response->assertRedirect(route('admin.bookings.show', $booking));
        $response->assertSessionHasErrors('status');
        $this->assertSame('expired', $booking->fresh()->status);
        $this->assertNull($booking->fresh()->letter_number);
    }

    private function createBooking(array $overrides = []): Booking
    {
        $user = User::factory()->create();
        $campus = Campus::query()->create([
            'name' => 'Kampus Uji',
            'address' => 'Jl. Uji',
            'phone' => '021000000',
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id,
            'name' => 'Gedung Uji',
        ]);
        $room = Room::query()->create([
            'building_id' => $building->id,
            'name' => 'Ruang Uji',
            'capacity' => 20,
            'is_available' => true,
        ]);

        return Booking::query()->create(array_merge([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'title' => 'Booking Uji',
            'description' => 'Pengujian status kedaluwarsa',
            'start_time' => '2026-06-09 08:00:00',
            'end_time' => '2026-06-09 10:00:00',
            'schedule_mode' => Booking::MODE_CONTINUOUS,
            'schedule_start_date' => '2026-06-09',
            'schedule_end_date' => '2026-06-09',
            'schedule_start_clock' => '08:00:00',
            'schedule_end_clock' => '10:00:00',
            'status' => 'waiting',
        ], $overrides));
    }
}
