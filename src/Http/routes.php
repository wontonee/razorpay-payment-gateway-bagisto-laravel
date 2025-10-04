<?php

use Illuminate\Support\Facades\Route;
use Wontonee\Razorpay\Http\Controllers\RazorpayController;

Route::group(['middleware' => ['web', 'theme', 'locale', 'currency']], function () {
    Route::get('razorpay-redirect', [RazorpayController::class, 'redirect'])->name('razorpay.process');
});

// Callback route for Razorpay payment response (POST only for security)
Route::post('razorpaycheck', [RazorpayController::class, 'verify'])
    ->name('razorpay.callback')
    ->middleware(['web', 'theme', 'locale', 'currency'])
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);

// Webhook route for real-time payment processing
Route::post('razorpay/webhook', [RazorpayController::class, 'webhook'])
    ->name('razorpay.webhook')
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);

// Admin routes for refund functionality
Route::group(['middleware' => ['web', 'admin'], 'prefix' => 'admin'], function () {
    Route::post('razorpay/refund', [RazorpayController::class, 'refund'])->name('admin.razorpay.refund');
    Route::get('razorpay/refund-status/{orderId}', [RazorpayController::class, 'getRefundStatus'])->name('admin.razorpay.refund-status');
});