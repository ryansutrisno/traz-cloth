<?php

use App\Http\Controllers\Storefront\AccountController;
use App\Http\Controllers\Storefront\AddressController;
use App\Http\Controllers\Storefront\Auth\RegisteredUserController;
use App\Http\Controllers\Storefront\CampaignController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\DashboardController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\NewsletterController;
use App\Http\Controllers\Storefront\OrderController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
Route::get('/campaigns/{slug}', [CampaignController::class, 'show'])->name('campaigns.show');

Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])
    ->middleware('throttle:5,1')
    ->name('newsletter.subscribe');

Route::inertia('/terms', 'terms')->name('terms');
Route::inertia('/privacy-policy', 'privacy')->name('privacy-policy');
Route::inertia('/contact', 'contact')->name('contact');
Route::inertia('/faq', 'faq')->name('faq');
Route::inertia('/tentang', 'about')->name('about');
Route::inertia('/kebijakan-pengiriman', 'shipping-policy')->name('shipping-policy');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'showRegister'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:otp')->name('register.store');
    Route::get('/verify-otp', [RegisteredUserController::class, 'showVerifyOtp'])->name('verify-otp');
    Route::post('/verify-otp', [RegisteredUserController::class, 'check'])->middleware('throttle:otp')->name('verify-otp.check');
    Route::post('/verify-otp/resend', [RegisteredUserController::class, 'resend'])->middleware('throttle:otp')->name('verify-otp.resend');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

require __DIR__.'/settings.php';

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/add', [CartController::class, 'add'])->middleware('throttle:10,1')->name('cart.add');
Route::patch('/cart/{cartItem}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/{cartItem}', [CartController::class, 'destroy'])->name('cart.destroy');

Route::middleware('auth')->group(function (): void {
    Route::get('/akun', [AccountController::class, 'index'])->name('account.index');
    Route::post('/akun/phone', [AccountController::class, 'updatePhone'])
        ->middleware('throttle:otp')
        ->name('account.phone.update');
    Route::post('/akun/phone/verify', [AccountController::class, 'verifyPhone'])
        ->middleware('throttle:otp')
        ->name('account.phone.verify');

    Route::get('/alamat', [AddressController::class, 'index'])->name('addresses.index');
    Route::post('/alamat', [AddressController::class, 'store'])->name('addresses.store');
    Route::patch('/alamat/{address}', [AddressController::class, 'update'])->name('addresses.update');
    Route::delete('/alamat/{address}', [AddressController::class, 'destroy'])->name('addresses.destroy');

    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout/rates', [CheckoutController::class, 'rates'])->name('checkout.rates');
    Route::post('/checkout/coupon', [CheckoutController::class, 'applyCoupon'])->name('checkout.coupon.apply');
    Route::delete('/checkout/coupon', [CheckoutController::class, 'removeCoupon'])->name('checkout.coupon.remove');
    Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
    Route::get('/checkout/dev/{order}', [CheckoutController::class, 'dev'])->name('checkout.dev');
    Route::post('/checkout/dev/{order}/pay', [CheckoutController::class, 'devPay'])->name('checkout.dev.pay');
    Route::get('/checkout/success/{order}', [CheckoutController::class, 'success'])->name('checkout.success');
    Route::get('/checkout/canceled', [CheckoutController::class, 'canceled'])->name('checkout.canceled');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/pay-remaining', [OrderController::class, 'payRemaining'])
        ->middleware('throttle:10,1')
        ->name('orders.pay-remaining');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
});

if (file_exists(__DIR__.'/admin.php')) {
    require __DIR__.'/admin.php';
}

if (file_exists(__DIR__.'/webhook.php')) {
    require __DIR__.'/webhook.php';
}
