<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\Admin\BuildingController;
use App\Http\Controllers\Admin\CampusController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;

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
    Route::get('/bookings/create', [BookingController::class, 'create'])->name('bookings.create');
    Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');

    // ✅ History
    Route::get('/history', [HistoryController::class, 'index'])->name('history.index');
    Route::get('/history/export/excel', [HistoryController::class, 'exportExcel'])->name('history.export.excel');
    Route::get('/history/export/pdf', [HistoryController::class, 'exportPdf'])->name('history.export.pdf');

    Route::get('/export-users', [ExportController::class, 'exportUsers']);
});

// Route khusus untuk admin, diproteksi oleh middleware 'role:admin'
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/home', [AdminController::class, 'index'])->name('home');
    Route::resource('campus', CampusController::class)->except(['show']);
    Route::resource('buildings', BuildingController::class)->except(['show']);
    Route::resource('rooms', RoomController::class)->except(['show']);
});

require __DIR__.'/auth.php';