<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Campus;
use App\Models\Semester;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingControllerTest extends TestCase
{
    use RefreshDatabase;

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

        $semester = Semester::query()->create([
            'year' => (int) $startDay->format('Y'),
            'term' => 'genap',
            'is_active' => true,
            'start_date' => $startDay->copy()->subWeek()->toDateString(),
            'end_date' => $endDay->copy()->addWeek()->toDateString(),
        ]);

        $response = $this->actingAs($user)
            ->from(route('bookings.create'))
            ->post(route('bookings.store'), [
                'room_id' => $room->id,
                'title' => 'Booking Multi Hari',
                'description' => 'Uji bentrok hari kedua',
                'start_time' => $startDay->copy()->setTime(9, 0)->format('Y-m-d\TH:i'),
                'end_time' => $endDay->copy()->setTime(9, 30)->format('Y-m-d\TH:i'),
            ]);

        $response->assertRedirect(route('bookings.create'));
        $response->assertSessionHasErrors('start_time');
        $this->assertDatabaseCount('bookings', 0);
    }
}
