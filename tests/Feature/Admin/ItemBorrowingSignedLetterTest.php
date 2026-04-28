<?php

namespace Tests\Feature\Admin;

use App\Models\Item;
use App\Models\ItemBorrowing;
use App\Models\ItemBorrowingItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ItemBorrowingSignedLetterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_must_upload_signed_letter_when_approving_item_borrowing(): void
    {
        Storage::fake('public');

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
        Storage::fake('public');

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_SARPRAS,
        ]);

        $itemBorrowing = $this->createItemBorrowing();

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
        Storage::disk('public')->assertExists($itemBorrowing->signed_letter);
    }

    public function test_admin_can_reject_item_borrowing_without_signed_letter(): void
    {
        Storage::fake('public');

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
        Storage::fake('public');

        $owner = User::factory()->create();
        $itemBorrowing = $this->createItemBorrowing($owner);

        $path = 'item-borrowing-signed-letters/surat-ditandatangani.pdf';
        Storage::disk('public')->put($path, 'signed-letter-content');

        $itemBorrowing->update([
            'status' => 'approved',
            'signed_letter' => $path,
            'signed_letter_uploaded_at' => now(),
        ]);

        $response = $this->actingAs($owner)->get(route('item-borrowings.signed-letter', $itemBorrowing));

        $response->assertOk();
        $response->assertDownload('surat-ditandatangani.pdf');
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
