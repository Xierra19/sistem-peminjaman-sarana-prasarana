<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Campus;
use App\Models\Item;
use App\Models\ItemBorrowing;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $baseQuery = Booking::query()
            ->with([
                'user:id,name,email',
                'room:id,name,building_id',
                'room.building:id,name,campus_id',
                'room.building.campus:id,name',
                'roomSchedules.room.building.campus',
            ])
            ->orderByDesc('created_at');

        $waitingStatuses = Booking::APPROVAL_PENDING_STATUSES;

        if ($user?->isAdmin()) {
            $roomSummary = null;
            $itemSummary = null;

            if ($user->canManageRoomModule()) {
                $bookings = $baseQuery->get();

                $roomSummary = [
                    'total' => $bookings->count(),
                    'approved' => $bookings->where('status', Booking::STATUS_APPROVED)->count(),
                    'waiting' => $bookings->whereIn('status', $waitingStatuses)->count(),
                    'needs_revision' => $bookings->where('status', Booking::STATUS_NEEDS_REVISION)->count(),
                    'rejected' => $bookings->where('status', Booking::STATUS_REJECTED)->count(),
                    'cancelled' => $bookings->where('status', Booking::STATUS_CANCELLED)->count(),
                    'expired' => $bookings->where('status', Booking::STATUS_EXPIRED)->count(),
                ];
            }

            if ($user->canManageItemModule()) {
                $itemBorrowings = ItemBorrowing::query()->with('items')->get();
                $itemSummary = [
                    'total' => $itemBorrowings->count(),
                    'approved' => $itemBorrowings->where('effective_status', ItemBorrowing::STATUS_APPROVED)->count(),
                    'waiting' => $itemBorrowings->whereIn('effective_status', [
                        ItemBorrowing::STATUS_WAITING,
                        ItemBorrowing::STATUS_NEEDS_REVISION,
                    ])->count(),
                    'rejected' => $itemBorrowings->where('effective_status', ItemBorrowing::STATUS_REJECTED)->count(),
                    'cancelled' => $itemBorrowings->where('effective_status', ItemBorrowing::STATUS_CANCELLED)->count(),
                    'completed' => $itemBorrowings->where('effective_status', ItemBorrowing::STATUS_COMPLETED)->count(),
                ];
            }

            return Inertia::render('Admin/Home', [
                'roomSummary' => $roomSummary,
                'itemSummary' => $itemSummary,
            ]);
        }

        $bookings = $baseQuery
            ->where('user_id', $user?->id)
            ->get();

        $roomSummary = [
            'total' => $bookings->count(),
            'approved' => $bookings->where('status', Booking::STATUS_APPROVED)->count(),
            'waiting' => $bookings->whereIn('status', $waitingStatuses)->count(),
            'needs_revision' => $bookings->where('status', Booking::STATUS_NEEDS_REVISION)->count(),
            'rejected' => $bookings->where('status', Booking::STATUS_REJECTED)->count(),
            'cancelled' => $bookings->where('status', Booking::STATUS_CANCELLED)->count(),
            'expired' => $bookings->where('status', Booking::STATUS_EXPIRED)->count(),
        ];

        $itemBorrowings = ItemBorrowing::query()
            ->with([
                'items.item:id,code,name,category',
                'singleItem:id,code,name,category',
            ])
            ->where('user_id', $user?->id)
            ->orderByDesc('created_at')
            ->get();

        $itemSummary = [
            'total' => $itemBorrowings->count(),
            'approved' => $itemBorrowings->where('effective_status', ItemBorrowing::STATUS_APPROVED)->count(),
            'waiting' => $itemBorrowings->where('effective_status', ItemBorrowing::STATUS_WAITING)->count(),
            'needs_revision' => $itemBorrowings->where('effective_status', ItemBorrowing::STATUS_NEEDS_REVISION)->count(),
            'rejected' => $itemBorrowings->where('effective_status', ItemBorrowing::STATUS_REJECTED)->count(),
            'cancelled' => $itemBorrowings->where('effective_status', ItemBorrowing::STATUS_CANCELLED)->count(),
            'completed' => $itemBorrowings->where('effective_status', ItemBorrowing::STATUS_COMPLETED)->count(),
        ];

        $combinedSummary = [
            'total' => $roomSummary['total'] + $itemSummary['total'],
            'approved' => $roomSummary['approved'] + $itemSummary['approved'],
            'waiting' => $roomSummary['waiting'] + $itemSummary['waiting'],
            'needs_revision' => $roomSummary['needs_revision'] + $itemSummary['needs_revision'],
            'rejected' => $roomSummary['rejected'] + $itemSummary['rejected'],
            'cancelled' => $roomSummary['cancelled'] + $itemSummary['cancelled'],
        ];

        $rooms = Room::query()
            ->select(['id', 'name', 'building_id', 'capacity', 'is_available'])
            ->with([
                'building:id,name,campus_id',
                'building.campus:id,name',
            ])
            ->orderBy('name')
            ->get();

        $campuses = Campus::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->with([
                'buildings' => function ($query) {
                    $query->select(['id', 'name', 'campus_id'])->orderBy('name');
                },
            ])
            ->get();

        $items = Item::query()
            ->select(['id', 'code', 'name', 'category', 'quantity', 'is_available'])
            ->orderBy('name')
            ->get();

        $requestHistory = $bookings
            ->map(fn (Booking $booking): array => [
                'key' => 'room-'.$booking->id,
                'type' => 'room',
                'id' => $booking->id,
                'title' => $booking->title,
                'resource_name' => $booking->room_summary,
                'schedule' => $booking->schedule_short_summary,
                'quantity' => null,
                'status' => $booking->status,
                'created_at' => $booking->created_at?->toIso8601String(),
            ])
            ->concat($itemBorrowings->map(function (ItemBorrowing $borrowing): array {
                $itemNames = $borrowing->items
                    ->pluck('item.name')
                    ->filter()
                    ->unique()
                    ->values();

                if ($itemNames->isEmpty() && $borrowing->singleItem) {
                    $itemNames->push($borrowing->singleItem->name);
                }

                $borrowDates = $borrowing->items->pluck('borrow_date')->filter();
                $returnDates = $borrowing->items->pluck('return_date')->filter();
                $firstBorrow = $borrowDates->min() ?? $borrowing->borrow_date;
                $lastReturn = $returnDates->max() ?? $borrowing->return_date;

                return [
                    'key' => 'item-'.$borrowing->id,
                    'type' => 'item',
                    'id' => $borrowing->id,
                    'title' => $borrowing->title,
                    'resource_name' => $itemNames->join(', ') ?: '-',
                    'schedule' => $this->formatItemSchedule($firstBorrow, $lastReturn),
                    'quantity' => $this->itemBorrowingQuantity($borrowing),
                    'status' => $borrowing->effective_status,
                    'created_at' => $borrowing->created_at?->toIso8601String(),
                ];
            }))
            ->sortByDesc('created_at')
            ->values();

        $businessTimezone = config('app.business_timezone');

        return Inertia::render('Dashboard', [
            'rooms' => $rooms,
            'campuses' => $campuses,
            'items' => $items,
            'roomSummary' => $roomSummary,
            'itemSummary' => $itemSummary,
            'combinedSummary' => $combinedSummary,
            'requestHistory' => $requestHistory,
            'minimumBookingDate' => Carbon::now($businessTimezone)->addDays(3)->toDateString(),
            'minimumBorrowDate' => Carbon::now($businessTimezone)->addDays(7)->toDateString(),
        ]);
    }

    private function formatItemSchedule(mixed $start, mixed $end): ?string
    {
        if (! $start) {
            return null;
        }

        $timezone = config('app.business_timezone');
        $startDate = Carbon::parse($start)->setTimezone($timezone);
        $endDate = $end ? Carbon::parse($end)->setTimezone($timezone) : null;

        if (! $endDate) {
            return $startDate->translatedFormat('d M Y, H:i');
        }

        if ($startDate->isSameDay($endDate)) {
            return $startDate->translatedFormat('d M Y, H:i').' - '.$endDate->format('H:i');
        }

        return $startDate->translatedFormat('d M Y, H:i').' - '.$endDate->translatedFormat('d M Y, H:i');
    }

    private function itemBorrowingQuantity(ItemBorrowing $borrowing): int
    {
        if ($borrowing->items->isEmpty()) {
            return (int) $borrowing->quantity;
        }

        return $borrowing->items
            ->groupBy('item_id')
            ->sum(function ($rows): int {
                $events = $rows
                    ->flatMap(fn ($row) => [
                        ['time' => $row->borrow_date->getTimestamp(), 'quantity' => (int) $row->quantity],
                        ['time' => $row->return_date->getTimestamp(), 'quantity' => -((int) $row->quantity)],
                    ])
                    ->sortBy([
                        ['time', 'asc'],
                        ['quantity', 'asc'],
                    ]);

                $active = 0;
                $peak = 0;

                foreach ($events as $event) {
                    $active += $event['quantity'];
                    $peak = max($peak, $active);
                }

                return $peak;
            });
    }
}
