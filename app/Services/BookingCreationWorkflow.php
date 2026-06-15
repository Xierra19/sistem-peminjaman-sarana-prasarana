<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingRoomSchedule;
use App\Models\LogHistory;
use App\Models\Room;
use App\Models\User;
use App\Notifications\BookingRequestedNotification;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Throwable;

final class BookingCreationWorkflow
{
    public function __construct(
        private readonly PublicFileStorage $fileStorage,
    ) {}

    public function create(array $validated, ?UploadedFile $attachment, User $user): Booking
    {
        $schedules = $this->expandSchedules($validated['schedules']);
        $this->validateSchedulesWithinRequest($schedules);

        $booking = $this->fileStorage->runWithStoredFile(
            $attachment,
            'attachments',
            function (?string $attachmentPath) use ($validated, $schedules, $user): Booking {
                return DB::transaction(function () use ($validated, $schedules, $user, $attachmentPath): Booking {
                    $this->lockRoomsAndValidateAvailability($schedules);

                    $orderedSchedules = $schedules->sortBy('start_time')->values();
                    $firstSchedule = $orderedSchedules->first();
                    $start = Carbon::parse($schedules->min('start_time'));
                    $end = Carbon::parse($schedules->max('end_time'));

                    $booking = Booking::query()->create([
                        'room_id' => $firstSchedule['room_id'],
                        'user_id' => $user->id,
                        'title' => $validated['title'],
                        'description' => $validated['description'] ?? null,
                        'status' => Booking::STATUS_WAITING,
                        'attachment' => $attachmentPath,
                        'start_time' => $start->toDateTimeString(),
                        'end_time' => $end->toDateTimeString(),
                        'schedule_mode' => Booking::MODE_CONTINUOUS,
                        'schedule_start_date' => $start->toDateString(),
                        'schedule_end_date' => $end->toDateString(),
                        'schedule_start_clock' => $start->format('H:i:s'),
                        'schedule_end_clock' => $end->format('H:i:s'),
                    ]);

                    $booking->roomSchedules()->createMany(
                        $orderedSchedules
                            ->map(fn (array $schedule) => collect($schedule)->except('input_index')->all())
                            ->all(),
                    );

                    LogHistory::query()->create([
                        'booking_id' => $booking->id,
                        'user_id' => $user->id,
                        'action' => 'requested',
                        'description' => 'Booking diajukan oleh pengguna.',
                    ]);

                    return $booking;
                });
            },
        );

        $booking->load(['user', 'roomSchedules.room.building.campus']);
        $this->notifyAdmins($booking);

        return $booking;
    }

    private function expandSchedules(array $scheduleRows): Collection
    {
        $schedules = collect($scheduleRows)
            ->flatMap(fn (array $schedule, int $index) => collect($schedule['dates'])
                ->map(fn (string $date) => [
                    'input_index' => $index,
                    'room_id' => (int) $schedule['room_id'],
                    'start_time' => Carbon::parse($date.' '.$schedule['start_time'])->toDateTimeString(),
                    'end_time' => Carbon::parse($date.' '.$schedule['end_time'])->toDateTimeString(),
                ]))
            ->values();

        if ($schedules->count() > 20) {
            throw ValidationException::withMessages([
                'schedules' => 'Maksimal 20 jadwal penggunaan dalam satu pengajuan.',
            ]);
        }

        return $schedules;
    }

    private function validateSchedulesWithinRequest(Collection $schedules): void
    {
        foreach ($schedules->groupBy('room_id') as $roomSchedules) {
            $orderedSchedules = $roomSchedules->sortBy('start_time')->values();

            for ($index = 1; $index < $orderedSchedules->count(); $index++) {
                $previous = $orderedSchedules[$index - 1];
                $current = $orderedSchedules[$index];

                if (Carbon::parse($current['start_time'])->lt(Carbon::parse($previous['end_time']))) {
                    throw ValidationException::withMessages([
                        "schedules.{$current['input_index']}.start_time" => 'Jadwal ruangan ini bertumpuk dengan baris lain dalam pengajuan yang sama.',
                    ]);
                }
            }
        }
    }

    private function lockRoomsAndValidateAvailability(Collection $schedules): void
    {
        $rooms = Room::query()
            ->whereIn('id', $schedules->pluck('room_id')->unique()->sort()->values())
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($schedules as $schedule) {
            $room = $rooms->get($schedule['room_id']);
            $inputIndex = $schedule['input_index'];

            if (! $room?->is_available) {
                throw ValidationException::withMessages([
                    "schedules.{$inputIndex}.room_id" => 'Ruangan sedang ditandai tidak tersedia.',
                ]);
            }

            if ($this->roomHasScheduleConflict($schedule)) {
                throw ValidationException::withMessages([
                    "schedules.{$inputIndex}.room_id" => 'Ruangan sudah digunakan pada tanggal dan jam yang dipilih.',
                ]);
            }
        }
    }

    private function roomHasScheduleConflict(array $schedule): bool
    {
        $hasDetailConflict = BookingRoomSchedule::query()
            ->where('room_id', $schedule['room_id'])
            ->where('start_time', '<', $schedule['end_time'])
            ->where('end_time', '>', $schedule['start_time'])
            ->whereHas('booking', function ($query): void {
                $query->whereNotIn('status', Booking::INACTIVE_STATUSES);
            })
            ->exists();

        if ($hasDetailConflict) {
            return true;
        }

        return Booking::query()
            ->where('room_id', $schedule['room_id'])
            ->whereDoesntHave('roomSchedules')
            ->whereNotIn('status', Booking::INACTIVE_STATUSES)
            ->where('start_time', '<', $schedule['end_time'])
            ->where('end_time', '>', $schedule['start_time'])
            ->exists();
    }

    private function notifyAdmins(Booking $booking): void
    {
        $admins = User::query()
            ->where('role', User::ROLE_ADMIN_BAP)
            ->whereNotNull('email')
            ->get();

        if ($admins->isEmpty()) {
            return;
        }

        try {
            Notification::send($admins, new BookingRequestedNotification($booking));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
