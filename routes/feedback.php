<?php

use App\Http\Controllers\FeedbackRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('/api/feedback')->name('api.feedback.')->group(function () {
    Route::get('/eligibility', [FeedbackRequestController::class, 'eligibility'])
        ->name('eligibility');
    Route::post('/requests', [FeedbackRequestController::class, 'store'])
        ->name('requests.store');
    Route::post('/requests/{feedbackRequest}/submit', [FeedbackRequestController::class, 'submit'])
        ->name('requests.submit');
    Route::post('/requests/{feedbackRequest}/dismiss', [FeedbackRequestController::class, 'dismiss'])
        ->name('requests.dismiss');
});
