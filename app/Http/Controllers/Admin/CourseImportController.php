<?php

// file: app/Http/Controllers/Admin/CourseImportController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CourseImportRequest;
use App\Models\CourseExamSchedule;
use App\Models\CourseMeeting;
use App\Models\CourseOffering;
use App\Models\Room;
use App\Models\Semester;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CourseImportController extends Controller
{
    public function create(): Response
    {
        $semesters = Semester::query()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get()
            ->map(function (Semester $semester) {
                $ranges = $semester->dateRangeStrings();
                $labelParts = array_filter([
                    $semester->name ?? null,
                    $ranges['semester'] ?? null,
                ]);

                return [
                    'id' => $semester->id,
                    'label' => $labelParts ? implode(' | ', $labelParts) : 'Semester #' . $semester->id,
                    'ranges' => Arr::only($ranges, ['uts', 'uas']),
                ];
            })
            ->values();

        return Inertia::render('Admin/Courses/Import', [
            'semesters' => $semesters,
        ]);
    }

    public function store(CourseImportRequest $request): RedirectResponse
    {
        $semester = Semester::query()->findOrFail((int) $request->input('semester_id'));
        $file = $request->file('file');
        $rows = $this->readRows($file);

        $summary = [
            'offerings_created' => 0,
            'offerings_updated' => 0,
            'meetings_upserted' => 0,
            'exams_upserted' => 0,
            'unknown_room_codes' => [],
            'invalid_dates' => [],
            'rows_processed' => 0,
            'rows' => [],
        ];

        DB::transaction(function () use (&$summary, $rows, $semester): void {
            foreach ($rows as $rowIndex => $row) {
                if ($this->rowIsEmpty($row)) {
                    continue;
                }

                $summary['rows_processed']++;
                $rowNumber = $rowIndex + 2; // account for header row
                $rowReport = [
                    'row' => $rowNumber,
                    'course_code' => $this->extractValue($row, ['kode mata kuliah']),
                    'status' => 'skipped',
                    'errors' => [],
                ];

                $courseCode = $rowReport['course_code'];

                if ($courseCode === '') {
                    $rowReport['errors'][] = 'Missing course code';
                    $summary['rows'][] = $rowReport;
                    continue;
                }

                $courseName = $this->extractValue($row, ['nama mata kuliah']) ?: $courseCode;
                $classGroup = $this->extractValue($row, ['class group', 'kelas', 'class']);

                $offering = CourseOffering::query()->updateOrCreate(
                    [
                        'semester_id' => $semester->id,
                        'course_code' => $courseCode,
                    ],
                    [
                        'course_name' => $courseName,
                        'class_group' => $classGroup !== '' ? $classGroup : null,
                    ]
                );

                if ($offering->wasRecentlyCreated) {
                    $summary['offerings_created']++;
                    $rowReport['status'] = 'created';
                } else {
                    $summary['offerings_updated']++;
                    $rowReport['status'] = 'updated';
                }

                $meetingDates = [];
                for ($i = 1; $i <= 14; $i++) {
                    $label = 'Pertemuan ' . $i;
                    $value = $this->extractValue($row, [strtolower($label)]);
                    $date = $this->normalizeDate($value, function (string $invalid) use (&$summary, &$rowReport, $rowNumber, $label): void {
                        $message = "Row {$rowNumber} {$label}: {$invalid}";
                        $summary['invalid_dates'][] = $message;
                        $rowReport['errors'][] = "Invalid {$label} date '{$invalid}'";
                    });

                    if ($date !== null) {
                        $meetingDates[$i] = $date;
                    }
                }

                $this->processPracticumColumns($row, $rowNumber, $summary, $rowReport, $meetingDates);

                ksort($meetingDates);

                foreach ($meetingDates as $meetingNo => $meetingDate) {
                    CourseMeeting::query()->updateOrCreate(
                        [
                            'course_offering_id' => $offering->id,
                            'meeting_no' => $meetingNo,
                        ],
                        [
                            'meeting_date' => $meetingDate,
                        ]
                    );

                    $summary['meetings_upserted']++;
                }

                $exams = [
                    'UTS' => [
                        'dateKey' => 'tanggal uts',
                        'roomKey' => 'ruangan uts',
                    ],
                    'UAS' => [
                        'dateKey' => 'tanggal uas',
                        'roomKey' => 'ruangan uas',
                    ],
                ];

                foreach ($exams as $type => $config) {
                    $dateLabel = Str::title($config['dateKey']);
                    $rawDate = $this->extractValue($row, [$config['dateKey']]);
                    $normalizedDate = $this->normalizeDate($rawDate, function (string $invalid) use (&$summary, &$rowReport, $rowNumber, $dateLabel): void {
                        $message = "Row {$rowNumber} {$dateLabel}: {$invalid}";
                        $summary['invalid_dates'][] = $message;
                        $rowReport['errors'][] = "Invalid {$dateLabel} '{$invalid}'";
                    });

                    $roomCode = $this->extractValue($row, [$config['roomKey']]);
                    $roomId = null;

                    if ($roomCode !== '') {
                        $room = Room::query()->where('code', $roomCode)->first();
                        if ($room) {
                            $roomId = $room->id;
                        } else {
                            $summary['unknown_room_codes'][$roomCode] = $roomCode;
                            $rowReport['errors'][] = "Room code '{$roomCode}' not found for {$type}";
                        }
                    }

                    if ($normalizedDate === null && $roomCode === '') {
                        continue;
                    }

                    $payload = [
                        'exam_date' => $normalizedDate,
                        'week_seq' => $normalizedDate
                            ? CourseExamSchedule::computeWeekSeqFor($semester, $normalizedDate, $type)
                            : null,
                        'start_time' => null,
                        'end_time' => null,
                        'room_id' => $roomId,
                    ];

                    CourseExamSchedule::query()->updateOrCreate(
                        [
                            'course_offering_id' => $offering->id,
                            'exam_type' => $type,
                        ],
                        $payload
                    );

                    $summary['exams_upserted']++;
                }

                $summary['rows'][] = $rowReport;
            }
        });

        $summary['unknown_room_codes'] = array_values($summary['unknown_room_codes']);

        return redirect()
            ->back()
            ->with('summary', $summary);
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function readRows(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));

        if (in_array($extension, ['csv', 'txt'], true)) {
            return $this->readCsvRows($file);
        }

        if (in_array($extension, ['xls', 'xlsx'], true)) {
            if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
                throw ValidationException::withMessages([
                    'file' => 'Spreadsheet import requires phpoffice/phpspreadsheet. Please upload a CSV file instead.',
                ]);
            }

            return $this->readSpreadsheetRows($file);
        }

        throw ValidationException::withMessages([
            'file' => 'Unsupported file format. Please upload CSV or XLSX files.',
        ]);
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function readCsvRows(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages([
                'file' => 'Unable to open the uploaded file.',
            ]);
        }

        $header = null;
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if ($header === null) {
                $header = $this->normalizeHeaderRow($data);
                continue;
            }

            $rows[] = $this->normalizeRow($header, $data);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function readSpreadsheetRows(UploadedFile $file): array
    {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
        $sheet = $reader->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        $header = null;
        $normalized = [];

        foreach ($rows as $row) {
            if ($header === null) {
                $header = $this->normalizeHeaderRow($row);
                continue;
            }

            $normalized[] = $this->normalizeRow($header, $row);
        }

        return $normalized;
    }

    /**
     * @param  array<int|string, string|null>  $row
     * @return array<string, string>
     */
    protected function normalizeRow(array $header, array $row): array
    {
        $normalized = [];

        foreach ($header as $index => $key) {
            $value = $row[$index] ?? null;

            if (is_numeric($value) && $this->isLikelyDateColumn($key) && class_exists('\PhpOffice\PhpSpreadsheet\Shared\Date')) {
                $dateTime = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value);
                $value = $dateTime->format('Y-m-d');
            }

            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d');
            }

            $normalized[$key] = is_string($value) ? trim($value) : (string) ($value ?? '');
        }

        return $normalized;
    }

    /**
     * @param  array<int, string|null>  $header
     * @return array<int, string>
     */
    protected function normalizeHeaderRow(array $header): array
    {
        $keys = [];

        foreach ($header as $value) {
            $key = $this->normalizeHeader((string) $value);
            $keys[] = $key;
        }

        return $keys;
    }

    protected function normalizeHeader(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        $normalized = str_replace(['(opsional)', '(optional)'], '', $normalized ?? '');

        return trim($normalized ?? '');
    }

    protected function isLikelyDateColumn(string $key): bool
    {
        return str_contains($key, 'tanggal')
            || str_contains($key, 'pertemuan')
            || str_contains($key, 'praktikum');
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<int, string>  $keys
     */
    protected function extractValue(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            $normalizedKey = $this->normalizeHeader($key);

            if (array_key_exists($normalizedKey, $row) && $row[$normalizedKey] !== '') {
                return $row[$normalizedKey];
            }
        }

        return '';
    }

    protected function normalizeDate(?string $value, callable $onInvalid): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $trimmed);

            return $date->format('Y-m-d');
        } catch (\Throwable) {
            // continue
        }

        try {
            $date = Carbon::createFromFormat('d/m/Y', $trimmed);

            return $date->format('Y-m-d');
        } catch (\Throwable) {
            // continue
        }

        $onInvalid($trimmed);

        return null;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<int, string>  $meetingDates
     */
    protected function processPracticumColumns(array $row, int $rowNumber, array &$summary, array &$rowReport, array &$meetingDates): void
    {
        $practicumColumns = [
            1 => 'praktikum 1',
            2 => 'praktikum 2',
        ];

        foreach ($practicumColumns as $index => $columnKey) {
            $label = 'Praktikum ' . $index;
            $value = $this->extractValue($row, [$columnKey]);
            $date = $this->normalizeDate($value, function (string $invalid) use (&$summary, &$rowReport, $rowNumber, $label): void {
                $message = "Row {$rowNumber} {$label}: {$invalid}";
                $summary['invalid_dates'][] = $message;
                $rowReport['errors'][] = "Invalid {$label} date '{$invalid}'";
            });

            if ($date === null) {
                continue;
            }

            for ($slot = 1; $slot <= 14; $slot++) {
                if (!array_key_exists($slot, $meetingDates)) {
                    $meetingDates[$slot] = $date;
                    continue 2;
                }
            }

            $rowReport['errors'][] = "No available meeting slot for {$label}";
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}


