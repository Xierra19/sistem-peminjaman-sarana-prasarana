<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\LogHistory;

class HistoryController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->role === 'admin') {
            // Admin lihat semua log history
            $histories = LogHistory::with(['booking.room', 'user'])
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            // User biasa hanya lihat history miliknya
            $histories = LogHistory::with(['booking.room'])
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return inertia('History/Index', [
            'histories' => $histories,
        ]);
    }
}
