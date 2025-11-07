<?php

use App\Http\Controllers\Api\AuthRegisterController;
use App\Http\Controllers\Api\PasswordResetController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('api.auth.')->group(function () {
    Route::middleware('throttle:otp-issue')->group(function () {
        Route::post('register/start', [AuthRegisterController::class, 'start'])
            ->name('register.start');
        Route::post('register/resend', [AuthRegisterController::class, 'resend'])
            ->name('register.resend');
        Route::post('password/forgot', [PasswordResetController::class, 'forgot'])
            ->name('password.forgot');
    });

    Route::middleware('throttle:otp-verify')->group(function () {
        Route::post('register/verify-code', [AuthRegisterController::class, 'verifyCode'])
            ->name('register.verify-code');
        Route::post('register/verify-link', [AuthRegisterController::class, 'verifyLink'])
            ->name('register.verify-link');
        Route::post('password/reset-by-code', [PasswordResetController::class, 'resetByCode'])
            ->name('password.reset-code');
        Route::post('password/reset-by-link', [PasswordResetController::class, 'resetByLink'])
            ->name('password.reset-link');
    });
});
