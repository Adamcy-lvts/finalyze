<?php

use App\Http\Controllers\ChapterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ManualEditorController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectGuidanceController;
use App\Http\Controllers\TopicController;
use App\Http\Middleware\ProjectStateMiddleware;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// Test broadcast route (for testing Reverb)
Route::get('/test-broadcast', function () {
    broadcast(new class implements \Illuminate\Contracts\Broadcasting\ShouldBroadcastNow
    {
        public function broadcastOn()
        {
            return [new \Illuminate\Broadcasting\Channel('test-channel')];
        }

        public function broadcastAs()
        {
            return 'test-event';
        }

        public function broadcastWith()
        {
            return [
                'message' => 'Hello from Laravel Reverb!',
                'timestamp' => now()->toDateTimeString(),
            ];
        }
    });

    return response()->json(['status' => 'Broadcasted!']);
});

// Dashboard
// Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/payment.php';

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard - Main landing page after login
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Projects - Basic project management (no state checking needed)
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');

    // Wizard progress saving - AJAX endpoint to save progress as user fills form
    Route::post('/projects/wizard/save-progress', [ProjectController::class, 'saveWizardProgress'])->name('projects.save-wizard-progress');

    // Bulk project deletion BEFORE single delete to avoid route conflicts
    Route::delete('/projects/bulk-destroy', [ProjectController::class, 'bulkDestroy'])->name('projects.bulk-destroy');

    // Project deletion routes WITHOUT state middleware - allows deletion regardless of setup state
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])
        ->whereNumber('project')
        ->name('projects.destroy');

    // Project edit routes WITHOUT state middleware - allows editing regardless of setup state
    Route::get('/projects/{project:slug}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
    Route::patch('/projects/{project:slug}', [ProjectController::class, 'update'])->name('projects.update');

    // Chapter deletion route WITHOUT state middleware - allows deletion regardless of setup state
    Route::delete('/projects/{project}/chapters/{chapter}', [ChapterController::class, 'destroy'])->name('chapters.destroy');

    // Word export routes WITHOUT state middleware - allows export regardless of setup state
    // Route::get('/projects/{project}/export/word', [ExportController::class, 'exportWord'])->name('projects.export-word');
    // Route::get('/projects/{project}/chapters/{chapterNumber}/export/word', [ExportController::class, 'exportChapter'])->name('chapters.export-word');
    // Route::post('/projects/{project}/chapters/export/word', [ExportController::class, 'exportChapters'])->name('chapters.export-multiple');

    // Export Routes
    Route::prefix('export')->name('export.')->group(function () {
        // Export full project as Word
        Route::get('/project/{project:slug}/word', [ExportController::class, 'exportWord'])
            ->name('project.word');

        // Export full project as PDF
        Route::get('/project/{project:slug}/pdf', [ExportController::class, 'exportProjectPdf'])
            ->name('project.pdf');

        // Export single chapter as Word
        Route::get('/project/{project:slug}/chapter/{chapterNumber}/word', [ExportController::class, 'exportChapter'])
            ->name('chapter.word');

        // Export single chapter as PDF
        Route::get('/project/{project:slug}/chapter/{chapterNumber}/pdf', [ChapterController::class, 'exportChapterPdf'])
            ->name('chapter.pdf');

        // Export multiple selected chapters as Word
        Route::post('/project/{project:slug}/chapters/word', [ExportController::class, 'exportChapters'])
            ->name('chapters.word');
    });

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
        Route::patch('/projects/{project}/update-mode', [ProjectController::class, 'updateMode'])->name('projects.update-mode');

        // PDF export for supervisor review
        Route::get('/projects/{project}/topics/export-pdf', [TopicController::class, 'exportPdf'])->name('topics.export-pdf');

        // Chapter writing and AI generation routes
        Route::get('/projects/{project}/writing', [ProjectController::class, 'writing'])->name('projects.writing');
        Route::get('/projects/{project}/bulk-generate', [ProjectController::class, 'bulkGenerate'])->name('projects.bulk-generate');
        Route::get('/projects/{project}/chapters/{chapter}/write', [ChapterController::class, 'write'])->name('chapters.write');
        Route::get('/projects/{project}/chapters/{chapter}/edit', [ChapterController::class, 'edit'])->name('chapters.edit');
        Route::post('/projects/{project}/chapters/generate', [ChapterController::class, 'generate'])->name('chapters.generate');
        Route::get('/projects/{project}/chapters/{chapter}/stream', [ChapterController::class, 'stream'])->name('chapters.stream');
        Route::post('/projects/{project}/chapters/save', [ChapterController::class, 'save'])->name('chapters.save');
        Route::post('/projects/{project}/chapters/{chapter}/chat', [ChapterController::class, 'chat'])->name('chapters.chat');
        Route::get('/projects/{project}/chapters/{chapter}/chat/history', [ChapterController::class, 'getChatHistory'])->name('chapters.chat-history');
        Route::post('/projects/{project}/chapters/{chapter}/chat/stream', [ChapterController::class, 'streamChat'])->name('chapters.chat-stream');

        // Chat file upload routes
        Route::post('/projects/{project}/chapters/{chapter}/chat/upload', [ChapterController::class, 'uploadChatFile'])->name('chapters.chat-upload');
        Route::get('/projects/{project}/chapters/{chapter}/chat/files', [ChapterController::class, 'getChatFiles'])->name('chapters.chat-files');
        Route::delete('/projects/{project}/chapters/{chapter}/chat/files/{uploadId}', [ChapterController::class, 'deleteChatFile'])->name('chapters.chat-file-delete');

        // Chat history search
        Route::get('/projects/{project}/chapters/{chapter}/chat/search', [ChapterController::class, 'searchChatHistory'])->name('chapters.chat-search');

        // Chat history management
        Route::get('/projects/{project}/chapters/{chapter}/chat/sessions', [ChapterController::class, 'getChatHistorySessions'])->name('chapters.chat-sessions');
        Route::delete('/projects/{project}/chapters/{chapter}/chat/sessions/{sessionId}', [ChapterController::class, 'deleteChatSession'])->name('chapters.chat-session-delete');
        Route::delete('/projects/{project}/chapters/{chapter}/chat/messages/{messageId}', [ChapterController::class, 'deleteChatMessage'])->name('chapters.chat-message-delete');
        Route::delete('/projects/{project}/chapters/{chapter}/chat/clear', [ChapterController::class, 'clearChatHistory'])->name('chapters.chat-clear');

        // Manual Editor Routes (Manual Mode Only)
        Route::prefix('projects/{project:slug}/manual-editor')->name('projects.manual-editor.')->scopeBindings()->group(function () {
            Route::get('/{chapter}', [ManualEditorController::class, 'show'])->name('show');
            Route::post('/{chapter}/save', [ManualEditorController::class, 'save'])->name('save');
            Route::post('/{chapter}/mark-complete', [ManualEditorController::class, 'markComplete'])->name('mark-complete');
            Route::post('/{chapter}/analyze', [ManualEditorController::class, 'analyzeAndSuggest'])->name('analyze');
            Route::post('/{chapter}/progressive-guidance', [ManualEditorController::class, 'progressiveGuidance'])->name('progressive-guidance');
            Route::post('/{chapter}/suggestions/{suggestion}/save', [ManualEditorController::class, 'saveSuggestion'])->name('suggestion.save');
            Route::post('/{chapter}/suggestions/{suggestion}/clear', [ManualEditorController::class, 'clearSuggestion'])->name('suggestion.clear');
            Route::post('/{chapter}/suggestions/{suggestion}/apply', [ManualEditorController::class, 'applySuggestion'])->name('suggestion.apply');
            Route::post('/{chapter}/chat', [ManualEditorController::class, 'chat'])->name('chat');
            // Quick Actions
            Route::post('/{chapter}/improve-text', [ManualEditorController::class, 'improveText'])->name('improve-text');
            Route::post('/{chapter}/expand-text', [ManualEditorController::class, 'expandText'])->name('expand-text');
            Route::post('/{chapter}/suggest-citations', [ManualEditorController::class, 'suggestCitations'])->name('suggest-citations');
            Route::post('/{chapter}/rephrase-text', [ManualEditorController::class, 'rephraseText'])->name('rephrase-text');
        });

        // Project Guidance routes
        Route::get('/projects/{project}/guidance', [ProjectGuidanceController::class, 'index'])->name('projects.guidance');
        Route::get('/projects/{project}/guidance/chapter/{chapterNumber}', [ProjectGuidanceController::class, 'chapterGuidance'])->name('projects.guidance-chapter');
        Route::get('/projects/{project}/guidance/writing-guidelines', [ProjectGuidanceController::class, 'writingGuidelines'])->name('projects.writing-guidelines');
        Route::post('/projects/{project}/guidance/proceed-to-writing', [ProjectGuidanceController::class, 'proceedToWriting'])->name('projects.proceed-to-writing');
    });

    // Simple test route to debug API routing
    Route::get('/api/test', function () {
        return response()->json(['message' => 'API routing works', 'time' => now()]);
    })->name('api.test');
}); // End of ProjectStateMiddleware group
