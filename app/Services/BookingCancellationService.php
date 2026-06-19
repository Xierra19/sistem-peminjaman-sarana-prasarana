<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\LogHistory;
use App\Models\User;
use App\Support\CancellationResult;
use Illuminate\Support\Facades\DB;

final class BookingCancellationService
{
    public function __construct(
        private readonly ExpirePendingBookings $expirePendingBookings,
    ) {}

    public function cancel(Booking $booking, User $user): CancellationResult
    {
        if ($this->expirePendingBookings->expireIfPastDue($booking)) {
            return CancellationResult::ExpiredNow;
        }

        return DB::transaction(function () use ($booking, $user): CancellationResult {
            $lockedBooking = Booking::query()
                ->lockForUpdate()
                ->findOrFail($booking->getKey());

            $result = match (true) {
                $lockedBooking->status === Booking::STATUS_CANCELLED => CancellationResult::AlreadyCancelled,
                $lockedBooking->status === Booking::STATUS_EXPIRED => CancellationResult::Expired,
                ! in_array($lockedBooking->status, Booking::CANCELLABLE_STATUSES, true) => CancellationResult::NotAllowed,
                default => null,
            };

            if ($result) {
                $booking->setRawAttributes($lockedBooking->getAttributes(), true);

                return $result;
            }

            $lockedBooking->update([
                'status' => Booking::STATUS_CANCELLED,
            ]);

            LogHistory::query()->create([
                'booking_id' => $lockedBooking->id,
                'user_id' => $user->id,
                'action' => Booking::STATUS_CANCELLED,
                'description' => 'Booking dibatalkan oleh pemohon.',
            ]);

            $booking->setRawAttributes($lockedBooking->getAttributes(), true);

            return CancellationResult::Cancelled;
        });
    }
}
