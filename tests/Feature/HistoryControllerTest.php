<?php

namespace Tests\Feature;

use App\Exports\HistoryExport;
use App\Models\Booking;
use App\Models\Building;
use App\Models\Campus;
use App\Models\LogHistory;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HistoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_rooms_from_booking_schedules_and_filter_options(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $owner = User::factory()->create();
        [$firstRoom, $secondRoom] = $this->createRooms();
        $booking = $this->createBooking($owner, $firstRoom);

        $booking->roomSchedules()->createMany([
            [
                'room_id' => $firstRoom->id,
                'start_time' => '2026-06-20 08:00:00',
                'end_time' => '2026-06-20 10:00:00',
            ],
            [
                'room_id' => $secondRoom->id,
                'start_time' => '2026-06-21 08:00:00',
                'end_time' => '2026-06-21 10:00:00',
            ],
        ]);

        LogHistory::query()->create([
            'booking_id' => $booking->id,
            'user_id' => $admin->id,
            'action' => Booking::STATUS_APPROVED,
            'description' => 'Booking disetujui.',
        ]);

        $this->actingAs($admin)
            ->get(route('history.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('History/Index')
                ->has('histories', 1)
                ->where('histories.0.booking.room_summary', 'Ruang A, Ruang B')
                ->has('histories.0.booking.room_schedules', 2)
                ->where('actionOptions.0', Booking::STATUS_APPROVED)
                ->has('roomOptions', 2));
    }

    public function test_non_super_admin_cannot_access_history_or_export(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_BAP]);

        $this->actingAs($admin)
            ->get(route('history.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('history.export.excel'))
            ->assertForbidden();
    }

    public function test_history_export_filters_by_room_action_search_and_business_date(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $owner = User::factory()->create(['name' => 'Pemohon Seminar']);
        [$firstRoom, $secondRoom] = $this->createRooms();
        $firstBooking = $this->createBooking($owner, $firstRoom, 'Seminar Utama');
        $secondBooking = $this->createBooking($owner, $secondRoom, 'Rapat Lanjutan');

        $firstBooking->roomSchedules()->create([
            'room_id' => $firstRoom->id,
            'start_time' => '2026-06-20 08:00:00',
            'end_time' => '2026-06-20 10:00:00',
        ]);
        $secondBooking->roomSchedules()->create([
            'room_id' => $secondRoom->id,
            'start_time' => '2026-06-21 08:00:00',
            'end_time' => '2026-06-21 10:00:00',
        ]);

        $included = $this->createLog(
            $firstBooking,
            $admin,
            Booking::STATUS_APPROVED,
            '2026-06-19 16:30:00',
        );
        $this->createLog(
            $secondBooking,
            $admin,
            Booking::STATUS_APPROVED,
            '2026-06-19 17:30:00',
        );
        $this->createLog(
            $firstBooking,
            $admin,
            Booking::STATUS_REJECTED,
            '2026-06-19 15:00:00',
        );

        $export = new HistoryExport($admin, [
            'search' => 'Seminar',
            'action' => Booking::STATUS_APPROVED,
            'room_id' => $firstRoom->id,
            'start_date' => '2026-06-19',
            'end_date' => '2026-06-19',
        ]);

        $this->assertSame([$included->id], $export->collection()->pluck('id')->all());
        $this->assertSame(
            [$included->id],
            (new HistoryExport($admin, [
                'search' => 'Disetujui',
                'room_id' => $firstRoom->id,
                'start_date' => '2026-06-19',
                'end_date' => '2026-06-19',
            ]))->collection()->pluck('id')->all(),
        );

        $this->actingAs($admin)
            ->get(route('history.export.excel', [
                'search' => 'Seminar',
                'action' => Booking::STATUS_APPROVED,
                'room_id' => $firstRoom->id,
                'start_date' => '2026-06-19',
                'end_date' => '2026-06-19',
            ]))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    /**
     * @return array{Room, Room}
     */
    private function createRooms(): array
    {
        $campus = Campus::query()->create([
            'name' => 'Kampus Bekasi',
            'address' => 'Jl. Uji',
            'phone' => '021000000',
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id,
            'name' => 'Gedung Utama',
        ]);

        return [
            Room::query()->create([
                'building_id' => $building->id,
                'name' => 'Ruang A',
                'capacity' => 20,
                'is_available' => true,
            ]),
            Room::query()->create([
                'building_id' => $building->id,
                'name' => 'Ruang B',
                'capacity' => 30,
                'is_available' => true,
            ]),
        ];
    }

    private function createBooking(
        User $owner,
        Room $room,
        string $title = 'Booking Uji',
    ): Booking {
        return Booking::query()->create([
            'user_id' => $owner->id,
            'room_id' => $room->id,
            'title' => $title,
            'description' => 'Pengujian histori',
            'start_time' => '2026-06-20 08:00:00',
            'end_time' => '2026-06-20 10:00:00',
            'schedule_mode' => Booking::MODE_CONTINUOUS,
            'schedule_start_date' => '2026-06-20',
            'schedule_end_date' => '2026-06-20',
            'schedule_start_clock' => '08:00:00',
            'schedule_end_clock' => '10:00:00',
            'status' => Booking::STATUS_WAITING,
        ]);
    }

    private function createLog(
        Booking $booking,
        User $actor,
        string $action,
        string $createdAt,
    ): LogHistory {
        $log = LogHistory::query()->create([
            'booking_id' => $booking->id,
            'user_id' => $actor->id,
            'action' => $action,
            'description' => "Aksi {$action}.",
        ]);
        $log->timestamps = false;
        $log->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $log;
    }
}
