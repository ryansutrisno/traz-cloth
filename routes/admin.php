<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\Auth\AdminLoginController;
use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\CommunicationController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ReadyStockController;
use App\Http\Controllers\Admin\ReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware('web')->group(function (): void {
    Route::get('/login', [AdminLoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [AdminLoginController::class, 'login'])->name('login.store');
    Route::post('/logout', [AdminLoginController::class, 'logout'])->name('logout');

    Route::middleware(['admin', 'auth:admin'])->group(function (): void {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::resource('campaigns', CampaignController::class)->except('show');
        Route::resource('coupons', CouponController::class)->except('show');

        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/transition', [OrderController::class, 'transition'])->name('orders.transition');
        Route::post('/orders/{order}/courier-rates', [OrderController::class, 'courierRates'])->name('orders.courier-rates');
        Route::post('/orders/{order}/book-courier', [OrderController::class, 'bookCourier'])->name('orders.book-courier');

        Route::get('/komunikasi', [CommunicationController::class, 'index'])->name('komunikasi.index');
        Route::post('/komunikasi/send', [CommunicationController::class, 'send'])
            ->middleware('throttle:10,1')
            ->name('komunikasi.send');
        Route::get('/laporan', [ReportController::class, 'index'])->name('laporan.index');
        Route::get('/ready-stock', [ReadyStockController::class, 'index'])->name('ready-stock.index');
        Route::match(['put', 'patch'], '/ready-stock/{campaignSize}', [ReadyStockController::class, 'update'])->name('ready-stock.update');
        Route::delete('/ready-stock/{campaignSize}', [ReadyStockController::class, 'destroy'])->name('ready-stock.destroy');
    });
});
