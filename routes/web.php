<?php

use App\Http\Controllers\ChapterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TopicController;
use App\Http\Middleware\ProjectStateMiddleware;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// Dashboard
// Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard - Main landing page after login
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Projects - Basic project management (no state checking needed)
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');

    // Wizard progress saving - AJAX endpoint to save progress as user fills form
    Route::post('/projects/wizard/save-progress', [ProjectController::class, 'saveWizardProgress'])->name('projects.save-wizard-progress');

    // Project deletion route WITHOUT state middleware - allows deletion regardless of setup state
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

    // Project-specific routes WITH state persistence middleware
    // This middleware ensures users are always on the correct page for their setup progress
    Route::middleware([ProjectStateMiddleware::class])->group(function () {
        Route::get('/projects/{project}/topic-selection', [ProjectController::class, 'topicSelection'])->name('projects.topic-selection');
        Route::get('/projects/{project}/topic-approval', [ProjectController::class, 'topicApproval'])->name('projects.topic-approval');
        Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
        Route::post('/projects/{project}/set-active', [ProjectController::class, 'setActive'])->name('projects.set-active');

        // Topic management routes - also need state checking
        Route::post('/projects/{project}/topics/generate', [TopicController::class, 'generate'])->name('topics.generate');
        Route::get('/projects/{project}/topics/stream', [TopicController::class, 'stream'])->name('topics.stream');
        Route::post('/projects/{project}/topics/select', [TopicController::class, 'select'])->name('topics.select');
        Route::post('/projects/{project}/topics/approve', [TopicController::class, 'approve'])->name('topics.approve');
        Route::post('/projects/{project}/go-back-to-wizard', [ProjectController::class, 'goBackToWizard'])->name('projects.go-back-to-wizard');
        Route::post('/projects/{project}/go-back-to-topic-selection', [ProjectController::class, 'goBackToTopicSelection'])->name('projects.go-back-to-topic-selection');
        Route::post('/projects/{project}/go-back-to-topic-approval', [ProjectController::class, 'goBackToTopicApproval'])->name('projects.go-back-to-topic-approval');

        // PDF export for supervisor review
        Route::get('/projects/{project}/topics/export-pdf', [TopicController::class, 'exportPdf'])->name('topics.export-pdf');

        // Chapter writing and AI generation routes
        Route::get('/projects/{project}/writing', [ProjectController::class, 'writing'])->name('projects.writing');
        Route::get('/projects/{project}/chapters/{chapter}/edit', [ChapterController::class, 'edit'])->name('chapters.edit');
        Route::post('/projects/{project}/chapters/generate', [ChapterController::class, 'generate'])->name('chapters.generate');
        Route::get('/projects/{project}/chapters/{chapter}/stream', [ChapterController::class, 'stream'])->name('chapters.stream');
        Route::post('/projects/{project}/chapters/save', [ChapterController::class, 'save'])->name('chapters.save');
        Route::post('/projects/{project}/chapters/{chapter}/chat', [ChapterController::class, 'chat'])->name('chapters.chat');
        Route::get('/projects/{project}/chapters/{chapter}/chat/history', [ChapterController::class, 'getChatHistory'])->name('chapters.chat-history');
        Route::post('/projects/{project}/chapters/{chapter}/chat/stream', [ChapterController::class, 'streamChat'])->name('chapters.chat-stream');
    });
});
