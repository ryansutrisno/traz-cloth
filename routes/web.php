<?php

use App\Http\Controllers\Storefront\Auth\RegisteredUserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'showRegister'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:otp')->name('register.store');
    Route::get('/verify-otp', [RegisteredUserController::class, 'showVerifyOtp'])->name('verify-otp');
    Route::post('/verify-otp', [RegisteredUserController::class, 'check'])->middleware('throttle:otp')->name('verify-otp.check');
    Route::post('/verify-otp/resend', [RegisteredUserController::class, 'resend'])->middleware('throttle:otp')->name('verify-otp.resend');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
