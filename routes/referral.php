<?php

use App\Http\Controllers\ReferralController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Referral Routes
|--------------------------------------------------------------------------
|
| Routes for the referral program - user dashboard, bank setup, earnings.
|
*/

Route::middleware(['auth', 'verified', 'affiliate'])->prefix('referrals')->name('referrals.')->group(function () {
    // Referral dashboard
    Route::get('/', [ReferralController::class, 'index'])->name('index');

    // Bank account verification and setup
    Route::post('/verify-bank', [ReferralController::class, 'verifyBankAccount'])->name('verify-bank');
    Route::post('/setup-bank', [ReferralController::class, 'setupBankAccount'])->name('setup-bank');

    // Earnings history
    Route::get('/earnings', [ReferralController::class, 'earnings'])->name('earnings');
});
