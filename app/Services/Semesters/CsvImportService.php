<?php

namespace App\Services\Semesters;

use App\DataTransferObjects\Semesters\DuplicateDto;
use App\DataTransferObjects\Semesters\PreviewResult;
use App\DataTransferObjects\Semesters\PreviewRow;
use App\DataTransferObjects\Semesters\SemesterDefaultImportRow;
use App\Models\Room;
use App\Models\SemesterCourseDefault;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CsvImportService
{
    /** @var array<int,string|null> */
    protected array $roomNameCache = [];

    public function __construct(
        protected OverlapChecker $overlapChecker,
        protected ExactDuplicateChecker $duplicateChecker
    ) {
    }

    /**
     * @return SemesterDefaultImportRow[]
     */
    public function parse(string $csv): array
    {
        $csv = ltrim($csv, "\xEF\xBB\xBF");
        $lines = preg_split("/(\r\n|\n|\r)/", trim($csv));
        if (! $lines) {
            return [];
        }

        $header = array_map('trim', str_getcsv(array_shift($lines) ?? ''));
        $expected = ['course_name','course_code','theory_time','practicum1_time','practicum2_time','day_of_week','theory_room_code','practicum1_room_code','practicum2_room_code'];

        if ($header !== $expected) {
            throw ValidationException::withMessages([
                'file' => 'Format header CSV tidak sesuai. Harus: '.implode(',', $expected),
            ]);
        }

        $rows = [];
        $lineNumber = 2; // setelah header
        foreach ($lines as $line) {
            if (trim($line) === '') {
                $lineNumber++;
                continue;
            }
            $columns = array_map('trim', str_getcsv($line));
            $columns = array_pad($columns, count($expected), null);

            $rows[] = new SemesterDefaultImportRow(
                $lineNumber,
                $columns[0] ?? '',
                $columns[1] ?? '',
                $columns[2] ?? '',
                $columns[3] ?: null,
                $columns[4] ?: null,
                $columns[5] ?? '',
                $columns[6] ?: null,
                $columns[7] ?: null,
                $columns[8] ?: null,
            );

            $lineNumber++;
        }

        return $rows;
    }

    public function preview(int $semesterId, array $rows): PreviewResult
    {
        $roomMap = Room::query()->pluck('id', 'name')->mapWithKeys(function ($id, $name) {
            return [Str::upper($name) => $id];
        });

        $dayMap = [
            'MON' => 'Mon',
            'TUE' => 'Tue',
            'WED' => 'Wed',
            'THU' => 'Thu',
            'FRI' => 'Fri',
            'SAT' => 'Sat',
            'SUN' => 'Sun',
        ];

        $previewRows = [];

        foreach ($rows as $row) {
            $errors = [];
            $normalizedDay = $dayMap[Str::upper(trim($row->dayOfWeek))] ?? null;
            if (! $normalizedDay) {
                $errors[] = 'Hari tidak valid.';
            }

            $theory = $this->parseTimeRange($row->theoryTime);
            if (! $theory) {
                $errors[] = 'Format jam teori tidak valid.';
            }

            $prac1 = $row->practicum1Time ? $this->parseTimeRange($row->practicum1Time) : null;
            if ($row->practicum1Time && ! $prac1) {
                $errors[] = 'Format jam praktikum 1 tidak valid.';
            }

            $prac2 = $row->practicum2Time ? $this->parseTimeRange($row->practicum2Time) : null;
            if ($row->practicum2Time && ! $prac2) {
                $errors[] = 'Format jam praktikum 2 tidak valid.';
            }

            $theoryRoomId = $this->resolveRoom($roomMap, $row->theoryRoomCode);
            if ($row->theoryRoomCode && ! $theoryRoomId) {
                $errors[] = 'Ruang teori dengan kode '.$row->theoryRoomCode.' tidak ditemukan.';
            }

            $prac1RoomId = $this->resolveRoom($roomMap, $row->practicum1RoomCode);
            if ($row->practicum1RoomCode && ! $prac1RoomId) {
                $errors[] = 'Ruang praktikum 1 dengan kode '.$row->practicum1RoomCode.' tidak ditemukan.';
            }

            $prac2RoomId = $this->resolveRoom($roomMap, $row->practicum2RoomCode);
            if ($row->practicum2RoomCode && ! $prac2RoomId) {
                $errors[] = 'Ruang praktikum 2 dengan kode '.$row->practicum2RoomCode.' tidak ditemukan.';
            }

            if (empty(trim($row->courseName))) {
                $errors[] = 'Nama mata kuliah wajib diisi.';
            }

            if (empty(trim($row->courseCode))) {
                $errors[] = 'Kode mata kuliah wajib diisi.';
            }

            $normalized = [
                'course_name' => trim($row->courseName),
                'course_code' => trim($row->courseCode),
                'day_of_week' => $normalizedDay,
                'theory_start_time' => $theory['start'] ?? null,
                'theory_end_time' => $theory['end'] ?? null,
                'theory_room_id' => $theoryRoomId,
                'practicum1_start_time' => $prac1['start'] ?? null,
                'practicum1_end_time' => $prac1['end'] ?? null,
                'practicum1_room_id' => $prac1RoomId,
                'practicum2_start_time' => $prac2['start'] ?? null,
                'practicum2_end_time' => $prac2['end'] ?? null,
                'practicum2_room_id' => $prac2RoomId,
            ];

            if (! $normalized['theory_start_time'] || ! $normalized['theory_end_time']) {
                $errors[] = 'Jam teori wajib diisi.';
            }

            $previewRows[] = new PreviewRow($row, $normalized, $errors);
        }

        // Duplikat antar baris file
        $this->markDuplicatesWithinFile($previewRows);

        // Overlap antar baris file
        $this->markOverlapWithinFile($previewRows);

        // Validasi terhadap database
        foreach ($previewRows as $previewRow) {
            if ($previewRow->hasErrors()) {
                continue;
            }

            $duplicate = $this->duplicateChecker->check($semesterId, $previewRow->normalizedData);
            if ($duplicate) {
                $previewRow->errors[] = $this->duplicateMessage($duplicate, $previewRow->normalizedData['course_code']);
            }

            $this->checkOverlapAgainstDatabase($semesterId, $previewRow);
        }

        return new PreviewResult($previewRows);
    }

    /**
     * @param array<int,array<string,mixed>> $normalizedRows
     */
    public function commit(int $semesterId, array $normalizedRows): void
    {
        DB::transaction(function () use ($semesterId, $normalizedRows) {
            foreach ($normalizedRows as $data) {
                $data['day_of_week'] = $data['day_of_week'] ?? null;
                if (! $data['day_of_week']) {
                    throw ValidationException::withMessages([
                        'file' => 'Data import tidak valid.',
                    ]);
                }

                $duplicate = $this->duplicateChecker->check($semesterId, $data);
                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'file' => $this->duplicateMessage($duplicate, $data['course_code']),
                    ]);
                }

                $this->assertNoOverlap($semesterId, $data);

                SemesterCourseDefault::updateOrCreate([
                    'semester_id' => $semesterId,
                    'course_name' => $data['course_name'],
                    'course_code' => $data['course_code'],
                    'day_of_week' => $data['day_of_week'],
                    'theory_start_time' => $data['theory_start_time'],
                    'theory_end_time' => $data['theory_end_time'],
                    'theory_room_id' => $data['theory_room_id'],
                    'practicum1_start_time' => $data['practicum1_start_time'],
                    'practicum1_end_time' => $data['practicum1_end_time'],
                    'practicum1_room_id' => $data['practicum1_room_id'],
                    'practicum2_start_time' => $data['practicum2_start_time'],
                    'practicum2_end_time' => $data['practicum2_end_time'],
                    'practicum2_room_id' => $data['practicum2_room_id'],
                ], [
                    'semester_id' => $semesterId,
                    'course_name' => $data['course_name'],
                    'course_code' => $data['course_code'],
                    'day_of_week' => $data['day_of_week'],
                    'theory_start_time' => $data['theory_start_time'],
                    'theory_end_time' => $data['theory_end_time'],
                    'theory_room_id' => $data['theory_room_id'],
                    'practicum1_start_time' => $data['practicum1_start_time'],
                    'practicum1_end_time' => $data['practicum1_end_time'],
                    'practicum1_room_id' => $data['practicum1_room_id'],
                    'practicum2_start_time' => $data['practicum2_start_time'],
                    'practicum2_end_time' => $data['practicum2_end_time'],
                    'practicum2_room_id' => $data['practicum2_room_id'],
                ]);
            }
        });
    }

    private function parseTimeRange(string $value): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (! preg_match('/^(\d{2}:\d{2})\s*-\s*(\d{2}:\d{2})$/', $value, $matches)) {
            return null;
        }

        [$full, $start, $end] = $matches;

        if ($start >= $end) {
            return null;
        }

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    private function resolveRoom(Collection $roomMap, ?string $code): ?int
    {
        if (! $code) {
            return null;
        }

        $key = Str::upper(trim($code));
        return $roomMap[$key] ?? null;
    }

    private function roomLabel(?int $roomId, ?string $fallbackCode = null): string
    {
        if (! $roomId) {
            return $fallbackCode ?? 'Tanpa ruang';
        }

        if (! array_key_exists($roomId, $this->roomNameCache)) {
            $this->roomNameCache[$roomId] = Room::find($roomId)?->name;
        }

        return $this->roomNameCache[$roomId] ?? ($fallbackCode ?? 'ID '.$roomId);
    }

    /** @param PreviewRow[] $previewRows */
    private function markDuplicatesWithinFile(array &$previewRows): void
    {
        $seen = [];

        foreach ($previewRows as $index => $row) {
            if ($row->hasErrors()) {
                continue;
            }

            foreach ($this->slotDefinitions($row) as $slotData) {
                $key = implode('|', [
                    $slotData['type'],
                    $slotData['day'],
                    $slotData['room_id'] ?? 'null',
                    $slotData['start'],
                    $slotData['end'],
                    $slotData['course_code'],
                ]);

                if (! isset($seen[$key])) {
                    $seen[$key] = $index;
                    continue;
                }

                $otherIndex = $seen[$key];
                $message = sprintf(
                    'Baris duplikat: %s dengan room yang sama di hari & jam yang sama sudah ada (course_code: %s).',
                    $slotData['type'],
                    $slotData['course_code']
                );
                $row->errors[] = $message;
                $previewRows[$otherIndex]->errors[] = $message;
            }
        }
    }

    /** @param PreviewRow[] $previewRows */
    private function markOverlapWithinFile(array &$previewRows): void
    {
        $slotsByRoom = [];

        foreach ($previewRows as $index => $row) {
            if ($row->hasErrors()) {
                continue;
            }

            foreach ($this->slotDefinitions($row) as $slotData) {
                if (! $slotData['room_id']) {
                    continue;
                }

                $slotsByRoom[$slotData['day']][$slotData['room_id']][] = $slotData + ['index' => $index];
            }
        }

        foreach ($slotsByRoom as $day => $rooms) {
            foreach ($rooms as $roomId => $slots) {
                usort($slots, fn ($a, $b) => strcmp($a['start'], $b['start']));

                $count = count($slots);
                for ($i = 0; $i < $count; $i++) {
                    for ($j = $i + 1; $j < $count; $j++) {
                        if ($this->overlapChecker->overlaps($slots[$i]['start'], $slots[$i]['end'], $slots[$j]['start'], $slots[$j]['end'])) {
                            $roomLabel = $this->roomLabel($roomId, $slots[$i]['room_code']);

                            $messageI = sprintf(
                                'Bentrok: Ruang %s pada %s %s-%s bentrok dengan baris #%d (%s).',
                                $roomLabel,
                                $day,
                                $slots[$i]['start'],
                                $slots[$i]['end'],
                                $previewRows[$slots[$j]['index']]->raw->lineNumber,
                                $slots[$j]['course_code']
                            );

                            $messageJ = sprintf(
                                'Bentrok: Ruang %s pada %s %s-%s bentrok dengan baris #%d (%s).',
                                $roomLabel,
                                $day,
                                $slots[$j]['start'],
                                $slots[$j]['end'],
                                $previewRows[$slots[$i]['index']]->raw->lineNumber,
                                $slots[$i]['course_code']
                            );

                            $previewRows[$slots[$i]['index']]->errors[] = $messageI;
                            $previewRows[$slots[$j]['index']]->errors[] = $messageJ;
                        }
                    }
                }
            }
        }
    }

    private function duplicateMessage(DuplicateDto $duplicate, string $courseCode): string
    {
        $existing = $duplicate->record;
        return sprintf(
            'Baris duplikat: %s dengan room yang sama di hari & jam yang sama sudah ada (course_code: %s).',
            $duplicate->slotType,
            $existing->course_code ?? $courseCode
        );
    }

    private function checkOverlapAgainstDatabase(int $semesterId, PreviewRow $row): void
    {
        foreach ($this->slotDefinitions($row) as $slot) {
            if (! $slot['room_id']) {
                continue;
            }

            $conflict = $this->overlapChecker->checkRoomOverlap($semesterId, $slot['room_id'], $slot['day'], $slot['start'], $slot['end']);
            if ($conflict) {
                $row->errors[] = sprintf(
                    'Bentrok: Ruang %s pada %s %s-%s sudah dipakai oleh %s (ID #%d).',
                    $this->roomLabel($slot['room_id'], $slot['room_code']),
                    $slot['day'],
                    $slot['start'],
                    $slot['end'],
                    $conflict->record->course_code.' / '.$conflict->record->course_name,
                    $conflict->record->id
                );
            }
        }
    }

    private function assertNoOverlap(int $semesterId, array $data): void
    {
        foreach (['theory' => 'theory_room_id', 'practicum1' => 'practicum1_room_id', 'practicum2' => 'practicum2_room_id'] as $slot => $roomField) {
            $roomId = $data[$roomField] ?? null;
            if (! $roomId) {
                continue;
            }

            $startField = $slot.'_start_time';
            $endField = $slot.'_end_time';

            $conflict = $this->overlapChecker->checkRoomOverlap($semesterId, $roomId, $data['day_of_week'], $data[$startField], $data[$endField]);
            if ($conflict) {
                throw ValidationException::withMessages([
                    'file' => sprintf(
                        'Bentrok: Ruang %s pada %s %s-%s sudah dipakai oleh %s (ID #%d).',
                        $this->roomLabel($roomId),
                        $data['day_of_week'],
                        $data[$startField],
                        $data[$endField],
                        $conflict->record->course_code.' / '.$conflict->record->course_name,
                        $conflict->record->id
                    ),
                ]);
            }
        }
    }

    private function slotDefinitions(PreviewRow $row): array
    {
        $data = $row->normalizedData;
        $day = $data['day_of_week'];
        $slots = [];

        if ($day && $data['theory_start_time'] && $data['theory_end_time']) {
            $slots[] = [
                'type' => 'Teori',
                'day' => $day,
                'start' => $data['theory_start_time'],
                'end' => $data['theory_end_time'],
                'room_id' => $data['theory_room_id'],
                'room_code' => $row->raw->theoryRoomCode,
                'course_code' => $data['course_code'],
                'course_name' => $data['course_name'],
                'line' => $row->raw->lineNumber,
            ];
        }

        if ($day && $data['practicum1_start_time'] && $data['practicum1_end_time']) {
            $slots[] = [
                'type' => 'Praktikum 1',
                'day' => $day,
                'start' => $data['practicum1_start_time'],
                'end' => $data['practicum1_end_time'],
                'room_id' => $data['practicum1_room_id'],
                'room_code' => $row->raw->practicum1RoomCode,
                'course_code' => $data['course_code'],
                'course_name' => $data['course_name'],
                'line' => $row->raw->lineNumber,
            ];
        }

        if ($day && $data['practicum2_start_time'] && $data['practicum2_end_time']) {
            $slots[] = [
                'type' => 'Praktikum 2',
                'day' => $day,
                'start' => $data['practicum2_start_time'],
                'end' => $data['practicum2_end_time'],
                'room_id' => $data['practicum2_room_id'],
                'room_code' => $row->raw->practicum2RoomCode,
                'course_code' => $data['course_code'],
                'course_name' => $data['course_name'],
                'line' => $row->raw->lineNumber,
            ];
        }

        return $slots;
    }
}