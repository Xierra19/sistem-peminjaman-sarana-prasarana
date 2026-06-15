<?php

namespace Tests\Unit\Policies;

use App\Models\ItemBorrowing;
use App\Models\User;
use App\Policies\ItemBorrowingPolicy;
use PHPUnit\Framework\TestCase;

class ItemBorrowingPolicyTest extends TestCase
{
    public function test_owner_can_access_edit_and_cancel_an_editable_request(): void
    {
        $owner = $this->user(1, User::ROLE_USER);
        $borrowing = $this->borrowing(1, 'waiting');
        $policy = new ItemBorrowingPolicy();

        $this->assertTrue($policy->view($owner, $borrowing));
        $this->assertTrue($policy->edit($owner, $borrowing));
        $this->assertTrue($policy->update($owner, $borrowing));
        $this->assertTrue($policy->cancel($owner, $borrowing));
    }

    public function test_owner_cannot_edit_a_processed_request(): void
    {
        $owner = $this->user(1, User::ROLE_USER);
        $borrowing = $this->borrowing(1, 'approved');
        $policy = new ItemBorrowingPolicy();

        $this->assertTrue($policy->view($owner, $borrowing));
        $this->assertFalse($policy->edit($owner, $borrowing));
        $this->assertFalse($policy->update($owner, $borrowing));
    }

    public function test_only_item_admin_can_access_another_users_request(): void
    {
        $borrowing = $this->borrowing(1, 'waiting');
        $itemAdmin = $this->user(2, User::ROLE_ADMIN_SARPRAS);
        $roomAdmin = $this->user(3, User::ROLE_ADMIN_BAP);
        $policy = new ItemBorrowingPolicy();

        $this->assertTrue($policy->view($itemAdmin, $borrowing));
        $this->assertFalse($policy->edit($itemAdmin, $borrowing));
        $this->assertFalse($policy->cancel($itemAdmin, $borrowing));
        $this->assertFalse($policy->view($roomAdmin, $borrowing));
    }

    private function user(int $id, string $role): User
    {
        return (new User())->forceFill([
            'id' => $id,
            'role' => $role,
        ]);
    }

    private function borrowing(int $userId, string $status): ItemBorrowing
    {
        return (new ItemBorrowing())->forceFill([
            'user_id' => $userId,
            'status' => $status,
        ]);
    }
}
