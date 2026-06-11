<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemBorrowing;
use App\Models\ItemBorrowingItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ItemBorrowingScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_user_can_submit_any_time_on_the_h_minus_seven_calendar_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-13 20:00', 'Asia/Jakarta')->utc());
        Storage::fake('public');
        Notification::fake();

        $user = User::factory()->create();
        $item = $this->createItem();

        $response = $this->actingAs($user)->post(route('item-borrowings.store'), [
            'title' => 'Seminar',
            'description' => 'Kegiatan seminar.',
            'attachment' => UploadedFile::fake()->create('surat.pdf', 100, 'application/pdf'),
            'items' => [[
                'item_id' => $item->id,
                'quantity' => 2,
                'borrow_date' => '2026-06-20',
                'borrow_time' => '08:00',
                'return_date' => '2026-06-20',
                'return_time' => '10:00',
            ]],
        ]);

        $response->assertRedirect(route('item-borrowings.index'));
        $response->assertSessionHasNoErrors();

        $detail = ItemBorrowingItem::query()->firstOrFail();

        $this->assertSame('2026-06-20 08:00', $detail->borrow_date->timezone('Asia/Jakarta')->format('Y-m-d H:i'));
        $this->assertSame('2026-06-20 10:00', $detail->return_date->timezone('Asia/Jakarta')->format('Y-m-d H:i'));
    }

    public function test_user_cannot_submit_after_the_h_minus_seven_calendar_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-14 00:01', 'Asia/Jakarta')->utc());
        Storage::fake('public');
        Notification::fake();

        $user = User::factory()->create();
        $item = $this->createItem();

        $response = $this->actingAs($user)
            ->from(route('item-borrowings.create'))
            ->post(route('item-borrowings.store'), [
                'title' => 'Seminar',
                'attachment' => UploadedFile::fake()->create('surat.pdf', 100, 'application/pdf'),
                'items' => [[
                    'item_id' => $item->id,
                    'quantity' => 1,
                    'borrow_date' => '2026-06-20',
                    'borrow_time' => '08:00',
                    'return_date' => '2026-06-20',
                    'return_time' => '10:00',
                ]],
            ]);

        $response->assertRedirect(route('item-borrowings.create'));
        $response->assertSessionHasErrors('items.0.borrow_date');
        $this->assertDatabaseCount('item_borrowings', 0);
    }

    public function test_adjacent_time_slots_do_not_reduce_availability(): void
    {
        $user = User::factory()->create();
        $item = $this->createItem(quantity: 5);

        $this->createApprovedBorrowing(
            $user,
            $item,
            '2026-06-20 08:00',
            '2026-06-20 10:00',
            3,
        );

        $response = $this->actingAs($user)->getJson(route('items.availability', [
            'item' => $item,
            'borrow_date' => '2026-06-20',
            'borrow_time' => '10:00',
            'return_date' => '2026-06-20',
            'return_time' => '12:00',
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('reserved_quantity', 0)
            ->assertJsonPath('remaining_quantity', 5);
    }

    public function test_overlapping_time_slots_reduce_availability(): void
    {
        $user = User::factory()->create();
        $item = $this->createItem(quantity: 5);

        $this->createApprovedBorrowing(
            $user,
            $item,
            '2026-06-20 08:00',
            '2026-06-20 10:00',
            3,
        );

        $response = $this->actingAs($user)->getJson(route('items.availability', [
            'item' => $item,
            'borrow_date' => '2026-06-20',
            'borrow_time' => '09:00',
            'return_date' => '2026-06-20',
            'return_time' => '11:00',
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('reserved_quantity', 3)
            ->assertJsonPath('remaining_quantity', 2);
    }

    public function test_approved_borrowing_becomes_completed_after_its_latest_return_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-20 10:01', 'Asia/Jakarta')->utc());

        $user = User::factory()->create();
        $item = $this->createItem();
        $borrowing = $this->createApprovedBorrowing(
            $user,
            $item,
            '2026-06-20 08:00',
            '2026-06-20 10:00',
            1,
        );

        $borrowing->load('items');

        $this->assertSame('approved', $borrowing->status);
        $this->assertSame('completed', $borrowing->effective_status);
    }

    public function test_admin_cannot_manually_mark_item_borrowing_as_returned(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_SARPRAS]);
        $user = User::factory()->create();
        $item = $this->createItem();
        $borrowing = $this->createApprovedBorrowing(
            $user,
            $item,
            '2026-06-20 08:00',
            '2026-06-20 10:00',
            1,
        );

        $response = $this->actingAs($admin)
            ->post(route('admin.item-borrowings.update-status', $borrowing), [
                'status' => 'returned',
            ]);

        $response->assertSessionHasErrors('status');
        $this->assertSame('approved', $borrowing->fresh()->status);
    }

    public function test_admin_cannot_cancel_a_completed_borrowing(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-20 10:01', 'Asia/Jakarta')->utc());

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_SARPRAS]);
        $user = User::factory()->create();
        $item = $this->createItem();
        $borrowing = $this->createApprovedBorrowing(
            $user,
            $item,
            '2026-06-20 08:00',
            '2026-06-20 10:00',
            1,
        );

        $response = $this->actingAs($admin)
            ->post(route('admin.item-borrowings.update-status', $borrowing), [
                'status' => 'cancelled',
                'notes' => 'Tidak boleh dibatalkan setelah selesai.',
            ]);

        $response->assertSessionHasErrors('status');
        $this->assertSame('approved', $borrowing->fresh()->status);
    }

    private function createItem(int $quantity = 10): Item
    {
        return Item::query()->create([
            'code' => 'ITM-'.fake()->unique()->numerify('####'),
            'name' => 'Proyektor',
            'category' => 'Elektronik',
            'quantity' => $quantity,
            'is_available' => true,
        ]);
    }

    private function createApprovedBorrowing(
        User $user,
        Item $item,
        string $borrowAt,
        string $returnAt,
        int $quantity,
    ): ItemBorrowing {
        $borrowing = ItemBorrowing::query()->create([
            'user_id' => $user->id,
            'title' => 'Peminjaman Barang',
            'status' => 'approved',
            'item_id' => null,
            'quantity' => 0,
            'borrow_date' => null,
            'return_date' => null,
        ]);

        ItemBorrowingItem::query()->create([
            'item_borrowing_id' => $borrowing->id,
            'item_id' => $item->id,
            'quantity' => $quantity,
            'borrow_date' => Carbon::parse($borrowAt, 'Asia/Jakarta')->utc(),
            'return_date' => Carbon::parse($returnAt, 'Asia/Jakarta')->utc(),
        ]);

        return $borrowing;
    }
}
