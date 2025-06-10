<?php

use Illuminate\Support\Facades\Route;
use Wontonee\Razorpay\Http\Controllers\RazorpayController;

Route::group(['middleware' => ['web', 'theme', 'locale', 'currency']], function () {
    Route::get('razorpay-redirect', [RazorpayController::class, 'redirect'])->name('razorpay.process');
});


// Separate route for callback without CSRF protection
Route::post('razorpaycheck', [RazorpayController::class, 'verify'])
    ->name('razorpay.callback')
    ->middleware(['web', 'locale', 'currency'])
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
