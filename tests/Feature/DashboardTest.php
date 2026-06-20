<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Item;
use App\Models\ItemBorrowing;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_dashboard_combines_only_the_authenticated_users_requests(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-19 10:00', 'Asia/Jakarta'));

        [$user, $room] = $this->createLocation();
        $otherUser = User::factory()->create(['email_verified_at' => now()]);
        $item = $this->createItem();

        $this->createBooking($user, $room, Booking::STATUS_WAITING, now()->subMinutes(3));
        $this->createBooking($user, $room, Booking::STATUS_NEEDS_REVISION, now()->subMinutes(2));
        $this->createBooking($otherUser, $room, Booking::STATUS_APPROVED, now());

        $this->createItemBorrowing(
            $user,
            $item,
            ItemBorrowing::STATUS_WAITING,
            now()->subMinute(),
        );
        $this->createItemBorrowing(
            $user,
            $item,
            ItemBorrowing::STATUS_RETURNED,
            now(),
        );
        $this->createItemBorrowing(
            $otherUser,
            $item,
            ItemBorrowing::STATUS_APPROVED,
            now(),
        );

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('roomSummary.total', 2)
                ->where('roomSummary.waiting', 1)
                ->where('roomSummary.needs_revision', 1)
                ->where('itemSummary.total', 2)
                ->where('itemSummary.waiting', 1)
                ->where('itemSummary.completed', 1)
                ->where('combinedSummary.total', 4)
                ->has('requestHistory', 4)
                ->has('requestHistory.0.type')
                ->has('requestHistory.0.status')
                ->has('requestHistory.0.resource_name')
                ->has('items', 1)
            );
    }

    public function test_dashboard_result_links_can_prefill_room_and_item_forms(): void
    {
        [$user, $room] = $this->createLocation();
        $item = $this->createItem(quantity: 5);

        $this->actingAs($user)
            ->get(route('bookings.create', [
                'room_id' => $room->id,
                'date' => '2026-06-25',
                'start_time' => '09:00',
                'end_time' => '11:00',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Bookings/Create')
                ->where('initialData.schedules.0.room_id', $room->id)
                ->where('initialData.schedules.0.dates.0', '2026-06-25')
                ->where('initialData.schedules.0.start_time', '09:00')
                ->where('initialData.schedules.0.end_time', '11:00')
            );

        $this->actingAs($user)
            ->get(route('item-borrowings.create', [
                'item_id' => $item->id,
                'quantity' => 3,
                'date' => '2026-06-28',
                'start_time' => '08:00',
                'end_time' => '10:00',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ItemBorrowings/Create')
                ->where('initialData.items.0.item_id', $item->id)
                ->where('initialData.items.0.quantity', 3)
                ->where('initialData.items.0.dates.0', '2026-06-28')
                ->where('initialData.items.0.start_time', '08:00')
                ->where('initialData.items.0.end_time', '10:00')
            );
    }

    public function test_dashboard_request_history_is_not_limited_to_five_entries(): void
    {
        [$user, $room] = $this->createLocation();

        foreach (range(1, 7) as $minutesAgo) {
            $this->createBooking(
                $user,
                $room,
                Booking::STATUS_WAITING,
                now()->subMinutes($minutesAgo),
            );
        }

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('requestHistory', 7)
                ->where('combinedSummary.total', 7)
            );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function createLocation(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $campus = Campus::query()->create([
            'name' => 'Kampus Jakarta',
            'address' => 'Jalan Kampus',
            'phone' => '021000000',
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id,
            'name' => 'Gedung Utama',
        ]);
        $room = Room::query()->create([
            'building_id' => $building->id,
            'name' => 'Auditorium',
            'capacity' => 100,
            'is_available' => true,
        ]);

        return [$user, $room];
    }

    private function createItem(int $quantity = 10): Item
    {
        return Item::query()->create([
            'code' => 'ITM-DASHBOARD',
            'name' => 'Proyektor',
            'category' => 'Elektronik',
            'quantity' => $quantity,
            'is_available' => true,
        ]);
    }

    private function createBooking(
        User $user,
        Room $room,
        string $status,
        Carbon $createdAt,
    ): Booking {
        return Booking::query()->create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'title' => 'Kegiatan Mahasiswa',
            'start_time' => '2026-06-25 09:00:00',
            'end_time' => '2026-06-25 11:00:00',
            'status' => $status,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function createItemBorrowing(
        User $user,
        Item $item,
        string $status,
        Carbon $createdAt,
    ): ItemBorrowing {
        $borrowing = ItemBorrowing::query()->create([
            'user_id' => $user->id,
            'title' => 'Perlengkapan Acara',
            'status' => $status,
            'item_id' => null,
            'quantity' => 0,
            'borrow_date' => null,
            'return_date' => null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $borrowing->items()->create([
            'item_id' => $item->id,
            'quantity' => 2,
            'borrow_date' => Carbon::parse('2026-06-28 08:00', 'Asia/Jakarta')->utc(),
            'return_date' => Carbon::parse('2026-06-28 10:00', 'Asia/Jakarta')->utc(),
        ]);

        return $borrowing;
    }
}
