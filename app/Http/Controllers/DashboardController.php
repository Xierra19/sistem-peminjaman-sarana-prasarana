<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $bookings = Booking::with('user', 'room')->get();

        return Inertia::render('Dashboard', [
            'bookings' => $bookings,
        ]);
    }
}
