<?php

// file: app/Http/Controllers/Admin/CourseImportController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourseExamSchedule;
use App\Models\CourseOffering;
use App\Models\Semester;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

                return [
                    'id' => $semester->id,
                    'label' => $ranges['semester'] ?? ('Semester #' . $semester->id),
                ];
            })
            ->values();

        return Inertia::render('Admin/Courses/Import', [
            'semesters' => $semesters,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'semester_id' => ['required', 'exists:semesters,id'],
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $semester = Semester::query()->findOrFail((int) $data['semester_id']);
        $handle = fopen($data['file']->getRealPath(), 'rb');

        if (!$handle) {
            throw ValidationException::withMessages([
                'file' => 'Unable to read the uploaded file.',
            ]);
        }

        $expectedHeader = [
            'Kode Mata Kuliah',
            'Nama Mata Kuliah',
            'Tanggal UTS',
            'Jam UTS',
            'Tanggal UAS',
            'Jam UAS',
        ];

        $summary = [
            'rows_processed' => 0,
            'offerings_created' => 0,
            'offerings_updated' => 0,
            'exams_upserted' => 0,
            'invalid_rows' => [],
        ];

        try {
            $header = fgetcsv($handle);

            if ($header === false || $header !== $expectedHeader) {
                throw ValidationException::withMessages([
                    'file' => 'Unexpected CSV header. Expected: ' . implode(', ', $expectedHeader),
                ]);
            }

            DB::transaction(function () use (&$summary, $handle, $semester): void {
                $rowNumber = 1; // header row

                while (($row = fgetcsv($handle)) !== false) {
                    $rowNumber++;
                    $summary['rows_processed']++;

                    $courseCode = trim((string) ($row[0] ?? ''));
                    $courseName = trim((string) ($row[1] ?? ''));
                    $utsDateRaw = trim((string) ($row[2] ?? ''));
                    $utsTimeRaw = trim((string) ($row[3] ?? ''));
                    $uasDateRaw = trim((string) ($row[4] ?? ''));
                    $uasTimeRaw = trim((string) ($row[5] ?? ''));

                    $errors = [];

                    if ($courseCode === '') {
                        $errors[] = 'Missing course code.';
                    }

                    if ($courseName === '') {
                        $courseName = $courseCode;
                    }

                    $utsDate = $this->normalizeDate($utsDateRaw, $errors, 'Tanggal UTS');
                    [$utsStart, $utsEnd] = $this->normalizeTimeRange($utsTimeRaw, $errors, 'Jam UTS');

                    $uasDate = $this->normalizeDate($uasDateRaw, $errors, 'Tanggal UAS');
                    [$uasStart, $uasEnd] = $this->normalizeTimeRange($uasTimeRaw, $errors, 'Jam UAS');

                    if ($courseCode === '') {
                        $summary['invalid_rows'][] = [
                            'row' => $rowNumber,
                            'course_code' => null,
                            'errors' => $errors,
                        ];
                        continue;
                    }

                    if ($errors !== []) {
                        $summary['invalid_rows'][] = [
                            'row' => $rowNumber,
                            'course_code' => $courseCode,
                            'errors' => $errors,
                        ];
                    }

                    $offering = CourseOffering::query()->updateOrCreate(
                        [
                            'semester_id' => $semester->id,
                            'course_code' => $courseCode,
                        ],
                        [
                            'course_name' => $courseName,
                        ]
                    );

                    if ($offering->wasRecentlyCreated) {
                        $summary['offerings_created']++;
                    } else {
                        $summary['offerings_updated']++;
                    }

                    $summary['exams_upserted'] += $this->upsertExam(
                        $offering,
                        'UTS',
                        $utsDate,
                        $utsStart,
                        $utsEnd
                    );

                    $summary['exams_upserted'] += $this->upsertExam(
                        $offering,
                        'UAS',
                        $uasDate,
                        $uasStart,
                        $uasEnd
                    );
                }
            });
        } finally {
            fclose($handle);
        }

        return redirect()
            ->back()
            ->with('summary', $summary);
    }

    private function normalizeDate(string $value, array &$errors, string $label): ?string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y'];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $trimmed)->format('Y-m-d');
            } catch (\Throwable) {
                // keep trying
            }
        }

        try {
            return Carbon::parse($trimmed)->format('Y-m-d');
        } catch (\Throwable) {
            $errors[] = "{$label} '{$trimmed}' is invalid.";

            return null;
        }
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function normalizeTimeRange(string $value, array &$errors, string $label): array
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return [null, null];
        }

        $parts = preg_split('/\s*(?:-|to|s\/?d\.?)\s*/i', $trimmed);

        $start = $this->normalizeTime($parts[0] ?? '', $errors, $label);
        $end = isset($parts[1]) ? $this->normalizeTime($parts[1], $errors, $label) : null;

        return [$start, $end];
    }

    private function normalizeTime(string $value, array &$errors, string $label): ?string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        $formats = ['H:i', 'H.i', 'G:i', 'H:i:s', 'g:i A', 'g:iA'];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $trimmed)->format('H:i');
            } catch (\Throwable) {
                // continue
            }
        }

        try {
            return Carbon::parse($trimmed)->format('H:i');
        } catch (\Throwable) {
            $errors[] = "{$label} '{$trimmed}' is invalid.";

            return null;
        }
    }

    private function upsertExam(
        CourseOffering $offering,
        string $examType,
        ?string $date,
        ?string $startTime,
        ?string $endTime
    ): int {
        if ($date === null && $startTime === null && $endTime === null) {
            return 0;
        }

        CourseExamSchedule::query()->updateOrCreate(
            [
                'course_offering_id' => $offering->id,
                'exam_type' => $examType,
            ],
            [
                'exam_date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
            ]
        );

        return 1;
    }
}
