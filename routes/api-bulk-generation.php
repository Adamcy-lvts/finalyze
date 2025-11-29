<?php

use App\Http\Controllers\Api\BulkGenerationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Bulk Generation API Routes
|--------------------------------------------------------------------------
|
| These routes handle the bulk project generation feature.
| All routes require authentication.
|
*/

Route::middleware(['auth:sanctum'])->prefix('projects/{project:slug}/bulk-generate')->group(function () {
    // Start or resume generation
    Route::post('/start', [BulkGenerationController::class, 'start'])
        ->name('api.projects.bulk-generate.start');

    // Get current status
    Route::get('/status', [BulkGenerationController::class, 'status'])
        ->name('api.projects.bulk-generate.status');

    // Cancel generation
    Route::post('/cancel', [BulkGenerationController::class, 'cancel'])
        ->name('api.projects.bulk-generate.cancel');

    // Retry failed generation
    Route::post('/retry', [BulkGenerationController::class, 'retry'])
        ->name('api.projects.bulk-generate.retry');
});
