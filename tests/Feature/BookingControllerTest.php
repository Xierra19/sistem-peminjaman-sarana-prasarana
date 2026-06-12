<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRoomSchedule;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_availability_returns_booking_room_schedule_for_selected_date(): void
    {
        [$user, $rooms] = $this->createLocation(1);
        $date = now()->addDays(5)->toDateString();
        $booking = $this->createBooking($user, $rooms[0]);

        $booking->roomSchedules()->create([
            'room_id' => $rooms[0]->id,
            'start_time' => "{$date} 09:00:00",
            'end_time' => "{$date} 11:30:00",
        ]);

        $response = $this->actingAs($user)->getJson(route('rooms.availability', [
            'room' => $rooms[0]->id,
            'date' => $date,
            'start_date' => $date,
            'end_date' => $date,
        ]));

        $response->assertOk();
        $response->assertJsonPath('bookings.0.start', '09:00');
        $response->assertJsonPath('bookings.0.end', '11:30');
        $response->assertJsonPath('daily_bookings.0.date', $date);
    }

    public function test_availability_returns_only_selected_multiple_dates(): void
    {
        [$user, $rooms] = $this->createLocation(1);
        $selectedDates = [
            now()->addDays(5)->toDateString(),
            now()->addDays(7)->toDateString(),
        ];
        $unselectedDate = now()->addDays(6)->toDateString();
        $booking = $this->createBooking($user, $rooms[0]);

        foreach ([...$selectedDates, $unselectedDate] as $date) {
            $booking->roomSchedules()->create([
                'room_id' => $rooms[0]->id,
                'start_time' => "{$date} 09:00:00",
                'end_time' => "{$date} 11:00:00",
            ]);
        }

        $response = $this->actingAs($user)->getJson(route('rooms.availability', [
            'room' => $rooms[0]->id,
            'dates' => $selectedDates,
        ]));

        $response->assertOk();
        $response->assertJsonCount(2, 'daily_bookings');
        $this->assertEquals(
            $selectedDates,
            collect($response->json('daily_bookings'))->pluck('date')->all(),
        );
    }

    public function test_store_allows_different_rooms_at_the_same_time_in_one_booking(): void
    {
        [$user, $rooms] = $this->createLocation(2);
        $date = now()->addDays(5)->toDateString();

        $response = $this->actingAs($user)->post(route('bookings.store'), [
            'title' => 'Seminar Dua Ruangan',
            'description' => 'Kegiatan yang berjalan bersamaan.',
            'schedules' => [
                $this->schedulePayload($rooms[0], $date, '09:00', '11:00'),
                $this->schedulePayload($rooms[1], $date, '09:00', '11:00'),
            ],
        ]);

        $response->assertRedirect(route('bookings.index'));
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('booking_room_schedules', 2);
        $this->assertEqualsCanonicalizing(
            [$rooms[0]->id, $rooms[1]->id],
            BookingRoomSchedule::query()->pluck('room_id')->all(),
        );
    }

    public function test_store_allows_same_room_at_non_overlapping_times(): void
    {
        [$user, $rooms] = $this->createLocation(1);
        $date = now()->addDays(5)->toDateString();

        $response = $this->actingAs($user)->post(route('bookings.store'), [
            'title' => 'Dua Sesi',
            'schedules' => [
                $this->schedulePayload($rooms[0], $date, '08:00', '10:00'),
                $this->schedulePayload($rooms[0], $date, '10:00', '12:00'),
            ],
        ]);

        $response->assertRedirect(route('bookings.index'));
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('booking_room_schedules', 2);
    }

    public function test_store_expands_multiple_dates_from_one_schedule_card(): void
    {
        [$user, $rooms] = $this->createLocation(1);
        $dates = [
            now()->addDays(5)->toDateString(),
            now()->addDays(7)->toDateString(),
            now()->addDays(9)->toDateString(),
        ];

        $response = $this->actingAs($user)->post(route('bookings.store'), [
            'title' => 'Kelas pada Beberapa Tanggal',
            'schedules' => [[
                'room_id' => $rooms[0]->id,
                'dates' => $dates,
                'start_time' => '09:00',
                'end_time' => '11:00',
            ]],
        ]);

        $response->assertRedirect(route('bookings.index'));
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('booking_room_schedules', 3);
        $this->assertEquals(
            $dates,
            BookingRoomSchedule::query()
                ->orderBy('start_time')
                ->get()
                ->map(fn (BookingRoomSchedule $schedule) => $schedule->start_time->toDateString())
                ->all(),
        );
    }

    public function test_store_rejects_more_than_twenty_expanded_schedules(): void
    {
        [$user, $rooms] = $this->createLocation(1);
        $dates = collect(range(5, 15))
            ->map(fn (int $days) => now()->addDays($days)->toDateString())
            ->all();

        $response = $this->actingAs($user)
            ->from(route('bookings.create'))
            ->post(route('bookings.store'), [
                'title' => 'Terlalu Banyak Jadwal',
                'schedules' => [
                    [
                        'room_id' => $rooms[0]->id,
                        'dates' => $dates,
                        'start_time' => '09:00',
                        'end_time' => '11:00',
                    ],
                    [
                        'room_id' => $rooms[0]->id,
                        'dates' => $dates,
                        'start_time' => '13:00',
                        'end_time' => '15:00',
                    ],
                ],
            ]);

        $response->assertRedirect(route('bookings.create'));
        $response->assertSessionHasErrors('schedules');
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('booking_room_schedules', 0);
    }

    public function test_store_rejects_overlapping_rows_for_the_same_room(): void
    {
        [$user, $rooms] = $this->createLocation(1);
        $date = now()->addDays(5)->toDateString();

        $response = $this->actingAs($user)
            ->from(route('bookings.create'))
            ->post(route('bookings.store'), [
                'title' => 'Jadwal Bertumpuk',
                'schedules' => [
                    $this->schedulePayload($rooms[0], $date, '08:00', '10:00'),
                    $this->schedulePayload($rooms[0], $date, '09:30', '11:00'),
                ],
            ]);

        $response->assertRedirect(route('bookings.create'));
        $response->assertSessionHasErrors('schedules.1.start_time');
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('booking_room_schedules', 0);
    }

    public function test_store_rejects_entire_booking_when_one_room_conflicts_with_active_booking(): void
    {
        [$user, $rooms] = $this->createLocation(2);
        $date = now()->addDays(5)->toDateString();
        $existing = $this->createBooking($user, $rooms[0], [
            'title' => 'Booking Existing',
            'status' => 'approved',
            'start_time' => "{$date} 09:00:00",
            'end_time' => "{$date} 11:00:00",
        ]);
        $existing->roomSchedules()->create([
            'room_id' => $rooms[0]->id,
            'start_time' => "{$date} 09:00:00",
            'end_time' => "{$date} 11:00:00",
        ]);

        $response = $this->actingAs($user)
            ->from(route('bookings.create'))
            ->post(route('bookings.store'), [
                'title' => 'Booking Baru',
                'schedules' => [
                    $this->schedulePayload($rooms[1], $date, '08:00', '09:00'),
                    $this->schedulePayload($rooms[0], $date, '10:00', '12:00'),
                ],
            ]);

        $response->assertRedirect(route('bookings.create'));
        $response->assertSessionHasErrors('schedules.1.room_id');
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('booking_room_schedules', 1);
    }

    /**
     * @return array{0: User, 1: array<int, Room>}
     */
    private function createLocation(int $roomCount): array
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
        $rooms = [];

        for ($index = 1; $index <= $roomCount; $index++) {
            $rooms[] = Room::query()->create([
                'building_id' => $building->id,
                'name' => "Ruang 10{$index}",
                'capacity' => 40,
                'is_available' => true,
            ]);
        }

        return [$user, $rooms];
    }

    private function createBooking(User $user, Room $room, array $overrides = []): Booking
    {
        $date = now()->addDays(5)->toDateString();

        return Booking::query()->create(array_merge([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'title' => 'Booking Uji',
            'description' => 'Data booking untuk pengujian.',
            'start_time' => "{$date} 08:00:00",
            'end_time' => "{$date} 10:00:00",
            'schedule_mode' => Booking::MODE_CONTINUOUS,
            'schedule_start_date' => $date,
            'schedule_end_date' => $date,
            'schedule_start_clock' => '08:00:00',
            'schedule_end_clock' => '10:00:00',
            'status' => 'waiting',
        ], $overrides));
    }

    /**
     * @return array<string, int|string>
     */
    private function schedulePayload(Room $room, string $date, string $start, string $end): array
    {
        return [
            'room_id' => $room->id,
            'date' => $date,
            'start_time' => $start,
            'end_time' => $end,
        ];
    }
}
