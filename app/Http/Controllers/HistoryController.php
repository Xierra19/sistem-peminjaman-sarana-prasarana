<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\LogHistory;

class HistoryController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->role === 'admin') {
            $histories = LogHistory::with(['booking.room', 'user'])
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            $histories = LogHistory::with(['booking.room', 'user'])
                ->whereHas('booking', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })        

                ->orderBy('created_at', 'desc')
                ->get();
        }

        return inertia('History/Index', [
            'histories' => $histories,
        ]);
    }
}
