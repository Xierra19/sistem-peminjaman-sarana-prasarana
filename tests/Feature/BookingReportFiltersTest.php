<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Room;
use App\Models\User;
use App\Support\BookingReportFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingReportFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_date_range_includes_schedules_that_overlap_the_filter(): void
    {
        $user = User::factory()->create();
        $campus = Campus::create([
            'name' => 'Kampus Bekasi',
            'address' => 'Bekasi',
            'phone' => '021000000',
        ]);
        $building = Building::create([
            'name' => 'Gedung A',
            'campus_id' => $campus->id,
        ]);
        $room = Room::create([
            'name' => 'Ruang 101',
            'building_id' => $building->id,
            'capacity' => 30,
            'is_available' => true,
        ]);

        $before = $this->createBooking($user, $room, '2026-06-07', '2026-06-09');
        $overlapsStart = $this->createBooking($user, $room, '2026-06-08', '2026-06-10');
        $inside = $this->createBooking($user, $room, '2026-06-11', '2026-06-11');
        $overlapsEnd = $this->createBooking($user, $room, '2026-06-12', '2026-06-14');
        $after = $this->createBooking($user, $room, '2026-06-13', '2026-06-15');
        $legacy = $this->createBooking($user, $room, null, null, '2026-06-11', '2026-06-12');

        $query = Booking::query();
        BookingReportFilters::apply($query, [
            'booking_start_date' => '2026-06-10',
            'booking_end_date' => '2026-06-12',
        ]);

        $filteredIds = $query->pluck('id')->all();

        $this->assertEqualsCanonicalizing(
            [$overlapsStart->id, $inside->id, $overlapsEnd->id, $legacy->id],
            $filteredIds,
        );
        $this->assertNotContains($before->id, $filteredIds);
        $this->assertNotContains($after->id, $filteredIds);
    }

    private function createBooking(
        User $user,
        Room $room,
        ?string $scheduleStart,
        ?string $scheduleEnd,
        ?string $legacyStart = null,
        ?string $legacyEnd = null,
    ): Booking {
        $startDate = $scheduleStart ?? $legacyStart;
        $endDate = $scheduleEnd ?? $legacyEnd;

        return Booking::create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'title' => "Booking {$startDate}",
            'start_time' => "{$startDate} 08:00:00",
            'end_time' => "{$endDate} 10:00:00",
            'schedule_mode' => Booking::MODE_CONTINUOUS,
            'schedule_start_date' => $scheduleStart,
            'schedule_end_date' => $scheduleEnd,
            'schedule_start_clock' => '08:00:00',
            'schedule_end_clock' => '10:00:00',
            'status' => 'approved',
        ]);
    }
}
