<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

final class HistoryFilters
{
    private const ACTION_LABELS = [
        'requested' => 'diajukan',
        'approved' => 'disetujui',
        'needs_revision' => 'perlu revisi',
        'rejected' => 'ditolak',
        'cancelled' => 'dibatalkan',
        'expired' => 'kedaluwarsa',
    ];

    /**
     * @param  Builder<\App\Models\LogHistory>  $query
     * @param  array<string, mixed>  $filters
     */
    public static function apply(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $normalizedSearch = mb_strtolower($search);
            $matchingActions = collect(self::ACTION_LABELS)
                ->filter(fn (string $label): bool => str_contains($label, $normalizedSearch))
                ->keys()
                ->all();

            $query->where(function (Builder $query) use ($search, $matchingActions): void {
                $query
                    ->where('action', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('user', function (Builder $query) use ($search): void {
                        $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('booking', function (Builder $query) use ($search): void {
                        $query
                            ->where('title', 'like', "%{$search}%")
                            ->orWhere('id', 'like', "%{$search}%")
                            ->orWhereHas('roomSchedules.room', function (Builder $query) use ($search): void {
                                $query
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhereHas('building', function (Builder $query) use ($search): void {
                                        $query
                                            ->where('name', 'like', "%{$search}%")
                                            ->orWhereHas('campus', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"));
                                    });
                            });
                    });

                if ($matchingActions !== []) {
                    $query->orWhereIn('action', $matchingActions);
                }
            });
        }

        if (filled($filters['action'] ?? null)) {
            $query->where('action', $filters['action']);
        }

        if (filled($filters['room_id'] ?? null)) {
            $roomId = (int) $filters['room_id'];

            $query->whereHas('booking', function (Builder $query) use ($roomId): void {
                $query->where(function (Builder $query) use ($roomId): void {
                    $query
                        ->where('room_id', $roomId)
                        ->orWhereHas('roomSchedules', fn (Builder $query) => $query->where('room_id', $roomId));
                });
            });
        }

        $timezone = config('app.business_timezone', 'Asia/Jakarta');

        if (filled($filters['start_date'] ?? null)) {
            $start = Carbon::parse($filters['start_date'], $timezone)
                ->startOfDay()
                ->utc();

            $query->where('created_at', '>=', $start);
        }

        if (filled($filters['end_date'] ?? null)) {
            $end = Carbon::parse($filters['end_date'], $timezone)
                ->endOfDay()
                ->utc();

            $query->where('created_at', '<=', $end);
        }
    }
}
