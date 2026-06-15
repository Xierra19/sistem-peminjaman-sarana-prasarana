<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\LogHistory;
use App\Models\Room;
use App\Models\User;
use App\Notifications\BookingRequestedNotification;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Throwable;

final class BookingCreationWorkflow
{
    public function __construct(
        private readonly PublicFileStorage $fileStorage,
        private readonly BookingScheduleConflictService $conflictService,
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
                    $source = $this->resolveResubmissionSource(
                        $validated['resubmitted_from_id'] ?? null,
                        $user,
                    );
                    $this->lockRoomsAndValidateAvailability($schedules);

                    $orderedSchedules = $schedules->sortBy('start_time')->values();
                    $firstSchedule = $orderedSchedules->first();
                    $start = Carbon::parse($schedules->min('start_time'));
                    $end = Carbon::parse($schedules->max('end_time'));

                    $booking = Booking::query()->create([
                        'room_id' => $firstSchedule['room_id'],
                        'user_id' => $user->id,
                        'resubmitted_from_id' => $source?->id,
                        'title' => $validated['title'],
                        'description' => $validated['description'] ?? null,
                        'status' => Booking::STATUS_WAITING,
                        'attachment' => $attachmentPath ?: $source?->attachment,
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
                        'description' => $source
                            ? "Booking diajukan ulang dari pengajuan #{$source->id}."
                            : 'Booking diajukan oleh pengguna.',
                    ]);

                    return $booking;
                });
            },
        );

        $booking->load(['user', 'roomSchedules.room.building.campus']);
        $this->notifyAdmins($booking);

        return $booking;
    }

    public function updateRevision(
        array $validated,
        Booking $booking,
        ?UploadedFile $attachment,
        User $user,
    ): void {
        $schedules = $this->expandSchedules($validated['schedules']);
        $this->validateSchedulesWithinRequest($schedules);
        $oldAttachmentPath = null;

        $this->fileStorage->runWithStoredFile(
            $attachment,
            'attachments',
            function (?string $newAttachmentPath) use (
                $validated,
                $schedules,
                $booking,
                $user,
                &$oldAttachmentPath,
            ): void {
                DB::transaction(function () use (
                    $validated,
                    $schedules,
                    $booking,
                    $user,
                    $newAttachmentPath,
                    &$oldAttachmentPath,
                ): void {
                    $lockedBooking = Booking::query()
                        ->lockForUpdate()
                        ->findOrFail($booking->id);

                    Gate::forUser($user)->authorize('update', $lockedBooking);
                    $this->lockRoomsAndValidateAvailability($schedules, $lockedBooking->id);

                    $orderedSchedules = $schedules->sortBy('start_time')->values();
                    $firstSchedule = $orderedSchedules->first();
                    $start = Carbon::parse($orderedSchedules->min('start_time'));
                    $end = Carbon::parse($orderedSchedules->max('end_time'));
                    $updateData = [
                        'room_id' => $firstSchedule['room_id'],
                        'title' => $validated['title'],
                        'description' => $validated['description'] ?? null,
                        'status' => Booking::STATUS_WAITING,
                        'start_time' => $start->toDateTimeString(),
                        'end_time' => $end->toDateTimeString(),
                        'schedule_mode' => Booking::MODE_CONTINUOUS,
                        'schedule_start_date' => $start->toDateString(),
                        'schedule_end_date' => $end->toDateString(),
                        'schedule_start_clock' => $start->format('H:i:s'),
                        'schedule_end_clock' => $end->format('H:i:s'),
                    ];

                    if ($newAttachmentPath) {
                        $oldAttachmentPath = $lockedBooking->attachment;
                        $updateData['attachment'] = $newAttachmentPath;
                    }

                    $lockedBooking->update($updateData);
                    $lockedBooking->roomSchedules()->delete();
                    $lockedBooking->roomSchedules()->createMany(
                        $orderedSchedules
                            ->map(fn (array $schedule) => collect($schedule)->except('input_index')->all())
                            ->all(),
                    );

                    LogHistory::query()->create([
                        'booking_id' => $lockedBooking->id,
                        'user_id' => $user->id,
                        'action' => 'revised',
                        'description' => 'Revisi booking dikirim dan menunggu persetujuan ulang.',
                    ]);

                    $booking->setRawAttributes($lockedBooking->getAttributes(), true);
                });
            },
        );

        if (
            $oldAttachmentPath
            && ! Booking::query()->where('attachment', $oldAttachmentPath)->exists()
        ) {
            $this->fileStorage->delete($oldAttachmentPath);
        }
        $booking->load(['user', 'roomSchedules.room.building.campus']);
        $this->notifyAdmins($booking);
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

    private function lockRoomsAndValidateAvailability(
        Collection $schedules,
        ?int $excludeBookingId = null,
    ): void {
        $rooms = Room::query()
            ->whereIn('id', $schedules->pluck('room_id')->unique()->sort()->values())
            ->orderBy('id')
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

            if ($this->conflictService->hasApprovedConflict(collect([$schedule]), $excludeBookingId)) {
                throw ValidationException::withMessages([
                    "schedules.{$inputIndex}.room_id" => 'Ruangan sudah disetujui untuk digunakan pada tanggal dan jam yang dipilih.',
                ]);
            }
        }
    }

    private function resolveResubmissionSource(?int $sourceId, User $user): ?Booking
    {
        if (! $sourceId) {
            return null;
        }

        $source = Booking::query()
            ->lockForUpdate()
            ->findOrFail($sourceId);

        if (
            $source->user_id !== $user->id
            || ! $source->canBeResubmitted()
        ) {
            throw ValidationException::withMessages([
                'resubmitted_from_id' => 'Pengajuan sumber tidak valid untuk diajukan ulang.',
            ]);
        }

        return $source;
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
