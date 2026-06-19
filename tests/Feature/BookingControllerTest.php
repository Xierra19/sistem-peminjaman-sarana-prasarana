<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRoomSchedule;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Room;
use App\Models\User;
use App\Notifications\BookingRequestedNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BookingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

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
        $response->assertJsonPath('bookings.0.status', Booking::STATUS_WAITING);
        $response->assertJsonPath('daily_bookings.0.date', $date);
    }

    public function test_booking_letter_displays_approval_time_in_business_timezone(): void
    {
        [$user, $rooms] = $this->createLocation(1);
        $booking = $this->createBooking($user, $rooms[0], [
            'status' => Booking::STATUS_APPROVED,
        ]);
        $booking->roomSchedules()->create([
            'room_id' => $rooms[0]->id,
            'start_time' => '2026-06-25 07:00:00',
            'end_time' => '2026-06-25 13:00:00',
        ]);
        $booking->load(['user', 'roomSchedules.room.building.campus']);

        $html = view('pdf.booking-letter', [
            'booking' => $booking,
            'generatedAt' => Carbon::parse('2026-06-19 02:58:00', 'UTC'),
            'approvedAt' => Carbon::parse('2026-06-19 02:58:00', 'UTC'),
        ])->render();

        $this->assertStringContainsString(
            'Disetujui pada 19 Juni 2026 09:58 WIB',
            $html,
        );
    }

    public function test_availability_excludes_booking_that_is_waiting_for_revision(): void
    {
        [$user, $rooms] = $this->createLocation(1);
        $date = now()->addDays(5)->toDateString();
        $booking = $this->createBooking($user, $rooms[0], [
            'status' => Booking::STATUS_NEEDS_REVISION,
        ]);
        $booking->roomSchedules()->create([
            'room_id' => $rooms[0]->id,
            'start_time' => "{$date} 09:00:00",
            'end_time' => "{$date} 11:00:00",
        ]);

        $this->actingAs($user)
            ->getJson(route('rooms.availability', [
                'room' => $rooms[0],
                'date' => $date,
            ]))
            ->assertOk()
            ->assertJsonPath('bookings', [])
            ->assertJsonPath('daily_bookings', []);
    }

    public function test_availability_includes_legacy_booking_without_room_schedule(): void
    {
        [$user, $rooms] = $this->createLocation(1);
        $date = now()->addDays(5)->toDateString();
        $this->createBooking($user, $rooms[0], [
            'start_time' => "{$date} 13:00:00",
            'end_time' => "{$date} 15:00:00",
        ]);

        $response = $this->actingAs($user)->getJson(route('rooms.availability', [
            'room' => $rooms[0],
            'date' => $date,
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('bookings.0.start', '13:00')
            ->assertJsonPath('bookings.0.end', '15:00')
            ->assertJsonPath('daily_bookings.0.date', $date);
    }

    public function test_availability_rejects_more_than_twenty_selected_dates(): void
    {
        [$user, $rooms] = $this->createLocation(1);
        $dates = collect(range(1, 21))
            ->map(fn (int $days) => now()->addDays($days)->toDateString())
            ->all();

        $this->actingAs($user)
            ->getJson(route('rooms.availability', [
                'room' => $rooms[0],
                'dates' => $dates,
            ]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Maksimal 20 tanggal dapat diperiksa sekaligus.')
            ->assertJsonPath('daily_bookings', []);
    }

    public function test_index_normalizes_invalid_query_parameters(): void
    {
        [$user, $rooms] = $this->createLocation(1);
        $this->createBooking($user, $rooms[0]);

        $response = $this->actingAs($user)->get(route('bookings.index', [
            'status' => 'not-a-status',
            'sort' => 'id desc; drop table bookings',
            'direction' => 'sideways',
            'per_page' => 100000,
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Bookings/Index')
            ->where('filters.status', null)
            ->where('filters.sort', 'created_at')
            ->where('filters.direction', 'desc')
            ->where('filters.per_page', 100)
            ->where('bookings.per_page', 100)
        );
    }

    public function test_index_supports_all_exposed_sort_columns(): void
    {
        [$user, $rooms] = $this->createLocation(1);
        $this->createBooking($user, $rooms[0]);

        foreach (['number', 'title', 'room', 'start_time', 'end_time', 'status', 'created_at'] as $sort) {
            $response = $this->actingAs($user)->get(route('bookings.index', [
                'sort' => $sort,
                'direction' => 'asc',
            ]));

            $response->assertOk();
            $response->assertInertia(fn (Assert $page) => $page
                ->component('Bookings/Index')
                ->where('filters.sort', $sort)
                ->where('filters.direction', 'asc')
            );
        }
    }

    public function test_waiting_filter_includes_legacy_approval_pending_statuses_but_excludes_revision(): void
    {
        [$user, $rooms] = $this->createLocation(1);

        foreach (Booking::APPROVAL_PENDING_STATUSES as $status) {
            $this->createBooking($user, $rooms[0], ['status' => $status]);
        }

        $this->createBooking($user, $rooms[0], [
            'status' => Booking::STATUS_NEEDS_REVISION,
        ]);

        $this->actingAs($user)
            ->get(route('bookings.index', ['status' => Booking::STATUS_WAITING]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Bookings/Index')
                ->where('filters.status', Booking::STATUS_WAITING)
                ->where('bookings.total', count(Booking::APPROVAL_PENDING_STATUSES))
            );
    }

    public function test_user_dashboard_separates_waiting_and_needs_revision_summaries(): void
    {
        [$user, $rooms] = $this->createLocation(1);

        foreach (Booking::APPROVAL_PENDING_STATUSES as $status) {
            $this->createBooking($user, $rooms[0], ['status' => $status]);
        }

        $this->createBooking($user, $rooms[0], [
            'status' => Booking::STATUS_NEEDS_REVISION,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('roomSummary.total', count(Booking::APPROVAL_PENDING_STATUSES) + 1)
                ->where('roomSummary.waiting', count(Booking::APPROVAL_PENDING_STATUSES))
                ->where('roomSummary.needs_revision', 1)
                ->where('combinedSummary.total', count(Booking::APPROVAL_PENDING_STATUSES) + 1)
            );
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

    public function test_new_booking_notifies_only_bap_admins(): void
    {
        Notification::fake();

        [$user, $rooms] = $this->createLocation(1);
        $bapAdmin = User::factory()->create(['role' => User::ROLE_ADMIN_BAP]);
        $sarprasAdmin = User::factory()->create(['role' => User::ROLE_ADMIN_SARPRAS]);
        $date = now()->addDays(5)->toDateString();

        $this->actingAs($user)->post(route('bookings.store'), [
            'title' => 'Seminar',
            'schedules' => [
                $this->schedulePayload($rooms[0], $date, '09:00', '11:00'),
            ],
        ])->assertRedirect(route('bookings.index'));

        Notification::assertSentTo($bapAdmin, BookingRequestedNotification::class);
        Notification::assertNotSentTo($sarprasAdmin, BookingRequestedNotification::class);
    }

    public function test_owner_can_cancel_waiting_booking_only_once(): void
    {
        [$owner, $rooms] = $this->createLocation(1);
        $booking = $this->createBooking($owner, $rooms[0]);

        $this->actingAs($owner)
            ->from(route('bookings.show', $booking))
            ->post(route('bookings.cancel', $booking))
            ->assertRedirect(route('bookings.show', $booking))
            ->assertSessionHas('success', 'Booking berhasil dibatalkan.');

        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
        $this->assertDatabaseHas('log_histories', [
            'booking_id' => $booking->id,
            'user_id' => $owner->id,
            'action' => Booking::STATUS_CANCELLED,
        ]);

        $this->actingAs($owner)
            ->from(route('bookings.show', $booking))
            ->post(route('bookings.cancel', $booking))
            ->assertSessionHas('error', 'Booking sudah dibatalkan sebelumnya.');

        $this->assertDatabaseCount('log_histories', 1);
    }

    public function test_cancelling_past_due_booking_expires_it_instead(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-10 00:01', 'Asia/Jakarta'));
        [$owner, $rooms] = $this->createLocation(1);
        $booking = $this->createBooking($owner, $rooms[0], [
            'start_time' => '2026-06-09 08:00:00',
            'end_time' => '2026-06-09 10:00:00',
            'schedule_start_date' => '2026-06-09',
            'schedule_end_date' => '2026-06-09',
        ]);

        $this->actingAs($owner)
            ->post(route('bookings.cancel', $booking))
            ->assertSessionHas('error', 'Permintaan sudah kedaluwarsa karena hari peminjaman terakhir telah berakhir.');

        $this->assertSame(Booking::STATUS_EXPIRED, $booking->fresh()->status);
        $this->assertDatabaseHas('log_histories', [
            'booking_id' => $booking->id,
            'action' => Booking::STATUS_EXPIRED,
        ]);
    }

    public function test_owner_cannot_cancel_approved_booking(): void
    {
        [$owner, $rooms] = $this->createLocation(1);
        $booking = $this->createBooking($owner, $rooms[0], [
            'status' => Booking::STATUS_APPROVED,
        ]);

        $this->actingAs($owner)
            ->post(route('bookings.cancel', $booking))
            ->assertSessionHas('error', 'Booking tidak dapat dibatalkan karena sudah diproses oleh admin.');

        $this->assertSame(Booking::STATUS_APPROVED, $booking->fresh()->status);
        $this->assertDatabaseMissing('log_histories', [
            'booking_id' => $booking->id,
            'action' => Booking::STATUS_CANCELLED,
        ]);
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

    public function test_store_returns_user_friendly_message_when_end_time_is_missing(): void
    {
        [$user, $rooms] = $this->createLocation(1);
        $date = now()->addDays(5)->toDateString();

        $response = $this->actingAs($user)
            ->from(route('bookings.create'))
            ->post(route('bookings.store'), [
                'title' => 'Jadwal Belum Lengkap',
                'schedules' => [[
                    'room_id' => $rooms[0]->id,
                    'dates' => [$date],
                    'start_time' => '20:30',
                    'end_time' => '',
                ]],
            ]);

        $response
            ->assertRedirect(route('bookings.create'))
            ->assertSessionHasErrors([
                'schedules.0.end_time' => 'Pilih jam selesai.',
            ]);

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_store_rejects_schedule_outside_operational_hours(): void
    {
        [$user, $rooms] = $this->createLocation(1);
        $date = now()->addDays(5)->toDateString();

        $response = $this->actingAs($user)
            ->from(route('bookings.create'))
            ->post(route('bookings.store'), [
                'title' => 'Di Luar Jam Operasional',
                'schedules' => [[
                    'room_id' => $rooms[0]->id,
                    'dates' => [$date],
                    'start_time' => '21:00',
                    'end_time' => '21:30',
                ]],
            ]);

        $response
            ->assertRedirect(route('bookings.create'))
            ->assertSessionHasErrors([
                'schedules.0.start_time' => 'Jam mulai paling akhir pukul 20:30.',
                'schedules.0.end_time' => 'Jam selesai paling akhir pukul 21:00.',
            ]);

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_store_allows_overlapping_request_when_existing_booking_is_still_waiting(): void
    {
        [$existingOwner, $rooms] = $this->createLocation(1);
        $requestingUser = User::factory()->create(['email_verified_at' => now()]);
        $date = now()->addDays(5)->toDateString();
        $existing = $this->createBooking($existingOwner, $rooms[0], [
            'status' => Booking::STATUS_WAITING,
            'start_time' => "{$date} 09:00:00",
            'end_time' => "{$date} 11:00:00",
        ]);
        $existing->roomSchedules()->create([
            'room_id' => $rooms[0]->id,
            'start_time' => "{$date} 09:00:00",
            'end_time' => "{$date} 11:00:00",
        ]);

        $response = $this->actingAs($requestingUser)->post(route('bookings.store'), [
            'title' => 'Pengajuan Paralel',
            'schedules' => [
                $this->schedulePayload($rooms[0], $date, '10:00', '12:00'),
            ],
        ]);

        $response->assertRedirect(route('bookings.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('bookings', 2);
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
        Storage::fake('local');

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
                'attachment' => UploadedFile::fake()->create('surat.pdf', 100, 'application/pdf'),
                'schedules' => [
                    $this->schedulePayload($rooms[1], $date, '08:00', '09:00'),
                    $this->schedulePayload($rooms[0], $date, '10:00', '12:00'),
                ],
            ]);

        $response->assertRedirect(route('bookings.create'));
        $response->assertSessionHasErrors('schedules.1.room_id');
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('booking_room_schedules', 1);
        Storage::disk('local')->assertDirectoryEmpty('attachments');
    }

    public function test_sarpras_admin_cannot_access_user_facing_booking_routes(): void
    {
        Storage::fake('local');

        [$owner, $rooms] = $this->createLocation(1);
        $sarprasAdmin = User::factory()->create([
            'role' => User::ROLE_ADMIN_SARPRAS,
            'email_verified_at' => now(),
        ]);
        $attachment = 'attachments/booking.pdf';
        Storage::disk('local')->put($attachment, 'booking');
        $booking = $this->createBooking($owner, $rooms[0], [
            'status' => 'approved',
            'attachment' => $attachment,
        ]);

        $this->actingAs($sarprasAdmin)
            ->get(route('bookings.show', $booking))
            ->assertForbidden();
        $this->actingAs($sarprasAdmin)
            ->get(route('bookings.attachment', $booking))
            ->assertForbidden();
        $this->actingAs($sarprasAdmin)
            ->get(route('bookings.letter', $booking))
            ->assertForbidden();
    }

    public function test_bap_admin_can_access_user_facing_booking_routes(): void
    {
        Storage::fake('local');

        [$owner, $rooms] = $this->createLocation(1);
        $bapAdmin = User::factory()->create([
            'role' => User::ROLE_ADMIN_BAP,
            'email_verified_at' => now(),
        ]);
        $attachment = 'attachments/booking.pdf';
        Storage::disk('local')->put($attachment, 'booking');
        $booking = $this->createBooking($owner, $rooms[0], [
            'attachment' => $attachment,
        ]);

        $this->actingAs($bapAdmin)
            ->get(route('bookings.show', $booking))
            ->assertOk();
        $this->actingAs($bapAdmin)
            ->get(route('bookings.attachment', $booking))
            ->assertDownload('booking.pdf');
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
