<?php

namespace App\Http\Controllers;

use App\Models\MasterSemester;
use App\Services\Semesters\CsvImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use JsonException;

class SemesterDefaultImportController extends Controller
{
    public function __construct(protected CsvImportService $importService)
    {
    }

    public function form(MasterSemester $semester): View
    {
        return view('semesters.defaults.import', [
            'semester' => $semester,
        ]);
    }

    public function preview(Request $request, MasterSemester $semester): View
    {
        $data = $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt'],
        ], [
            'csv_file.required' => 'File CSV wajib diunggah.',
            'csv_file.mimes' => 'File harus berformat CSV.',
        ]);

        $content = $request->file('csv_file')->get();
        $rows = $this->importService->parse($content);
        $preview = $this->importService->preview($semester->id, $rows);

        $normalizedRows = [];
        foreach ($preview->rows as $row) {
            $normalizedRows[] = $row->normalizedData;
        }

        try {
            $payload = base64_encode(json_encode($normalizedRows, JSON_THROW_ON_ERROR));
        } catch (JsonException $exception) {
            throw ValidationException::withMessages([
                'csv_file' => 'Gagal memproses isi CSV: '.$exception->getMessage(),
            ]);
        }

        return view('semesters.defaults.import_preview', [
            'semester' => $semester,
            'preview' => $preview,
            'payload' => $payload,
        ]);
    }

    public function commit(Request $request, MasterSemester $semester): RedirectResponse
    {
        $validated = $request->validate([
            'payload' => ['required', 'string'],
            'has_errors' => ['required', 'in:0,1'],
        ]);

        if ($validated['has_errors'] === '1') {
            throw ValidationException::withMessages([
                'payload' => 'Tidak dapat melakukan impor karena masih ada baris yang bermasalah.',
            ]);
        }

        $decoded = base64_decode($validated['payload'], true);
        if ($decoded === false) {
            throw ValidationException::withMessages([
                'payload' => 'Data impor tidak valid.',
            ]);
        }

        try {
            $normalizedRows = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw ValidationException::withMessages([
                'payload' => 'Data impor tidak valid.',
            ]);
        }

        if (! is_array($normalizedRows)) {
            throw ValidationException::withMessages([
                'payload' => 'Data impor tidak valid.',
            ]);
        }

        $this->importService->commit($semester->id, $normalizedRows);

        return redirect()->route('admin.semesters.defaults.index', $semester)->with('status', 'Impor default jadwal berhasil disimpan.');
    }
}