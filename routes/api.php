<?php

use App\Http\Controllers\Api\PasswordResetController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('api.auth.')->group(function () {
    Route::middleware('throttle:otp-issue')->group(function () {
        Route::post('password/forgot', [PasswordResetController::class, 'forgot'])
            ->name('password.forgot');
    });

    Route::middleware('throttle:otp-verify')->group(function () {
        Route::post('password/reset-by-code', [PasswordResetController::class, 'resetByCode'])
            ->name('password.reset-code');
        Route::post('password/reset-by-link', [PasswordResetController::class, 'resetByLink'])
            ->name('password.reset-link');
    });
});
