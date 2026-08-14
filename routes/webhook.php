<?php

use App\Http\Controllers\Webhook\BiteshipWebhookController;
use App\Http\Controllers\Webhook\MidtransWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhook/midtrans', [MidtransWebhookController::class, 'midtrans'])
    ->name('webhook.midtrans');

Route::post('/webhook/biteship', [BiteshipWebhookController::class, 'biteship'])
    ->name('webhook.biteship');
