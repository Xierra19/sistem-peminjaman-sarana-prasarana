<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingRoomSchedule;
use App\Models\Room;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class BookingScheduleConflictService
{
    /**
     * @param  Collection<int, array{room_id: int, start_time: mixed, end_time: mixed}>  $schedules
     */
    public function lockRooms(Collection $schedules): void
    {
        Room::query()
            ->whereIn('id', $schedules->pluck('room_id')->unique()->sort()->values())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * @param  Collection<int, array{room_id: int, start_time: mixed, end_time: mixed}>  $schedules
     */
    public function hasApprovedConflict(Collection $schedules, ?int $excludeBookingId = null): bool
    {
        return $schedules->contains(
            fn (array $schedule): bool => $this->scheduleHasConflict(
                $schedule,
                Booking::BLOCKING_STATUSES,
                $excludeBookingId,
            ),
        );
    }

    public function lockRoomsForBooking(Booking $booking): void
    {
        $this->lockRooms($this->schedulesForBooking($booking));
    }

    public function bookingHasApprovedConflict(Booking $booking): bool
    {
        return $this->hasApprovedConflict(
            $this->schedulesForBooking($booking),
            $booking->id,
        );
    }

    /**
     * @param  array<int, string>  $statuses
     * @return Collection<int, Booking>
     */
    public function conflictsForBooking(Booking $booking, array $statuses): Collection
    {
        $schedules = $this->schedulesForBooking($booking);

        if ($schedules->isEmpty()) {
            return collect();
        }

        return Booking::query()
            ->with(['user:id,name,email', 'roomSchedules.room:id,name'])
            ->whereKeyNot($booking->id)
            ->whereIn('status', $statuses)
            ->where(function (Builder $query) use ($schedules): void {
                foreach ($schedules as $schedule) {
                    $query->orWhere(function (Builder $scheduleQuery) use ($schedule): void {
                        $scheduleQuery
                            ->where(function (Builder $detailQuery) use ($schedule): void {
                                $detailQuery->whereHas('roomSchedules', function (Builder $roomScheduleQuery) use ($schedule): void {
                                    $roomScheduleQuery
                                        ->where('room_id', $schedule['room_id'])
                                        ->where('start_time', '<', $schedule['end_time'])
                                        ->where('end_time', '>', $schedule['start_time']);
                                });
                            })
                            ->orWhere(function (Builder $legacyQuery) use ($schedule): void {
                                $legacyQuery
                                    ->whereDoesntHave('roomSchedules')
                                    ->where('room_id', $schedule['room_id'])
                                    ->where('start_time', '<', $schedule['end_time'])
                                    ->where('end_time', '>', $schedule['start_time']);
                            });
                    });
                }
            })
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @param  array{room_id: int, start_time: mixed, end_time: mixed}  $schedule
     * @param  array<int, string>  $statuses
     */
    private function scheduleHasConflict(
        array $schedule,
        array $statuses,
        ?int $excludeBookingId,
    ): bool {
        $hasDetailConflict = BookingRoomSchedule::query()
            ->where('room_id', $schedule['room_id'])
            ->where('start_time', '<', $schedule['end_time'])
            ->where('end_time', '>', $schedule['start_time'])
            ->whereHas('booking', function (Builder $query) use ($statuses, $excludeBookingId): void {
                $query
                    ->whereIn('status', $statuses)
                    ->when($excludeBookingId, fn (Builder $bookingQuery, int $bookingId) => $bookingQuery->whereKeyNot($bookingId));
            })
            ->exists();

        if ($hasDetailConflict) {
            return true;
        }

        return Booking::query()
            ->where('room_id', $schedule['room_id'])
            ->whereDoesntHave('roomSchedules')
            ->whereIn('status', $statuses)
            ->when($excludeBookingId, fn (Builder $query, int $bookingId) => $query->whereKeyNot($bookingId))
            ->where('start_time', '<', $schedule['end_time'])
            ->where('end_time', '>', $schedule['start_time'])
            ->exists();
    }

    /**
     * @return Collection<int, array{room_id: int, start_time: mixed, end_time: mixed}>
     */
    public function schedulesForBooking(Booking $booking): Collection
    {
        $booking->loadMissing('roomSchedules');

        if ($booking->roomSchedules->isNotEmpty()) {
            return $booking->roomSchedules
                ->map(fn (BookingRoomSchedule $schedule): array => [
                    'room_id' => (int) $schedule->room_id,
                    'start_time' => $schedule->start_time,
                    'end_time' => $schedule->end_time,
                ])
                ->values();
        }

        if (! $booking->room_id || ! $booking->start_time || ! $booking->end_time) {
            return collect();
        }

        return collect([[
            'room_id' => (int) $booking->room_id,
            'start_time' => $booking->start_time,
            'end_time' => $booking->end_time,
        ]]);
    }
}
