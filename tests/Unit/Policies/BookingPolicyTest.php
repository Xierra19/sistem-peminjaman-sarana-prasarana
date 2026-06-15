<?php

namespace Tests\Unit\Policies;

use App\Models\Booking;
use App\Models\User;
use App\Policies\BookingPolicy;
use PHPUnit\Framework\TestCase;

class BookingPolicyTest extends TestCase
{
    public function test_owner_can_access_and_cancel_own_booking(): void
    {
        $owner = $this->user(1, User::ROLE_USER);
        $booking = $this->booking(1);
        $policy = new BookingPolicy;

        $this->assertTrue($policy->view($owner, $booking));
        $this->assertTrue($policy->downloadAttachment($owner, $booking));
        $this->assertTrue($policy->downloadLetter($owner, $booking));
        $this->assertTrue($policy->cancel($owner, $booking));
    }

    public function test_only_room_admin_can_access_another_users_booking(): void
    {
        $booking = $this->booking(1);
        $roomAdmin = $this->user(2, User::ROLE_ADMIN_BAP);
        $itemAdmin = $this->user(3, User::ROLE_ADMIN_SARPRAS);
        $policy = new BookingPolicy;

        $this->assertTrue($policy->view($roomAdmin, $booking));
        $this->assertFalse($policy->cancel($roomAdmin, $booking));
        $this->assertFalse($policy->view($itemAdmin, $booking));
    }

    public function test_owner_can_resubmit_rejected_or_cancelled_booking(): void
    {
        $owner = $this->user(1, User::ROLE_USER);
        $policy = new BookingPolicy;

        $this->assertTrue($policy->resubmit(
            $owner,
            $this->booking(1)->forceFill(['status' => Booking::STATUS_REJECTED]),
        ));
        $this->assertTrue($policy->resubmit(
            $owner,
            $this->booking(1)->forceFill([
                'status' => Booking::STATUS_CANCELLED,
                'letter_number' => '1/BAP-Bekasi/Booking/06/2026',
            ]),
        ));
        $this->assertFalse($policy->resubmit(
            $owner,
            $this->booking(1)->forceFill(['status' => Booking::STATUS_CANCELLED]),
        ));
        $this->assertFalse($policy->resubmit(
            $owner,
            $this->booking(1)->forceFill(['status' => Booking::STATUS_APPROVED]),
        ));
    }

    private function user(int $id, string $role): User
    {
        return (new User)->forceFill([
            'id' => $id,
            'role' => $role,
        ]);
    }

    private function booking(int $userId): Booking
    {
        return (new Booking)->forceFill([
            'user_id' => $userId,
        ]);
    }
}
