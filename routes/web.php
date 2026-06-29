<?php

use App\Http\Controllers\Admin\BookingApprovalController;
use App\Http\Controllers\Admin\BuildingController;
use App\Http\Controllers\Admin\CampusController;
use App\Http\Controllers\Admin\ItemBorrowingApprovalController;
use App\Http\Controllers\Admin\ItemBorrowingReportController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\ItemBorrowingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Booking Routes
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/create', [BookingController::class, 'create'])->name('bookings.create');
    Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::get('/rooms/{room}/availability', [BookingController::class, 'availability'])->name('rooms.availability');
    Route::get('/bookings/{booking}/edit', [BookingController::class, 'edit'])->name('bookings.edit');
    Route::put('/bookings/{booking}', [BookingController::class, 'update'])->name('bookings.update');
    Route::get('/bookings/{booking}/resubmit', [BookingController::class, 'resubmit'])->name('bookings.resubmit');
    Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
    Route::get('/bookings/{booking}/attachment', [BookingController::class, 'downloadAttachment'])->name('bookings.attachment');
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
    Route::get('/bookings/{booking}/letter', [BookingController::class, 'downloadLetter'])->name('bookings.letter');

    // Item Borrowing Routes (Updated with Multi & Edit)
    Route::get('/item-borrowings', [ItemBorrowingController::class, 'index'])->name('item-borrowings.index');
    Route::get('/item-borrowings/create', [ItemBorrowingController::class, 'create'])->name('item-borrowings.create');
    Route::post('/item-borrowings', [ItemBorrowingController::class, 'store'])->name('item-borrowings.store');
    Route::get('/items/{item}/availability', [ItemBorrowingController::class, 'availability'])->name('items.availability');
    Route::get('/item-borrowings/{itemBorrowing}/resubmit', [ItemBorrowingController::class, 'resubmit'])->name('item-borrowings.resubmit');
    Route::get('/item-borrowings/{itemBorrowing}', [ItemBorrowingController::class, 'show'])->name('item-borrowings.show');
    Route::get('/item-borrowings/{itemBorrowing}/attachment', [ItemBorrowingController::class, 'downloadAttachment'])->name('item-borrowings.attachment');
    Route::get('/item-borrowings/{itemBorrowing}/signed-letter', [ItemBorrowingController::class, 'downloadSignedLetter'])->name('item-borrowings.signed-letter');
    Route::post('/item-borrowings/{itemBorrowing}/cancel', [ItemBorrowingController::class, 'cancel'])->name('item-borrowings.cancel');
    Route::get('/item-borrowings/{itemBorrowing}/edit', [ItemBorrowingController::class, 'edit'])->name('item-borrowings.edit');
    Route::put('/item-borrowings/{itemBorrowing}', [ItemBorrowingController::class, 'update'])->name('item-borrowings.update');

    // History (super admin)
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/history', [HistoryController::class, 'index'])->name('history.index');
        Route::get('/history/export/excel', [HistoryController::class, 'exportExcel'])->name('history.export.excel');
    });

    Route::get('/export-users', [ExportController::class, 'exportUsers']);
});

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::middleware('role:super_admin,admin_bap,admin_sarpras')->group(function () {
        Route::get('/home', [AdminController::class, 'index'])->name('home');
    });

    Route::middleware('role:super_admin')->group(function () {
        Route::resource('users', AdminUserController::class)->only(['index', 'create', 'store', 'edit', 'update']);
        Route::patch('users/{user}/deactivate', [AdminUserController::class, 'deactivate'])->name('users.deactivate');
        Route::patch('users/{user}/activate', [AdminUserController::class, 'activate'])->name('users.activate');
    });

    Route::middleware('role:super_admin,admin_bap')->group(function () {
        Route::resource('campus', CampusController::class)->except(['show']);
        Route::post('campus/bulk-destroy', [CampusController::class, 'bulkDestroy'])->name('campus.bulk-destroy');
        Route::resource('buildings', BuildingController::class)->except(['show']);
        Route::post('buildings/bulk-destroy', [BuildingController::class, 'bulkDestroy'])->name('buildings.bulk-destroy');
        Route::resource('rooms', RoomController::class)->except(['show']);
        Route::post('rooms/bulk-destroy', [RoomController::class, 'bulkDestroy'])->name('rooms.bulk-destroy');

        Route::get('bookings', [BookingApprovalController::class, 'index'])->name('bookings.index');
        Route::get('bookings/{booking}', [BookingApprovalController::class, 'show'])->name('bookings.show');
        Route::post('bookings/{booking}/status', [BookingApprovalController::class, 'updateStatus'])->name('bookings.update-status');
        Route::get('bookings/export/excel', [BookingApprovalController::class, 'exportExcel'])->name('bookings.export.excel');
        Route::get('bookings/export/pdf', [BookingApprovalController::class, 'exportPdf'])->name('bookings.export.pdf');

        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
        Route::get('reports/export/pdf', [ReportController::class, 'exportPdf'])->name('reports.export.pdf');
    });

    Route::middleware('role:super_admin,admin_sarpras')->group(function () {
        Route::resource('items', \App\Http\Controllers\Admin\ItemController::class)->except(['show']);
        Route::post('items/bulk-destroy', [\App\Http\Controllers\Admin\ItemController::class, 'bulkDestroy'])->name('items.bulk-destroy');

        Route::get('item-borrowings', [ItemBorrowingApprovalController::class, 'index'])->name('item-borrowings.index');
        Route::get('item-borrowings/{itemBorrowing}', [ItemBorrowingApprovalController::class, 'show'])->name('item-borrowings.show');
        Route::post('item-borrowings/{itemBorrowing}/status', [ItemBorrowingApprovalController::class, 'updateStatus'])->name('item-borrowings.update-status');

        Route::get('item-borrowing-reports', [ItemBorrowingReportController::class, 'index'])->name('item-borrowing-reports.index');
        Route::get('item-borrowing-reports/export', [ItemBorrowingReportController::class, 'export'])->name('item-borrowing-reports.export');
        Route::get('item-borrowing-reports/export/pdf', [ItemBorrowingReportController::class, 'exportPdf'])->name('item-borrowing-reports.export.pdf');
    });
});

require __DIR__.'/auth.php';
