<?php

use App\Http\Controllers\PaymentController;
use App\Http\Controllers\WordUsageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Payment Routes
|--------------------------------------------------------------------------
|
| Routes for handling payments, word packages, and balance management.
|
*/

// Public routes
Route::get('/pricing', [PaymentController::class, 'pricing'])->name('pricing');

// Webhook (no auth, but validated via signature)
Route::post('/webhooks/paystack', [PaymentController::class, 'webhook'])
    ->name('webhooks.paystack')
    ->withoutMiddleware(['web', 'csrf']);

// Authenticated routes
Route::middleware(['auth'])->group(function () {
    // Payment flow
    Route::post('/payments/initialize', [PaymentController::class, 'initialize'])
        ->name('payments.initialize');

    Route::get('/payments/callback', [PaymentController::class, 'callback'])
        ->name('payments.callback');

    Route::post('/payments/verify', [PaymentController::class, 'verify'])
        ->name('payments.verify');

    // Balance and history
    Route::get('/api/balance', [PaymentController::class, 'balance'])
        ->name('api.balance');

    Route::get('/api/payments/history', [PaymentController::class, 'history'])
        ->name('api.payments.history');

    Route::get('/api/transactions', [PaymentController::class, 'transactions'])
        ->name('api.transactions');

    // Word usage + balance operations
    Route::prefix('/api/words')->name('api.words.')->group(function () {
        Route::post('/record-usage', [WordUsageController::class, 'recordUsage'])
            ->name('record-usage');
        Route::post('/pre-authorize', [WordUsageController::class, 'preAuthorize'])
            ->name('pre-authorize');
        Route::post('/refund', [WordUsageController::class, 'refund'])
            ->name('refund');
        Route::get('/estimates', [WordUsageController::class, 'estimates'])
            ->name('estimates');
    });
});
