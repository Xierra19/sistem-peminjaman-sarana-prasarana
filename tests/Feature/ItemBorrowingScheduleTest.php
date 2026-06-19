<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemBorrowing;
use App\Models\ItemBorrowingItem;
use App\Models\User;
use App\Notifications\ItemBorrowingRequestedNotification;
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
        Storage::fake('local');
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
                'dates' => ['2026-06-20'],
                'start_time' => '08:00',
                'end_time' => '10:00',
            ]],
        ]);

        $response->assertRedirect(route('item-borrowings.index'));
        $response->assertSessionHasNoErrors();

        $detail = ItemBorrowingItem::query()->firstOrFail();

        $this->assertSame('2026-06-20 08:00', $detail->borrow_date->timezone('Asia/Jakarta')->format('Y-m-d H:i'));
        $this->assertSame('2026-06-20 10:00', $detail->return_date->timezone('Asia/Jakarta')->format('Y-m-d H:i'));
    }

    public function test_multi_date_card_expands_into_separate_item_schedules(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-13 10:00', 'Asia/Jakarta')->utc());
        Storage::fake('local');
        Notification::fake();

        $user = User::factory()->create();
        $item = $this->createItem();

        $response = $this->actingAs($user)->post(route('item-borrowings.store'), [
            'title' => 'Dokumentasi Multi Tanggal',
            'attachment' => UploadedFile::fake()->create('surat.pdf', 100, 'application/pdf'),
            'items' => [[
                'item_id' => $item->id,
                'quantity' => 4,
                'dates' => ['2026-06-20', '2026-06-22', '2026-06-24'],
                'start_time' => '08:00',
                'end_time' => '10:00',
            ]],
        ]);

        $response
            ->assertRedirect(route('item-borrowings.index'))
            ->assertSessionHasNoErrors();

        $rows = ItemBorrowingItem::query()->orderBy('borrow_date')->get();

        $this->assertCount(3, $rows);
        $this->assertSame(
            ['2026-06-20', '2026-06-22', '2026-06-24'],
            $rows->map(fn (ItemBorrowingItem $row) => $row->borrow_date
                ->timezone('Asia/Jakarta')
                ->toDateString())->all(),
        );
        $this->assertSame([4, 4, 4], $rows->pluck('quantity')->all());
    }

    public function test_same_item_can_use_the_same_schedule_when_quantity_is_different(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-13 10:00', 'Asia/Jakarta')->utc());
        Storage::fake('local');
        Notification::fake();

        $user = User::factory()->create();
        $item = $this->createItem(quantity: 5);

        $response = $this->actingAs($user)->post(route('item-borrowings.store'), [
            'title' => 'Dua Kartu Barang Sama',
            'attachment' => UploadedFile::fake()->create('surat.pdf', 100, 'application/pdf'),
            'items' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 2,
                    'dates' => ['2026-06-20'],
                    'start_time' => '08:00',
                    'end_time' => '10:00',
                ],
                [
                    'item_id' => $item->id,
                    'quantity' => 1,
                    'dates' => ['2026-06-20'],
                    'start_time' => '08:00',
                    'end_time' => '10:00',
                ],
            ],
        ]);

        $response
            ->assertRedirect(route('item-borrowings.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame([1, 2], ItemBorrowingItem::query()->orderBy('quantity')->pluck('quantity')->all());
    }

    public function test_identical_item_cards_are_rejected(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-13 10:00', 'Asia/Jakarta')->utc());
        Storage::fake('local');
        Notification::fake();

        $user = User::factory()->create();
        $item = $this->createItem();
        $card = [
            'item_id' => $item->id,
            'quantity' => 2,
            'dates' => ['2026-06-20'],
            'start_time' => '08:00',
            'end_time' => '10:00',
        ];

        $response = $this->actingAs($user)
            ->from(route('item-borrowings.create'))
            ->post(route('item-borrowings.store'), [
                'title' => 'Jadwal Duplikat',
                'attachment' => UploadedFile::fake()->create('surat.pdf', 100, 'application/pdf'),
                'items' => [$card, $card],
            ]);

        $response->assertRedirect(route('item-borrowings.create'));
        $response->assertSessionHasErrors('items.1.dates.0');
        $this->assertDatabaseCount('item_borrowings', 0);
    }

    public function test_overlapping_cards_for_the_same_item_use_combined_stock(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-13 10:00', 'Asia/Jakarta')->utc());
        Storage::fake('local');
        Notification::fake();

        $user = User::factory()->create();
        $item = $this->createItem(quantity: 5);

        $response = $this->actingAs($user)
            ->from(route('item-borrowings.create'))
            ->post(route('item-borrowings.store'), [
                'title' => 'Stok Gabungan',
                'attachment' => UploadedFile::fake()->create('surat.pdf', 100, 'application/pdf'),
                'items' => [
                    [
                        'item_id' => $item->id,
                        'quantity' => 3,
                        'dates' => ['2026-06-20'],
                        'start_time' => '08:00',
                        'end_time' => '10:00',
                    ],
                    [
                        'item_id' => $item->id,
                        'quantity' => 3,
                        'dates' => ['2026-06-20'],
                        'start_time' => '09:00',
                        'end_time' => '11:00',
                    ],
                ],
            ]);

        $response->assertRedirect(route('item-borrowings.create'));
        $response->assertSessionHasErrors(['items.0.quantity', 'items.1.quantity']);
        $this->assertDatabaseCount('item_borrowings', 0);
    }

    public function test_update_replaces_existing_schedule_with_multi_date_rows(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-13 10:00', 'Asia/Jakarta')->utc());
        Storage::fake('local');
        Notification::fake();

        $user = User::factory()->create();
        $item = $this->createItem();
        $borrowing = $this->createBorrowing($user, $item, ItemBorrowing::STATUS_WAITING);

        $response = $this->actingAs($user)->put(route('item-borrowings.update', $borrowing), [
            'title' => 'Jadwal Diperbarui',
            'items' => [[
                'item_id' => $item->id,
                'quantity' => 2,
                'dates' => ['2026-06-20', '2026-06-22'],
                'start_time' => '13:00',
                'end_time' => '15:00',
            ]],
        ]);

        $response
            ->assertRedirect(route('item-borrowings.index'))
            ->assertSessionHasNoErrors();

        $rows = $borrowing->fresh()->items()->orderBy('borrow_date')->get();

        $this->assertCount(2, $rows);
        $this->assertSame([2, 2], $rows->pluck('quantity')->all());
        $this->assertSame(
            ['2026-06-20 13:00', '2026-06-22 13:00'],
            $rows->map(fn (ItemBorrowingItem $row) => $row->borrow_date
                ->timezone('Asia/Jakarta')
                ->format('Y-m-d H:i'))->all(),
        );
    }

    public function test_multi_date_availability_returns_stock_for_each_selected_date(): void
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
            'dates' => ['2026-06-20', '2026-06-22'],
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('daily_availability.0.date', '2026-06-20')
            ->assertJsonPath('daily_availability.0.remaining_quantity', 2)
            ->assertJsonPath('daily_availability.1.date', '2026-06-22')
            ->assertJsonPath('daily_availability.1.remaining_quantity', 5);
    }

    public function test_new_request_notifies_only_sarpras_admins(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-13 10:00', 'Asia/Jakarta')->utc());
        Storage::fake('local');
        Notification::fake();

        $sarprasAdmin = User::factory()->create(['role' => User::ROLE_ADMIN_SARPRAS]);
        $bapAdmin = User::factory()->create(['role' => User::ROLE_ADMIN_BAP]);
        $user = User::factory()->create();
        $item = $this->createItem();

        $this->actingAs($user)->post(route('item-borrowings.store'), [
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
        ])->assertRedirect(route('item-borrowings.index'));

        Notification::assertSentTo($sarprasAdmin, ItemBorrowingRequestedNotification::class);
        Notification::assertNotSentTo($bapAdmin, ItemBorrowingRequestedNotification::class);
    }

    public function test_user_cannot_submit_after_the_h_minus_seven_calendar_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-14 00:01', 'Asia/Jakarta')->utc());
        Storage::fake('local');
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
        $response->assertSessionHasErrors('items.0.dates.0');
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

    public function test_edit_availability_excludes_the_current_borrowing_item(): void
    {
        $user = User::factory()->create();
        $item = $this->createItem(quantity: 5);
        $borrowing = $this->createBorrowing($user, $item, 'waiting');
        $borrowingItem = $borrowing->items()->firstOrFail();

        $response = $this->actingAs($user)->getJson(route('items.availability', [
            'item' => $item,
            'borrow_date' => '2026-06-20',
            'borrow_time' => '08:00',
            'return_date' => '2026-06-20',
            'return_time' => '10:00',
            'exclude_item_borrowing_item_id' => $borrowingItem->id,
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('reserved_quantity', 0)
            ->assertJsonPath('remaining_quantity', 5);
    }

    public function test_availability_does_not_exclude_another_users_borrowing_item(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $item = $this->createItem(quantity: 5);
        $borrowing = $this->createBorrowing($owner, $item, 'waiting');
        $borrowingItem = $borrowing->items()->firstOrFail();

        $response = $this->actingAs($otherUser)->getJson(route('items.availability', [
            'item' => $item,
            'borrow_date' => '2026-06-20',
            'borrow_time' => '08:00',
            'return_date' => '2026-06-20',
            'return_time' => '10:00',
            'exclude_item_borrowing_item_id' => $borrowingItem->id,
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('reserved_quantity', 1)
            ->assertJsonPath('remaining_quantity', 4);
    }

    public function test_store_rejects_a_request_when_locked_stock_is_insufficient(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-13 10:00', 'Asia/Jakarta')->utc());
        Storage::fake('local');
        Notification::fake();

        $user = User::factory()->create();
        $item = $this->createItem(quantity: 2);
        $this->createBorrowing(User::factory()->create(), $item, 'waiting');

        $response = $this->actingAs($user)
            ->from(route('item-borrowings.create'))
            ->post(route('item-borrowings.store'), [
                'title' => 'Peminjaman melebihi stok',
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

        $response->assertRedirect(route('item-borrowings.create'));
        $response->assertSessionHasErrors('items.0.quantity');
        $this->assertDatabaseCount('item_borrowings', 1);
        Storage::disk('local')->assertDirectoryEmpty('item-borrowing-attachments');
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

    public function test_owner_can_cancel_waiting_item_borrowing_only_once(): void
    {
        $owner = User::factory()->create();
        $item = $this->createItem();
        $borrowing = $this->createBorrowing($owner, $item, ItemBorrowing::STATUS_WAITING);

        $this->actingAs($owner)
            ->from(route('item-borrowings.show', $borrowing))
            ->post(route('item-borrowings.cancel', $borrowing))
            ->assertRedirect(route('item-borrowings.show', $borrowing))
            ->assertSessionHas('success', 'Permintaan peminjaman barang berhasil dibatalkan.');

        $this->assertSame(ItemBorrowing::STATUS_CANCELLED, $borrowing->fresh()->status);
        $this->assertDatabaseHas('item_borrowing_logs', [
            'item_borrowing_id' => $borrowing->id,
            'user_id' => $owner->id,
            'action' => ItemBorrowing::STATUS_CANCELLED,
        ]);

        $this->actingAs($owner)
            ->from(route('item-borrowings.show', $borrowing))
            ->post(route('item-borrowings.cancel', $borrowing))
            ->assertSessionHas('error', 'Permintaan peminjaman barang sudah dibatalkan sebelumnya.');

        $this->assertDatabaseCount('item_borrowing_logs', 1);
    }

    public function test_owner_cannot_cancel_approved_item_borrowing(): void
    {
        $owner = User::factory()->create();
        $item = $this->createItem();
        $borrowing = $this->createApprovedBorrowing(
            $owner,
            $item,
            '2026-06-20 08:00',
            '2026-06-20 10:00',
            1,
        );

        $this->actingAs($owner)
            ->post(route('item-borrowings.cancel', $borrowing))
            ->assertSessionHas('error', 'Permintaan tidak dapat dibatalkan karena sudah diproses oleh admin.');

        $this->assertSame(ItemBorrowing::STATUS_APPROVED, $borrowing->fresh()->status);
        $this->assertDatabaseMissing('item_borrowing_logs', [
            'item_borrowing_id' => $borrowing->id,
            'action' => ItemBorrowing::STATUS_CANCELLED,
        ]);
    }

    public function test_user_cannot_update_an_item_row_from_another_borrowing(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-13 10:00', 'Asia/Jakarta')->utc());

        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $item = $this->createItem();
        $otherItem = $this->createItem();

        $borrowing = $this->createBorrowing($user, $item, 'waiting');
        $otherBorrowing = $this->createBorrowing($otherUser, $otherItem, 'waiting');
        $otherRow = $otherBorrowing->items()->firstOrFail();

        $response = $this->actingAs($user)
            ->from(route('item-borrowings.edit', $borrowing))
            ->put(route('item-borrowings.update', $borrowing), [
                'title' => 'Percobaan manipulasi',
                'items' => [[
                    'id' => $otherRow->id,
                    'item_id' => $otherItem->id,
                    'quantity' => 9,
                    'borrow_date' => '2026-06-20',
                    'borrow_time' => '08:00',
                    'return_date' => '2026-06-20',
                    'return_time' => '10:00',
                ]],
            ]);

        $response->assertRedirect(route('item-borrowings.edit', $borrowing));
        $response->assertSessionHasErrors('items.0.id');

        $this->assertSame('Peminjaman Barang', $borrowing->fresh()->title);
        $this->assertSame(1, $otherRow->fresh()->quantity);
        $this->assertSame($otherBorrowing->id, $otherRow->fresh()->item_borrowing_id);
    }

    public function test_successful_update_replaces_attachment_after_commit(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-13 10:00', 'Asia/Jakarta')->utc());
        Storage::fake('local');

        $user = User::factory()->create();
        $item = $this->createItem();
        $borrowing = $this->createBorrowing($user, $item, 'waiting');
        $borrowingItem = $borrowing->items()->firstOrFail();
        $oldPath = 'item-borrowing-attachments/old.pdf';
        Storage::disk('local')->put($oldPath, 'old');
        $borrowing->update(['attachment' => $oldPath]);

        $response = $this->actingAs($user)->put(route('item-borrowings.update', $borrowing), [
            'title' => 'Peminjaman diperbarui',
            'attachment' => UploadedFile::fake()->create('new.pdf', 100, 'application/pdf'),
            'items' => [[
                'id' => $borrowingItem->id,
                'item_id' => $item->id,
                'quantity' => 1,
                'borrow_date' => '2026-06-20',
                'borrow_time' => '08:00',
                'return_date' => '2026-06-20',
                'return_time' => '10:00',
            ]],
        ]);

        $response->assertRedirect(route('item-borrowings.index'));
        $response->assertSessionHasNoErrors();

        $newPath = $borrowing->fresh()->attachment;
        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($newPath);
    }

    public function test_failed_update_keeps_old_attachment_and_removes_new_upload(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-13 10:00', 'Asia/Jakarta')->utc());
        Storage::fake('local');

        $user = User::factory()->create();
        $currentItem = $this->createItem();
        $unavailableItem = $this->createItem(quantity: 1);
        $borrowing = $this->createBorrowing($user, $currentItem, 'waiting');
        $borrowingItem = $borrowing->items()->firstOrFail();
        $this->createBorrowing(User::factory()->create(), $unavailableItem, 'waiting');
        $oldPath = 'item-borrowing-attachments/old.pdf';
        Storage::disk('local')->put($oldPath, 'old');
        $borrowing->update(['attachment' => $oldPath]);

        $response = $this->actingAs($user)
            ->from(route('item-borrowings.edit', $borrowing))
            ->put(route('item-borrowings.update', $borrowing), [
                'title' => 'Peminjaman gagal diperbarui',
                'attachment' => UploadedFile::fake()->create('new.pdf', 100, 'application/pdf'),
                'items' => [[
                    'id' => $borrowingItem->id,
                    'item_id' => $unavailableItem->id,
                    'quantity' => 1,
                    'borrow_date' => '2026-06-20',
                    'borrow_time' => '08:00',
                    'return_date' => '2026-06-20',
                    'return_time' => '10:00',
                ]],
            ]);

        $response->assertRedirect(route('item-borrowings.edit', $borrowing));
        $response->assertSessionHasErrors('items.0.quantity');
        $this->assertSame($oldPath, $borrowing->fresh()->attachment);
        Storage::disk('local')->assertExists($oldPath);
        $this->assertSame([$oldPath], Storage::disk('local')->allFiles('item-borrowing-attachments'));
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

    private function createBorrowing(User $user, Item $item, string $status): ItemBorrowing
    {
        $borrowing = ItemBorrowing::query()->create([
            'user_id' => $user->id,
            'title' => 'Peminjaman Barang',
            'status' => $status,
            'item_id' => null,
            'quantity' => 0,
            'borrow_date' => null,
            'return_date' => null,
        ]);

        ItemBorrowingItem::query()->create([
            'item_borrowing_id' => $borrowing->id,
            'item_id' => $item->id,
            'quantity' => 1,
            'borrow_date' => Carbon::parse('2026-06-20 08:00', 'Asia/Jakarta')->utc(),
            'return_date' => Carbon::parse('2026-06-20 10:00', 'Asia/Jakarta')->utc(),
        ]);

        return $borrowing;
    }
}
