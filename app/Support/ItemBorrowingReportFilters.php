<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class ItemBorrowingReportFilters
{
    public static function apply(Builder $query, array $filters): Builder
    {
        $status = $filters['status'] ?? null;
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;
        $search = trim((string) ($filters['search'] ?? ''));

        if ($status) {
            $query->where(function (Builder $statusQuery) use ($status): void {
                if ($status === 'waiting') {
                    $statusQuery->whereIn('status', ['waiting', 'requested']);
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
                    ->orWhereHas('item', function (Builder $itemQuery) use ($pattern): void {
                        $itemQuery
                            ->where('name', 'like', $pattern)
                            ->orWhere('code', 'like', $pattern)
                            ->orWhere('category', 'like', $pattern);
                    })
                    ->orWhereHas('items.item', function (Builder $itemQuery) use ($pattern): void {
                        $itemQuery
                            ->where('name', 'like', $pattern)
                            ->orWhere('code', 'like', $pattern)
                            ->orWhere('category', 'like', $pattern);
                    });
            });
        }

        return $query;
    }
}
