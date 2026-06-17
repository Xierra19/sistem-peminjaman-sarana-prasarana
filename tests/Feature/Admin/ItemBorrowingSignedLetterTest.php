<?php

namespace Tests\Feature\Admin;

use App\Models\Item;
use App\Models\ItemBorrowing;
use App\Models\ItemBorrowingItem;
use App\Models\User;
use App\Notifications\ItemBorrowingStatusUpdatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ItemBorrowingSignedLetterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_must_upload_signed_letter_when_approving_item_borrowing(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_SARPRAS,
        ]);

        $itemBorrowing = $this->createItemBorrowing();

        $response = $this->actingAs($admin)
            ->from(route('admin.item-borrowings.show', $itemBorrowing))
            ->post(route('admin.item-borrowings.update-status', $itemBorrowing), [
                'status' => 'approved',
                'notes' => 'Siap diproses.',
            ]);

        $response->assertRedirect(route('admin.item-borrowings.show', $itemBorrowing));
        $response->assertSessionHasErrors('signed_letter');

        $this->assertDatabaseHas('item_borrowings', [
            'id' => $itemBorrowing->id,
            'status' => 'waiting',
            'signed_letter' => null,
        ]);
    }

    public function test_admin_can_approve_item_borrowing_with_signed_letter(): void
    {
        Storage::fake('local');
        Notification::fake();

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_SARPRAS,
        ]);

        $owner = User::factory()->create();
        $itemBorrowing = $this->createItemBorrowing($owner);

        $response = $this->actingAs($admin)->post(
            route('admin.item-borrowings.update-status', $itemBorrowing),
            [
                'status' => 'approved',
                'signed_letter' => UploadedFile::fake()->create('approval-letter.pdf', 200, 'application/pdf'),
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $itemBorrowing->refresh();

        $this->assertSame('approved', $itemBorrowing->status);
        $this->assertNotNull($itemBorrowing->approved_at);
        $this->assertNotNull($itemBorrowing->signed_letter);
        $this->assertNotNull($itemBorrowing->signed_letter_uploaded_at);
        Storage::disk('local')->assertExists($itemBorrowing->signed_letter);
        Notification::assertSentTo(
            $owner,
            ItemBorrowingStatusUpdatedNotification::class,
        );
    }

    public function test_invalid_reapproval_removes_new_upload_without_changing_existing_data(): void
    {
        Storage::fake('local');
        Notification::fake();

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_SARPRAS,
        ]);
        $itemBorrowing = $this->createItemBorrowing();
        $existingPath = 'item-borrowing-signed-letters/existing.pdf';
        Storage::disk('local')->put($existingPath, 'existing-letter');
        $itemBorrowing->update([
            'status' => ItemBorrowing::STATUS_APPROVED,
            'signed_letter' => $existingPath,
            'signed_letter_uploaded_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(
            route('admin.item-borrowings.update-status', $itemBorrowing),
            [
                'status' => ItemBorrowing::STATUS_APPROVED,
                'signed_letter' => UploadedFile::fake()->create(
                    'replacement.pdf',
                    200,
                    'application/pdf',
                ),
            ],
        );

        $response->assertSessionHasErrors('status');
        $this->assertSame(ItemBorrowing::STATUS_APPROVED, $itemBorrowing->fresh()->status);
        $this->assertSame($existingPath, $itemBorrowing->fresh()->signed_letter);
        Storage::disk('local')->assertExists($existingPath);
        $this->assertCount(
            1,
            Storage::disk('local')->allFiles('item-borrowing-signed-letters'),
        );
        $this->assertDatabaseCount('item_borrowing_logs', 0);
        Notification::assertNothingSent();
    }

    public function test_admin_can_reject_item_borrowing_without_signed_letter(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_SARPRAS,
        ]);

        $itemBorrowing = $this->createItemBorrowing();

        $response = $this->actingAs($admin)->post(
            route('admin.item-borrowings.update-status', $itemBorrowing),
            [
                'status' => 'rejected',
                'notes' => 'Stok tidak tersedia pada tanggal tersebut.',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('item_borrowings', [
            'id' => $itemBorrowing->id,
            'status' => 'rejected',
            'signed_letter' => null,
        ]);
    }

    public function test_owner_can_download_signed_letter(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $itemBorrowing = $this->createItemBorrowing($owner);

        $path = 'item-borrowing-signed-letters/surat-ditandatangani.pdf';
        Storage::disk('local')->put($path, 'signed-letter-content');

        $itemBorrowing->update([
            'status' => 'approved',
            'signed_letter' => $path,
            'signed_letter_uploaded_at' => now(),
        ]);

        $response = $this->actingAs($owner)->get(route('item-borrowings.signed-letter', $itemBorrowing));

        $response->assertOk();
        $response->assertDownload('surat-ditandatangani.pdf');
    }

    public function test_bap_admin_cannot_access_user_facing_item_borrowing_routes(): void
    {
        Storage::fake('local');

        $bapAdmin = User::factory()->create([
            'role' => User::ROLE_ADMIN_BAP,
        ]);
        $itemBorrowing = $this->createItemBorrowing();
        $attachment = 'item-borrowing-attachments/request.pdf';
        $signedLetter = 'item-borrowing-signed-letters/approval.pdf';
        Storage::disk('local')->put($attachment, 'request');
        Storage::disk('local')->put($signedLetter, 'approval');
        $itemBorrowing->update([
            'status' => 'approved',
            'attachment' => $attachment,
            'signed_letter' => $signedLetter,
        ]);

        $this->actingAs($bapAdmin)
            ->get(route('item-borrowings.show', $itemBorrowing))
            ->assertForbidden();
        $this->actingAs($bapAdmin)
            ->get(route('item-borrowings.attachment', $itemBorrowing))
            ->assertForbidden();
        $this->actingAs($bapAdmin)
            ->get(route('item-borrowings.signed-letter', $itemBorrowing))
            ->assertForbidden();
    }

    public function test_sarpras_admin_can_access_user_facing_item_borrowing_routes(): void
    {
        Storage::fake('local');

        $sarprasAdmin = User::factory()->create([
            'role' => User::ROLE_ADMIN_SARPRAS,
        ]);
        $itemBorrowing = $this->createItemBorrowing();
        $attachment = 'item-borrowing-attachments/request.pdf';
        $signedLetter = 'item-borrowing-signed-letters/approval.pdf';
        Storage::disk('local')->put($attachment, 'request');
        Storage::disk('local')->put($signedLetter, 'approval');
        $itemBorrowing->update([
            'status' => 'approved',
            'attachment' => $attachment,
            'signed_letter' => $signedLetter,
        ]);

        $this->actingAs($sarprasAdmin)
            ->get(route('item-borrowings.show', $itemBorrowing))
            ->assertOk();
        $this->actingAs($sarprasAdmin)
            ->get(route('item-borrowings.attachment', $itemBorrowing))
            ->assertDownload('request.pdf');
        $this->actingAs($sarprasAdmin)
            ->get(route('item-borrowings.signed-letter', $itemBorrowing))
            ->assertDownload('approval.pdf');
    }

    public function test_status_notification_renders_legacy_single_item_details(): void
    {
        $owner = User::factory()->create();
        $item = Item::query()->create([
            'code' => 'ITM-LEGACY',
            'name' => 'Proyektor Legacy',
            'category' => 'Elektronik',
            'quantity' => 10,
            'is_available' => true,
        ]);
        $itemBorrowing = ItemBorrowing::query()->create([
            'user_id' => $owner->id,
            'title' => 'Peminjaman Legacy',
            'status' => 'approved',
            'item_id' => $item->id,
            'quantity' => 2,
            'borrow_date' => now()->addDays(7),
            'return_date' => now()->addDays(8),
        ]);

        $mail = (new ItemBorrowingStatusUpdatedNotification(
            $itemBorrowing,
            'approved',
        ))->toMail($owner);

        $this->assertContains('Barang: Proyektor Legacy', $mail->introLines);
        $this->assertContains('Jumlah: 2', $mail->introLines);
    }

    private function createItemBorrowing(?User $owner = null): ItemBorrowing
    {
        $owner ??= User::factory()->create();

        $item = Item::query()->create([
            'code' => 'ITM-001',
            'name' => 'Proyektor',
            'category' => 'Elektronik',
            'quantity' => 10,
            'is_available' => true,
        ]);

        $itemBorrowing = ItemBorrowing::query()->create([
            'user_id' => $owner->id,
            'title' => 'Peminjaman Proyektor',
            'description' => 'Untuk kegiatan presentasi.',
            'status' => 'waiting',
            'attachment' => null,
            'item_id' => null,
            'quantity' => 0,
            'borrow_date' => null,
            'return_date' => null,
        ]);

        ItemBorrowingItem::query()->create([
            'item_borrowing_id' => $itemBorrowing->id,
            'item_id' => $item->id,
            'quantity' => 2,
            'borrow_date' => now()->addDay()->toDateString(),
            'return_date' => now()->addDays(2)->toDateString(),
        ]);

        return $itemBorrowing;
    }
}
