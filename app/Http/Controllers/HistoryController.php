<?php

namespace App\Http\Controllers;

use App\Exports\HistoryExport;
use App\Models\LogHistory;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class HistoryController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        if (! $user?->canManageHistory()) {
            abort(403);
        }

        $histories = LogHistory::with([
            'booking.roomSchedules.room.building.campus',
            'user:id,name,email',
        ])
            ->orderByDesc('created_at')
            ->get();

        return inertia('History/Index', [
            'histories' => $histories,
            'actionOptions' => LogHistory::query()
                ->whereNotNull('action')
                ->distinct()
                ->orderBy('action')
                ->pluck('action')
                ->values(),
            'roomOptions' => Room::query()
                ->with('building.campus')
                ->orderBy('name')
                ->get()
                ->map(fn (Room $room): array => [
                    'id' => $room->id,
                    'name' => $room->name,
                    'location' => collect([
                        $room->building?->name,
                        $room->building?->campus?->name,
                    ])->filter()->join(' · '),
                ])
                ->values(),
        ]);
    }

    public function exportExcel(Request $request)
    {
        $user = Auth::user();

        if (! $user?->canManageHistory()) {
            abort(403);
        }

        $filters = $this->validatedFilters($request);
        $fileName = 'log-history-booking-'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(new HistoryExport($user, $filters), $fileName);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFilters(Request $request): array
    {
        $actionOptions = LogHistory::query()
            ->whereNotNull('action')
            ->distinct()
            ->pluck('action')
            ->all();

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', Rule::in($actionOptions)],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $filters = array_merge([
            'search' => null,
            'action' => null,
            'room_id' => null,
            'start_date' => null,
            'end_date' => null,
        ], $validated);

        if (
            $filters['start_date']
            && $filters['end_date']
            && $filters['start_date'] > $filters['end_date']
        ) {
            [$filters['start_date'], $filters['end_date']] = [
                $filters['end_date'],
                $filters['start_date'],
            ];
        }

        return $filters;
    }
}
