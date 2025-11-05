<?php

// file: routes/web.php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\Admin\BuildingController;
use App\Http\Controllers\Admin\CampusController;
use App\Http\Controllers\Admin\CourseImportController;
use App\Http\Controllers\Admin\CourseOfferingController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\BookingApprovalController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\SemesterController;
use App\Http\Controllers\SemesterDefaultController;
use App\Http\Controllers\SemesterDefaultImportController;
use App\Http\Controllers\Admin\SemesterController as AdminSemesterController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Route yang bisa diakses oleh semua user yang sudah login
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Booking
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/create', [BookingController::class, 'create'])->name('bookings.create');
    Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::get('/rooms/{room}/availability', [BookingController::class, 'availability'])->name('rooms.availability');
    Route::get('/bookings/{booking}/attachment', [BookingController::class, 'downloadAttachment'])->name('bookings.attachment');
    Route::get('/bookings/{booking}/letter', [BookingController::class, 'downloadLetter'])->name('bookings.letter');

    // ✅ History
    Route::get('/history', [HistoryController::class, 'index'])->name('history.index');
    Route::get('/history/export/excel', [HistoryController::class, 'exportExcel'])->name('history.export.excel');

    Route::get('/export-users', [ExportController::class, 'exportUsers']);
});

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('semester/edit', [AdminSemesterController::class, 'edit'])->name('semester.edit');
    Route::put('semester', [AdminSemesterController::class, 'update'])->name('semester.update');

    Route::get('semesters/{semester}/offerings', [CourseOfferingController::class, 'index'])->name('offerings.index');
    Route::post('offerings', [CourseOfferingController::class, 'store'])->name('offerings.store');
    Route::put('offerings/{offering}/exam', [CourseOfferingController::class, 'updateExam'])->name('offerings.exam.update');

    Route::get('courses/import', [CourseImportController::class, 'create'])->name('courses.import.create');
    Route::post('courses/import', [CourseImportController::class, 'store'])->name('courses.import.store');
});

// Route khusus untuk admin, diproteksi oleh middleware 'role:admin'
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/home', [AdminController::class, 'index'])->name('home');
    Route::resource('campus', CampusController::class)->except(['show']);
    Route::resource('buildings', BuildingController::class)->except(['show']);
    Route::resource('rooms', RoomController::class)->except(['show']);

    Route::post('semesters/{semester}/toggle-active', [SemesterController::class, 'toggleActive'])->name('semesters.toggle-active');
    Route::resource('semesters', SemesterController::class)->except(['show']);

    Route::prefix('semesters/{semester}')->scopeBindings()->group(function () {
        Route::resource('defaults', SemesterDefaultController::class)
            ->except(['show'])
            ->parameters(['defaults' => 'default'])
            ->names('semesters.defaults');

        Route::get('defaults/import', [SemesterDefaultImportController::class, 'form'])->name('semesters.defaults.import.form');
        Route::post('defaults/import/preview', [SemesterDefaultImportController::class, 'preview'])->name('semesters.defaults.import.preview');
        Route::post('defaults/import/commit', [SemesterDefaultImportController::class, 'commit'])->name('semesters.defaults.import.commit');
    });

    Route::get('bookings', [BookingApprovalController::class, 'index'])->name('bookings.index');
    Route::get('bookings/{booking}', action: [BookingApprovalController::class, 'show'])->name('bookings.show');
    Route::post('bookings/{booking}/status', [BookingApprovalController::class, 'updateStatus'])->name('bookings.update-status');
    Route::get('bookings/export/excel', [BookingApprovalController::class, 'exportExcel'])->name('bookings.export.excel');
    Route::get('bookings/export/pdf', [BookingApprovalController::class, 'exportPdf'])->name('bookings.export.pdf');
});

require __DIR__.'/auth.php';
