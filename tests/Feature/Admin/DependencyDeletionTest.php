<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Item;
use App\Models\ItemBorrowing;
use App\Models\ItemBorrowingItem;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DependencyDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_campus_index_exposes_building_count_and_blocks_deletion_when_dependencies_exist(): void
    {
        $admin = $this->superAdmin();
        $campus = Campus::create([
            'name' => 'Kampus Utama',
            'address' => 'Jl. Contoh',
            'phone' => '081234567890',
        ]);
        Building::create([
            'campus_id' => $campus->id,
            'name' => 'Gedung A',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.campus.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Campus/Index')
                ->where('campuses.0.buildings_count', 1)
            );

        $this->actingAs($admin)
            ->delete(route('admin.campus.destroy', $campus))
            ->assertRedirect(route('admin.campus.index'))
            ->assertSessionHas('error', 'Campus tidak dapat dihapus karena masih memiliki gedung terkait. Hapus gedung terlebih dahulu.');

        $this->assertDatabaseHas('campuses', ['id' => $campus->id]);
    }

    public function test_building_index_exposes_room_count_and_blocks_deletion_when_dependencies_exist(): void
    {
        $admin = $this->superAdmin();
        $campus = $this->createCampus();
        $building = Building::create([
            'campus_id' => $campus->id,
            'name' => 'Gedung A',
        ]);
        Room::create([
            'building_id' => $building->id,
            'name' => 'Ruang 101',
            'capacity' => 30,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.buildings.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Buildings/Index')
                ->where('buildings.0.rooms_count', 1)
            );

        $this->actingAs($admin)
            ->delete(route('admin.buildings.destroy', $building))
            ->assertRedirect(route('admin.buildings.index'))
            ->assertSessionHas('error', 'Gedung tidak dapat dihapus karena masih memiliki ruangan terkait. Hapus ruangan terlebih dahulu.');

        $this->assertDatabaseHas('buildings', ['id' => $building->id]);
    }

    public function test_room_index_exposes_booking_count_and_blocks_deletion_when_dependencies_exist(): void
    {
        $admin = $this->superAdmin();
        $user = User::factory()->create();
        $room = $this->createRoom();
        Booking::create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'title' => 'Rapat',
            'description' => null,
            'start_time' => now()->addDay()->setTime(9, 0),
            'end_time' => now()->addDay()->setTime(11, 0),
            'status' => Booking::STATUS_WAITING,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.rooms.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Rooms/Index')
                ->where('rooms.0.bookings_count', 1)
            );

        $this->actingAs($admin)
            ->delete(route('admin.rooms.destroy', $room))
            ->assertRedirect(route('admin.rooms.index'))
            ->assertSessionHas('error', 'Ruangan tidak dapat dihapus karena masih memiliki booking terkait. Selesaikan atau pindahkan booking terlebih dahulu.');

        $this->assertDatabaseHas('rooms', ['id' => $room->id]);
    }

    public function test_item_index_exposes_dependency_counts_and_blocks_both_legacy_and_pivot_borrowings(): void
    {
        $admin = $this->superAdmin();
        $user = User::factory()->create();

        $legacyItem = Item::create([
            'code' => 'BRG-001',
            'name' => 'A Proyektor',
            'category' => 'Elektronik',
            'quantity' => 1,
            'is_available' => true,
        ]);
        ItemBorrowing::create([
            'user_id' => $user->id,
            'item_id' => $legacyItem->id,
            'title' => 'Peminjaman Proyektor',
            'description' => null,
            'status' => ItemBorrowing::STATUS_WAITING,
            'quantity' => 1,
            'borrow_date' => now()->addDay(),
            'return_date' => now()->addDays(2),
        ]);

        $pivotItem = Item::create([
            'code' => 'BRG-002',
            'name' => 'Z Laptop',
            'category' => 'Elektronik',
            'quantity' => 1,
            'is_available' => true,
        ]);
        $borrowing = ItemBorrowing::create([
            'user_id' => $user->id,
            'title' => 'Peminjaman Laptop',
            'description' => null,
            'status' => ItemBorrowing::STATUS_WAITING,
        ]);
        ItemBorrowingItem::create([
            'item_borrowing_id' => $borrowing->id,
            'item_id' => $pivotItem->id,
            'quantity' => 1,
            'borrow_date' => now()->addDay(),
            'return_date' => now()->addDays(2),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.items.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Items/Index')
                ->where('items.0.item_borrowings_count', 1)
                ->where('items.0.borrowing_items_count', 0)
                ->where('items.1.item_borrowings_count', 0)
                ->where('items.1.borrowing_items_count', 1)
            );

        $this->actingAs($admin)
            ->delete(route('admin.items.destroy', $legacyItem))
            ->assertRedirect(route('admin.items.index'))
            ->assertSessionHas('error', 'Barang tidak dapat dihapus karena masih memiliki riwayat peminjaman. Selesaikan atau hapus data peminjaman terlebih dahulu.');

        $this->actingAs($admin)
            ->delete(route('admin.items.destroy', $pivotItem))
            ->assertRedirect(route('admin.items.index'))
            ->assertSessionHas('error', 'Barang tidak dapat dihapus karena masih memiliki riwayat peminjaman. Selesaikan atau hapus data peminjaman terlebih dahulu.');

        $this->assertDatabaseHas('items', ['id' => $legacyItem->id]);
        $this->assertDatabaseHas('items', ['id' => $pivotItem->id]);
    }

    private function superAdmin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
        ]);
    }

    private function createCampus(): Campus
    {
        return Campus::create([
            'name' => 'Kampus Uji',
            'address' => 'Jl. Uji Coba',
            'phone' => '081234567890',
        ]);
    }

    private function createRoom(): Room
    {
        $campus = $this->createCampus();
        $building = Building::create([
            'campus_id' => $campus->id,
            'name' => 'Gedung Uji',
        ]);

        return Room::create([
            'building_id' => $building->id,
            'name' => 'Ruang Uji',
            'capacity' => 20,
        ]);
    }
}
