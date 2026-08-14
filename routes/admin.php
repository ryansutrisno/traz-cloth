<?php

use App\Http\Controllers\Admin\Auth\AdminLoginController;
use App\Http\Controllers\Admin\CampaignController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware('web')->group(function (): void {
    Route::get('/login', [AdminLoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [AdminLoginController::class, 'login'])->name('login.store');
    Route::post('/logout', [AdminLoginController::class, 'logout'])->name('logout');

    Route::middleware('admin')->group(function (): void {
        Route::get('/', fn () => redirect()->route('admin.campaigns.index'))->name('dashboard');
        Route::resource('campaigns', CampaignController::class)->except('show');
    });
});
