<?php

namespace App\Http\Controllers;

use App\Models\Booking;
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

        if ($user?->role === 'admin') {
            $bookings = $baseQuery->get();
        } else {
            $bookings = $baseQuery
                ->where('user_id', $user?->id)
                ->get();
        }

        $waitingStatuses = ['waiting', 'pending'];

        $statusSummary = [
            'total' => $bookings->count(),
            'approved' => $bookings->where('status', 'approved')->count(),
            'waiting' => $bookings->whereIn('status', $waitingStatuses)->count(),
            'rejected' => $bookings->where('status', 'rejected')->count(),
        ];

        return Inertia::render('Dashboard', [
            'bookings' => $bookings,
            'statusSummary' => $statusSummary,
        ]);
    }
}
