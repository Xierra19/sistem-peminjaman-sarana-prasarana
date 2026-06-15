<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\LogHistory;
use App\Models\User;
use App\Notifications\BookingStatusUpdatedNotification;
use App\Support\AdminStatusTransitionResult;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

final class BookingApprovalWorkflow
{
    private const LETTER_NUMBER_ATTEMPTS = 3;

    public function __construct(
        private readonly ExpirePendingBookings $expirePendingBookings,
    ) {}

    public function update(
        Booking $booking,
        string $targetStatus,
        ?string $notes,
        User $admin,
    ): AdminStatusTransitionResult {
        if ($this->expirePendingBookings->expireIfPastDue($booking)) {
            return AdminStatusTransitionResult::Expired;
        }

        $result = $this->runTransitionWithRetry(
            $booking,
            $targetStatus,
            $notes,
            $admin,
        );

        if ($result === AdminStatusTransitionResult::Updated) {
            $this->notifyOwner($booking, $targetStatus, $notes, $admin);
        }

        return $result;
    }

    private function runTransitionWithRetry(
        Booking $booking,
        string $targetStatus,
        ?string $notes,
        User $admin,
    ): AdminStatusTransitionResult {
        for ($attempt = 1; $attempt <= self::LETTER_NUMBER_ATTEMPTS; $attempt++) {
            try {
                return DB::transaction(fn (): AdminStatusTransitionResult => $this->transition(
                    $booking,
                    $targetStatus,
                    $notes,
                    $admin,
                ));
            } catch (UniqueConstraintViolationException $exception) {
                if (
                    $attempt === self::LETTER_NUMBER_ATTEMPTS
                    || ! $this->isLetterNumberCollision($exception)
                ) {
                    throw $exception;
                }
            }
        }

        throw new LogicException('Booking approval retry loop ended unexpectedly.');
    }

    private function transition(
        Booking $booking,
        string $targetStatus,
        ?string $notes,
        User $admin,
    ): AdminStatusTransitionResult {
        $lockedBooking = Booking::query()
            ->lockForUpdate()
            ->findOrFail($booking->getKey());

        if (
            in_array($targetStatus, [Booking::STATUS_APPROVED, Booking::STATUS_REJECTED], true)
            && ! in_array($lockedBooking->status, Booking::PENDING_STATUSES, true)
        ) {
            return AdminStatusTransitionResult::PendingRequired;
        }

        if (
            $targetStatus === Booking::STATUS_CANCELLED
            && $lockedBooking->status !== Booking::STATUS_APPROVED
        ) {
            return AdminStatusTransitionResult::ApprovedRequired;
        }

        $lockedBooking->update([
            'status' => $targetStatus,
        ]);

        if ($targetStatus === Booking::STATUS_APPROVED && ! $lockedBooking->letter_number) {
            $this->assignLetterNumber($lockedBooking);
        }

        $description = match ($targetStatus) {
            Booking::STATUS_APPROVED => 'Booking disetujui',
            Booking::STATUS_REJECTED => 'Booking ditolak',
            Booking::STATUS_CANCELLED => 'Booking dibatalkan oleh admin',
            default => 'Status booking diperbarui',
        };

        if ($notes) {
            $description .= ' - '.$notes;
        }

        LogHistory::query()->create([
            'booking_id' => $lockedBooking->id,
            'user_id' => $admin->id,
            'action' => $targetStatus,
            'description' => $description,
        ]);

        $booking->setRawAttributes($lockedBooking->getAttributes(), true);

        return AdminStatusTransitionResult::Updated;
    }

    private function assignLetterNumber(Booking $booking): void
    {
        $issuedAt = now();
        $latestSequence = Booking::query()
            ->whereYear('letter_generated_at', (int) $issuedAt->format('Y'))
            ->whereMonth('letter_generated_at', (int) $issuedAt->format('m'))
            ->lockForUpdate()
            ->orderByDesc('letter_sequence')
            ->value('letter_sequence');
        $nextSequence = ((int) $latestSequence) + 1;
        $letterNumber = $this->formatLetterNumber($nextSequence, $issuedAt);

        while (Booking::query()->where('letter_number', $letterNumber)->exists()) {
            $nextSequence++;
            $letterNumber = $this->formatLetterNumber($nextSequence, $issuedAt);
        }

        $booking->update([
            'letter_sequence' => $nextSequence,
            'letter_number' => $letterNumber,
            'letter_generated_at' => $issuedAt,
        ]);
    }

    private function formatLetterNumber(int $sequence, \DateTimeInterface $issuedAt): string
    {
        return sprintf(
            '%d/BAP-Bekasi/Booking/%s/%s',
            $sequence,
            $issuedAt->format('m'),
            $issuedAt->format('Y'),
        );
    }

    private function isLetterNumberCollision(UniqueConstraintViolationException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'bookings_letter_number_unique')
            || str_contains($message, 'bookings.letter_number')
            || str_contains($message, 'letter_number');
    }

    private function notifyOwner(
        Booking $booking,
        string $targetStatus,
        ?string $notes,
        User $admin,
    ): void {
        $booking->load(['user', 'roomSchedules.room.building.campus']);

        if (! $booking->user || empty($booking->user->email)) {
            return;
        }

        try {
            $booking->user->notify(new BookingStatusUpdatedNotification(
                $booking,
                $targetStatus,
                $notes,
                $admin->name,
            ));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
