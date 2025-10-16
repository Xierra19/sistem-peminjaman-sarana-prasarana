<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Exports\HistoryExport;
use App\Models\LogHistory;
use Maatwebsite\Excel\Facades\Excel;


class HistoryController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->role === 'admin') {
            $histories = LogHistory::with(['booking.room.building.campus', 'user'])
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            $histories = LogHistory::with(['booking.room.building.campus', 'user'])
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

    public function exportExcel()
    {
        $user = Auth::user();

        return Excel::download(new HistoryExport($user), 'log-history-booking.xlsx');
    }
}