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

        $query = LogHistory::with(['booking.room.building.campus', 'user'])
            ->orderByDesc('created_at');

        if ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        }

        $histories = $query->get();

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
