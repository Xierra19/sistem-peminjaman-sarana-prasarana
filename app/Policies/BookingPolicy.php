<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function view(User $user, Booking $booking): bool
    {
        return $this->owns($user, $booking) || $user->canManageRoomModule();
    }

    public function downloadAttachment(User $user, Booking $booking): bool
    {
        return $this->view($user, $booking);
    }

    public function edit(User $user, Booking $booking): bool
    {
        return $this->owns($user, $booking)
            && $booking->status === Booking::STATUS_NEEDS_REVISION;
    }

    public function update(User $user, Booking $booking): bool
    {
        return $this->edit($user, $booking);
    }

    public function resubmit(User $user, Booking $booking): bool
    {
        return $this->owns($user, $booking)
            && $booking->canBeResubmitted();
    }

    public function downloadLetter(User $user, Booking $booking): bool
    {
        return $this->view($user, $booking);
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return $this->owns($user, $booking);
    }

    private function owns(User $user, Booking $booking): bool
    {
        return $booking->user_id === $user->id;
    }
}
