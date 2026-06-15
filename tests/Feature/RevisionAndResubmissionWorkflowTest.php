<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Item;
use App\Models\ItemBorrowing;
use App\Models\Room;
use App\Models\User;
use App\Notifications\BookingRequestedNotification;
use App\Notifications\BookingStatusUpdatedNotification;
use App\Notifications\ItemBorrowingRequestedNotification;
use App\Notifications\ItemBorrowingStatusUpdatedNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RevisionAndResubmissionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_booking_revision_keeps_the_record_and_uses_the_original_submission_cutoff(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 10:00', 'Asia/Jakarta'));
        Notification::fake();

        $owner = User::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_BAP]);
        $room = $this->createRoom();
        $booking = $this->createBooking($owner, $room, Booking::STATUS_WAITING);
        $booking->forceFill(['created_at' => '2026-06-10 08:00:00'])->saveQuietly();

        $this->actingAs($admin)
            ->post(route('admin.bookings.update-status', $booking), [
                'status' => Booking::STATUS_NEEDS_REVISION,
                'notes' => 'Perjelas keperluan kegiatan.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(Booking::STATUS_NEEDS_REVISION, $booking->fresh()->status);
        Notification::assertSentTo($owner, BookingStatusUpdatedNotification::class);

        $this->actingAs($owner)
            ->put(route('bookings.update', $booking), [
                'title' => 'Booking hasil revisi',
                'description' => 'Keperluan sudah diperjelas.',
                'schedules' => [[
                    'room_id' => $room->id,
                    'dates' => ['2026-06-16'],
                    'start_time' => '08:00',
                    'end_time' => '10:00',
                ]],
            ])
            ->assertRedirect(route('bookings.show', $booking))
            ->assertSessionHasNoErrors();

        $booking->refresh();

        $this->assertSame(Booking::STATUS_WAITING, $booking->status);
        $this->assertSame('Booking hasil revisi', $booking->title);
        $this->assertDatabaseHas('log_histories', [
            'booking_id' => $booking->id,
            'user_id' => $owner->id,
            'action' => 'revised',
        ]);
        Notification::assertSentTo($admin, BookingRequestedNotification::class);
    }

    public function test_rejected_booking_is_resubmitted_as_a_new_record(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 10:00', 'Asia/Jakarta'));
        Notification::fake();

        $owner = User::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_BAP]);
        $room = $this->createRoom();
        $source = $this->createBooking($owner, $room, Booking::STATUS_REJECTED);

        $this->actingAs($owner)
            ->post(route('bookings.store'), [
                'title' => 'Booking diajukan ulang',
                'description' => 'Sudah diperbaiki.',
                'resubmitted_from_id' => $source->id,
                'schedules' => [[
                    'room_id' => $room->id,
                    'dates' => ['2026-06-18'],
                    'start_time' => '13:00',
                    'end_time' => '15:00',
                ]],
            ])
            ->assertRedirect(route('bookings.index'))
            ->assertSessionHasNoErrors();

        $newBooking = Booking::query()
            ->where('resubmitted_from_id', $source->id)
            ->firstOrFail();

        $this->assertNotSame($source->id, $newBooking->id);
        $this->assertSame(Booking::STATUS_REJECTED, $source->fresh()->status);
        $this->assertSame(Booking::STATUS_WAITING, $newBooking->status);
        Notification::assertSentTo($admin, BookingRequestedNotification::class);
    }

    public function test_emergency_cancelled_booking_is_resubmitted_as_a_new_record(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 10:00', 'Asia/Jakarta'));
        Notification::fake();

        $owner = User::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_BAP]);
        $room = $this->createRoom();
        $source = $this->createBooking($owner, $room, Booking::STATUS_CANCELLED);
        $source->update([
            'letter_sequence' => 1,
            'letter_number' => '1/BAP-Bekasi/Booking/06/2026',
            'letter_generated_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get(route('bookings.resubmit', $source))
            ->assertOk();

        $this->actingAs($owner)
            ->post(route('bookings.store'), [
                'title' => 'Booking setelah pembatalan darurat',
                'description' => 'Diajukan kembali dengan jadwal baru.',
                'resubmitted_from_id' => $source->id,
                'schedules' => [[
                    'room_id' => $room->id,
                    'dates' => ['2026-06-18'],
                    'start_time' => '13:00',
                    'end_time' => '15:00',
                ]],
            ])
            ->assertRedirect(route('bookings.index'))
            ->assertSessionHasNoErrors();

        $newBooking = Booking::query()
            ->where('resubmitted_from_id', $source->id)
            ->firstOrFail();

        $this->assertNotSame($source->id, $newBooking->id);
        $this->assertSame(Booking::STATUS_CANCELLED, $source->fresh()->status);
        $this->assertSame(Booking::STATUS_WAITING, $newBooking->status);
        Notification::assertSentTo($admin, BookingRequestedNotification::class);
    }

    public function test_item_revision_returns_to_waiting_and_notifies_admin_again(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 10:00', 'Asia/Jakarta'));
        Notification::fake();

        $owner = User::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_SARPRAS]);
        $item = $this->createItem();
        $borrowing = $this->createItemBorrowing(
            $owner,
            $item,
            ItemBorrowing::STATUS_WAITING,
        );
        $borrowing->forceFill(['created_at' => '2026-06-01 08:00:00'])->saveQuietly();

        $this->actingAs($admin)
            ->post(route('admin.item-borrowings.update-status', $borrowing), [
                'status' => ItemBorrowing::STATUS_NEEDS_REVISION,
                'notes' => 'Kurangi jumlah barang.',
            ])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($owner, ItemBorrowingStatusUpdatedNotification::class);

        $row = $borrowing->items()->firstOrFail();
        $this->actingAs($owner)
            ->put(route('item-borrowings.update', $borrowing), [
                'title' => 'Peminjaman barang revisi',
                'items' => [[
                    'id' => $row->id,
                    'item_id' => $item->id,
                    'quantity' => 1,
                    'borrow_date' => '2026-06-16',
                    'borrow_time' => '08:00',
                    'return_date' => '2026-06-16',
                    'return_time' => '10:00',
                ]],
            ])
            ->assertRedirect(route('item-borrowings.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(ItemBorrowing::STATUS_WAITING, $borrowing->fresh()->status);
        $this->assertDatabaseHas('item_borrowing_logs', [
            'item_borrowing_id' => $borrowing->id,
            'user_id' => $owner->id,
            'action' => 'revised',
        ]);
        Notification::assertSentTo($admin, ItemBorrowingRequestedNotification::class);
    }

    public function test_rejected_item_borrowing_is_resubmitted_with_its_existing_attachment(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 10:00', 'Asia/Jakarta'));
        Notification::fake();
        Storage::fake('public');

        $owner = User::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_SARPRAS]);
        $item = $this->createItem();
        $source = $this->createItemBorrowing(
            $owner,
            $item,
            ItemBorrowing::STATUS_REJECTED,
        );
        $attachment = 'item-borrowing-attachments/source.pdf';
        Storage::disk('public')->put($attachment, 'source');
        $source->update(['attachment' => $attachment]);

        $this->actingAs($owner)
            ->post(route('item-borrowings.store'), [
                'title' => 'Peminjaman diajukan ulang',
                'resubmitted_from_id' => $source->id,
                'items' => [[
                    'item_id' => $item->id,
                    'quantity' => 1,
                    'borrow_date' => '2026-06-22',
                    'borrow_time' => '08:00',
                    'return_date' => '2026-06-22',
                    'return_time' => '10:00',
                ]],
            ])
            ->assertRedirect(route('item-borrowings.index'))
            ->assertSessionHasNoErrors();

        $newBorrowing = ItemBorrowing::query()
            ->where('resubmitted_from_id', $source->id)
            ->firstOrFail();

        $this->assertNotSame($source->id, $newBorrowing->id);
        $this->assertSame(ItemBorrowing::STATUS_REJECTED, $source->fresh()->status);
        $this->assertSame(ItemBorrowing::STATUS_WAITING, $newBorrowing->status);
        $this->assertSame($attachment, $newBorrowing->attachment);
        Storage::disk('public')->assertExists($attachment);
        Notification::assertSentTo($admin, ItemBorrowingRequestedNotification::class);
    }

    private function createRoom(): Room
    {
        $campus = Campus::query()->create([
            'name' => 'Kampus Revisi',
            'address' => 'Jl. Revisi',
            'phone' => '021000000',
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id,
            'name' => 'Gedung Revisi',
        ]);

        return Room::query()->create([
            'building_id' => $building->id,
            'name' => 'Ruang Revisi',
            'capacity' => 20,
            'is_available' => true,
        ]);
    }

    private function createBooking(User $owner, Room $room, string $status): Booking
    {
        $booking = Booking::query()->create([
            'user_id' => $owner->id,
            'room_id' => $room->id,
            'title' => 'Booking awal',
            'description' => 'Deskripsi awal',
            'start_time' => '2026-06-16 08:00:00',
            'end_time' => '2026-06-16 10:00:00',
            'schedule_mode' => Booking::MODE_CONTINUOUS,
            'schedule_start_date' => '2026-06-16',
            'schedule_end_date' => '2026-06-16',
            'schedule_start_clock' => '08:00:00',
            'schedule_end_clock' => '10:00:00',
            'status' => $status,
        ]);

        $booking->roomSchedules()->create([
            'room_id' => $room->id,
            'start_time' => '2026-06-16 08:00:00',
            'end_time' => '2026-06-16 10:00:00',
        ]);

        return $booking;
    }

    private function createItem(): Item
    {
        return Item::query()->create([
            'code' => 'ITM-REV',
            'name' => 'Proyektor Revisi',
            'category' => 'Elektronik',
            'quantity' => 10,
            'is_available' => true,
        ]);
    }

    private function createItemBorrowing(
        User $owner,
        Item $item,
        string $status,
    ): ItemBorrowing {
        $borrowing = ItemBorrowing::query()->create([
            'user_id' => $owner->id,
            'title' => 'Peminjaman awal',
            'description' => 'Deskripsi awal',
            'status' => $status,
            'item_id' => null,
            'quantity' => 0,
            'borrow_date' => null,
            'return_date' => null,
        ]);

        $borrowing->items()->create([
            'item_id' => $item->id,
            'quantity' => 2,
            'borrow_date' => Carbon::parse('2026-06-16 08:00', 'Asia/Jakarta')->utc(),
            'return_date' => Carbon::parse('2026-06-16 10:00', 'Asia/Jakarta')->utc(),
        ]);

        return $borrowing;
    }
}
