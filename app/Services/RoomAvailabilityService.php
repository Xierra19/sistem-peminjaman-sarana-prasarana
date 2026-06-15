<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class RoomAvailabilityService
{
    public function getAvailability(
        Room $room,
        ?Carbon $day,
        Collection $selectedDates,
        ?Carbon $rangeStart,
        ?Carbon $rangeEnd,
    ): array {
        if ($rangeStart && $rangeEnd && $rangeStart->gt($rangeEnd)) {
            [$rangeStart, $rangeEnd] = [
                $rangeEnd->copy()->startOfDay(),
                $rangeStart->copy()->endOfDay(),
            ];
        }

        $queryStart = $rangeStart
            ?? $selectedDates->first()?->copy()->startOfDay()
            ?? $day?->copy()->startOfDay();
        $queryEnd = $rangeEnd
            ?? $selectedDates->last()?->copy()->endOfDay()
            ?? $day?->copy()->endOfDay();

        if (! $queryStart || ! $queryEnd) {
            return $this->emptyAvailability();
        }

        $schedules = $room->bookingSchedules()
            ->with('booking:id,title,status')
            ->whereHas('booking', function ($query): void {
                $query->whereIn('status', [
                    ...Booking::QUEUED_STATUSES,
                    ...Booking::BLOCKING_STATUSES,
                ]);
            })
            ->where('start_time', '<', $queryEnd)
            ->where('end_time', '>', $queryStart)
            ->get();

        $legacyBookings = $room->bookings()
            ->whereDoesntHave('roomSchedules')
            ->whereIn('status', [
                ...Booking::QUEUED_STATUSES,
                ...Booking::BLOCKING_STATUSES,
            ])
            ->where('start_time', '<', $queryEnd)
            ->where('end_time', '>', $queryStart)
            ->get();

        $dailyBookings = $selectedDates->isNotEmpty()
            ? $selectedDates
                ->flatMap(fn (Carbon $selectedDate) => $this->buildDailyBookings(
                    $schedules,
                    $legacyBookings,
                    $selectedDate->copy()->startOfDay(),
                    $selectedDate->copy()->endOfDay(),
                ))
                ->values()
                ->all()
            : $this->buildDailyBookings(
                $schedules,
                $legacyBookings,
                $queryStart,
                $queryEnd,
            );

        $selectedDateEntry = $day
            ? collect($dailyBookings)->firstWhere('date', $day->toDateString())
            : null;

        return [
            'available' => true,
            'bookings' => $selectedDateEntry['bookings'] ?? [],
            'daily_bookings' => $dailyBookings,
        ];
    }

    private function emptyAvailability(): array
    {
        return [
            'available' => true,
            'bookings' => [],
            'daily_bookings' => [],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildDailyBookings(
        Collection $schedules,
        Collection $legacyBookings,
        Carbon $rangeStart,
        Carbon $rangeEnd,
    ): array {
        $grouped = collect();

        foreach ($schedules as $schedule) {
            foreach ($schedule->buildDailyIntervalsWithinRange($rangeStart, $rangeEnd) as $interval) {
                $grouped->push($interval);
            }
        }

        foreach ($legacyBookings as $booking) {
            foreach ($booking->buildDailyIntervalsWithinRange($rangeStart, $rangeEnd) as $interval) {
                $grouped->push($interval);
            }
        }

        return $grouped
            ->groupBy('date')
            ->map(fn (Collection $items, string $date) => [
                'date' => $date,
                'bookings' => $items
                    ->sortBy(['start', 'end'])
                    ->values()
                    ->map(fn (array $item) => [
                        'id' => $item['id'],
                        'title' => $item['title'],
                        'status' => $item['status'],
                        'start' => $item['start'],
                        'end' => $item['end'],
                    ])
                    ->all(),
            ])
            ->sortBy('date')
            ->values()
            ->all();
    }
}
