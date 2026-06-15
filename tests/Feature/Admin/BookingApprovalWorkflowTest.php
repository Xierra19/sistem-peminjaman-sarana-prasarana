<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Room;
use App\Models\User;
use App\Notifications\BookingStatusUpdatedNotification;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PDOException;
use Tests\TestCase;

class BookingApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_approval_assigns_monthly_letter_number_logs_and_notifies_owner(): void
    {
        Carbon::setTestNow('2026-06-14 10:00:00');
        Notification::fake();

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_BAP,
        ]);
        $owner = User::factory()->create();
        $booking = $this->createBooking($owner);

        $response = $this->actingAs($admin)->post(
            route('admin.bookings.update-status', $booking),
            [
                'status' => Booking::STATUS_APPROVED,
                'notes' => 'Jadwal tersedia.',
            ],
        );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $booking->refresh();

        $this->assertSame(Booking::STATUS_APPROVED, $booking->status);
        $this->assertSame(1, $booking->letter_sequence);
        $this->assertSame('1/BAP-Bekasi/Booking/06/2026', $booking->letter_number);
        $this->assertDatabaseHas('log_histories', [
            'booking_id' => $booking->id,
            'user_id' => $admin->id,
            'action' => Booking::STATUS_APPROVED,
            'description' => 'Booking disetujui - Jadwal tersedia.',
        ]);
        Notification::assertSentTo(
            $owner,
            BookingStatusUpdatedNotification::class,
        );
    }

    public function test_monthly_letter_sequence_increments_and_invalid_reapproval_has_no_side_effects(): void
    {
        Carbon::setTestNow('2026-06-14 10:00:00');
        Notification::fake();

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_BAP,
        ]);
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $firstBooking = $this->createBooking($firstOwner);
        $secondBooking = $this->createBooking($secondOwner);

        $this->actingAs($admin)->post(
            route('admin.bookings.update-status', $firstBooking),
            ['status' => Booking::STATUS_APPROVED],
        )->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(
            route('admin.bookings.update-status', $secondBooking),
            ['status' => Booking::STATUS_APPROVED],
        )->assertSessionHasNoErrors();

        $this->assertSame(1, $firstBooking->fresh()->letter_sequence);
        $this->assertSame(2, $secondBooking->fresh()->letter_sequence);
        $this->assertDatabaseCount('log_histories', 2);
        Notification::assertSentToTimes($firstOwner, BookingStatusUpdatedNotification::class, 1);

        $response = $this->actingAs($admin)->post(
            route('admin.bookings.update-status', $firstBooking),
            ['status' => Booking::STATUS_APPROVED],
        );

        $response->assertSessionHasErrors('status');
        $this->assertSame(1, $firstBooking->fresh()->letter_sequence);
        $this->assertDatabaseCount('log_histories', 2);
        Notification::assertSentToTimes($firstOwner, BookingStatusUpdatedNotification::class, 1);
    }

    public function test_database_rejects_duplicate_booking_letter_numbers(): void
    {
        $firstBooking = $this->createBooking(User::factory()->create());
        $secondBooking = $this->createBooking(User::factory()->create());
        $letterNumber = '1/BAP-Bekasi/Booking/06/2026';

        $firstBooking->update([
            'letter_sequence' => 1,
            'letter_number' => $letterNumber,
            'letter_generated_at' => now(),
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        $secondBooking->update([
            'letter_sequence' => 1,
            'letter_number' => $letterNumber,
            'letter_generated_at' => now(),
        ]);
    }

    public function test_letter_number_collision_retries_the_whole_approval_transaction(): void
    {
        Carbon::setTestNow('2026-06-14 10:00:00');
        Notification::fake();

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_BAP,
        ]);
        $owner = User::factory()->create();
        $booking = $this->createBooking($owner);
        $collisions = 0;
        $originalDispatcher = clone Booking::getEventDispatcher();

        try {
            Booking::updating(function (Booking $updatingBooking) use (&$collisions): void {
                if (! $updatingBooking->isDirty('letter_number') || $collisions > 0) {
                    return;
                }

                $collisions++;
                $previous = new PDOException(
                    'UNIQUE constraint failed: bookings.letter_number',
                    23000,
                );

                throw new UniqueConstraintViolationException(
                    'sqlite',
                    'update "bookings" set "letter_number" = ?',
                    [$updatingBooking->letter_number],
                    $previous,
                );
            });

            $response = $this->actingAs($admin)->post(
                route('admin.bookings.update-status', $booking),
                ['status' => Booking::STATUS_APPROVED],
            );
        } finally {
            Booking::setEventDispatcher($originalDispatcher);
        }

        $response->assertSessionHasNoErrors();
        $this->assertSame(1, $collisions);
        $this->assertSame(Booking::STATUS_APPROVED, $booking->fresh()->status);
        $this->assertSame('1/BAP-Bekasi/Booking/06/2026', $booking->fresh()->letter_number);
        $this->assertDatabaseCount('log_histories', 1);
        Notification::assertSentToTimes($owner, BookingStatusUpdatedNotification::class, 1);
    }

    private function createBooking(User $owner): Booking
    {
        $campus = Campus::query()->firstOrCreate(
            ['name' => 'Kampus Uji'],
            [
                'address' => 'Jl. Uji',
                'phone' => '021000000',
            ],
        );
        $building = Building::query()->firstOrCreate(
            [
                'campus_id' => $campus->id,
                'name' => 'Gedung Uji',
            ],
        );
        $room = Room::query()->firstOrCreate(
            [
                'building_id' => $building->id,
                'name' => 'Ruang Uji',
            ],
            [
                'capacity' => 20,
                'is_available' => true,
            ],
        );

        return Booking::query()->create([
            'user_id' => $owner->id,
            'room_id' => $room->id,
            'title' => 'Booking Uji',
            'description' => 'Pengujian persetujuan admin',
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
}
