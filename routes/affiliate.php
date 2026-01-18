<?php

use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\AffiliateRequestController;
use App\Http\Controllers\Auth\AffiliateAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Affiliate Routes
|--------------------------------------------------------------------------
|
| Routes for affiliate registration, dashboard, and access requests.
|
*/

Route::middleware('guest')->group(function () {
    Route::get('/affiliate/register', [AffiliateAuthController::class, 'showRegistration'])->name('affiliate.register');
    Route::post('/affiliate/register', [AffiliateAuthController::class, 'register'])->name('affiliate.register.store');
    Route::get('/affiliate/invite/{code}', [AffiliateAuthController::class, 'validateInvite'])->name('affiliate.invite');
});

Route::middleware(['auth', 'verified', 'affiliate'])->prefix('affiliate')->name('affiliate.')->group(function () {
    Route::get('/', [AffiliateController::class, 'index'])->name('dashboard');
    Route::get('/earnings', [AffiliateController::class, 'earningsPage'])->name('earnings');
    Route::get('/earnings/data', [AffiliateController::class, 'earnings'])->name('earnings.data');
    Route::post('/verify-bank', [AffiliateController::class, 'verifyBankAccount'])->name('verify-bank');
    Route::post('/setup-bank', [AffiliateController::class, 'setupBankAccount'])->name('setup-bank');
    Route::get('/referrals', [AffiliateController::class, 'referralsPage'])->name('referrals');
    Route::get('/referrals/data', [AffiliateController::class, 'referrals'])->name('referrals.data');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/affiliate/request', [AffiliateRequestController::class, 'store'])->name('affiliate.request');
    Route::get('/affiliate/request/status', [AffiliateRequestController::class, 'status'])->name('affiliate.request.status');
    Route::post('/affiliate/promo-dismiss', [AffiliateRequestController::class, 'dismissPromo'])->name('affiliate.promo.dismiss');
});
