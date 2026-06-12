<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class BookingReportFilters
{
    /**
     * Apply report filters to the given booking query.
     */
    public static function apply(Builder $query, array $filters): Builder
    {
        $status = $filters['status'] ?? null;
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;
        $bookingStartDate = $filters['booking_start_date'] ?? null;
        $bookingEndDate = $filters['booking_end_date'] ?? null;
        $search = trim((string) ($filters['search'] ?? ''));

        if ($status) {
            $query->where(function (Builder $statusQuery) use ($status): void {
                if ($status === 'waiting') {
                    $statusQuery->whereIn('status', ['waiting', 'pending']);
                    return;
                }

                $statusQuery->where('status', $status);
            });
        }

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        if ($bookingStartDate || $bookingEndDate) {
            $query->where(function (Builder $dateQuery) use ($bookingStartDate, $bookingEndDate): void {
                $dateQuery
                    ->whereHas('roomSchedules', function (Builder $scheduleQuery) use ($bookingStartDate, $bookingEndDate): void {
                        if ($bookingStartDate) {
                            $scheduleQuery->whereDate('end_time', '>=', $bookingStartDate);
                        }

                        if ($bookingEndDate) {
                            $scheduleQuery->whereDate('start_time', '<=', $bookingEndDate);
                        }
                    })
                    ->orWhere(function (Builder $legacyQuery) use ($bookingStartDate, $bookingEndDate): void {
                        $legacyQuery->whereDoesntHave('roomSchedules');

                        if ($bookingStartDate) {
                            $legacyQuery->whereDate('end_time', '>=', $bookingStartDate);
                        }

                        if ($bookingEndDate) {
                            $legacyQuery->whereDate('start_time', '<=', $bookingEndDate);
                        }
                    });
            });
        }

        if ($search !== '') {
            $query->where(function (Builder $searchQuery) use ($search): void {
                $pattern = "%{$search}%";

                $searchQuery
                    ->where('title', 'like', $pattern)
                    ->orWhere('description', 'like', $pattern)
                    ->orWhere('status', 'like', $pattern)
                    ->orWhereHas('user', function (Builder $userQuery) use ($pattern): void {
                        $userQuery
                            ->where('name', 'like', $pattern)
                            ->orWhere('email', 'like', $pattern)
                            ->orWhere('phone', 'like', $pattern);
                    })
                    ->orWhereHas('room', function (Builder $roomQuery) use ($pattern): void {
                        $roomQuery
                            ->where('name', 'like', $pattern)
                            ->orWhereHas('building', function (Builder $buildingQuery) use ($pattern): void {
                                $buildingQuery
                                    ->where('name', 'like', $pattern)
                                    ->orWhereHas('campus', function (Builder $campusQuery) use ($pattern): void {
                                        $campusQuery->where('name', 'like', $pattern);
                                    });
                            });
                    })
                    ->orWhereHas('roomSchedules.room', function (Builder $roomQuery) use ($pattern): void {
                        $roomQuery
                            ->where('name', 'like', $pattern)
                            ->orWhereHas('building', function (Builder $buildingQuery) use ($pattern): void {
                                $buildingQuery
                                    ->where('name', 'like', $pattern)
                                    ->orWhereHas('campus', function (Builder $campusQuery) use ($pattern): void {
                                        $campusQuery->where('name', 'like', $pattern);
                                    });
                            });
                    });
            });
        }

        return $query;
    }
}
