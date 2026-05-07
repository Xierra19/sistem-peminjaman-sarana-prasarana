<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Campus;
use App\Models\ItemBorrowing;
use App\Models\Room;
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
            ])
            ->orderByDesc('created_at');

        $waitingStatuses = ['waiting', 'pending'];

        if ($user?->isAdmin()) {
            $roomSummary = null;
            $itemSummary = null;

            if ($user->canManageRoomModule()) {
                $bookings = $baseQuery->get();

                $roomSummary = [
                    'total' => $bookings->count(),
                    'approved' => $bookings->where('status', 'approved')->count(),
                    'waiting' => $bookings->whereIn('status', $waitingStatuses)->count(),
                    'rejected' => $bookings->where('status', 'rejected')->count(),
                    'cancelled' => $bookings->where('status', 'cancelled')->count(),
                ];
            }

            if ($user->canManageItemModule()) {
                $itemBorrowings = ItemBorrowing::query()->get();
                $itemSummary = [
                    'total' => $itemBorrowings->count(),
                    'approved' => $itemBorrowings->where('status', 'approved')->count(),
                    'waiting' => $itemBorrowings->whereIn('status', ['waiting', 'requested'])->count(),
                    'rejected' => $itemBorrowings->where('status', 'rejected')->count(),
                    'cancelled' => $itemBorrowings->where('status', 'cancelled')->count(),
                    'returned' => $itemBorrowings->where('status', 'returned')->count(),
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

        $statusSummary = [
            'total' => $bookings->count(),
            'approved' => $bookings->where('status', 'approved')->count(),
            'waiting' => $bookings->whereIn('status', $waitingStatuses)->count(),
            'rejected' => $bookings->where('status', 'rejected')->count(),
            'cancelled' => $bookings->where('status', 'cancelled')->count(),
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

        return Inertia::render('Dashboard', [
            'rooms' => $rooms,
            'campuses' => $campuses,
            'recentBookings' => $bookings->take(5)->values(),
            'bookingSummary' => $statusSummary,
        ]);
    }
}
