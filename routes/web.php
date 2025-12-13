<?php

use App\Http\Controllers\Admin\AdminAIController;
use App\Http\Controllers\Admin\AdminAnalyticsController;
use App\Http\Controllers\Admin\AdminAuditController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\AdminPackageController;
use App\Http\Controllers\Admin\AdminPaymentController;
use App\Http\Controllers\Admin\AdminProjectController;
use App\Http\Controllers\Admin\AdminSystemController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\ChapterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ManualEditorController;
use App\Http\Controllers\ProjectAnalysisController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectGuidanceController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\TopicLabController;
use App\Http\Middleware\ProjectStateMiddleware;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (\App\Services\PaystackService $paystackService) {
    // Fetch packages safely
    try {
        $packages = \App\Models\WordPackage::getForPricingPage();
    } catch (\Exception $e) {
        $packages = ['projects' => [], 'topups' => []];
        \Illuminate\Support\Facades\Log::error('Pricing page package fetch failed: '.$e->getMessage());
    }

    // Check payment config safely
    try {
        $paystackConfigured = $paystackService->isConfigured();
        $paystackPublicKey = $paystackService->getPublicKey();
    } catch (\Exception $e) {
        $paystackConfigured = false;
        $paystackPublicKey = null;
        \Illuminate\Support\Facades\Log::error('Pricing page payment config check failed: '.$e->getMessage());
    }

    $user = auth()->user();
    $activePackageId = null;

    if ($user) {
        $latestPayment = $user->successfulPayments()
            ->whereHas('wordPackage', function ($query) {
                $query->where('type', 'project');
            })
            ->latest('paid_at')
            ->first();

        if ($latestPayment) {
            $activePackageId = $latestPayment->package_id;
        } elseif ($user->received_signup_bonus) {
            $freePkg = \App\Models\WordPackage::where('slug', 'free-starter')->first();
            if ($freePkg) {
                $activePackageId = $freePkg->id;
            }
        }
    }

    return Inertia::render('Welcome', [
        'packages' => $packages,
        'paystackConfigured' => $paystackConfigured,
        'paystackPublicKey' => $paystackPublicKey,
        'wordBalance' => $user ? $user->getWordBalanceData() : null,
        'activePackageId' => $activePackageId,
    ]);
})->name('home');

Route::get('/project-topics', [\App\Http\Controllers\PublicTopicController::class, 'index'])->name('project-topics.index');

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

// Theme test page (temporary for debugging)
Route::get('/theme-test', function () {
    // Load heavy data similar to ManualEditor to test theme behavior with large Inertia props
    $user = auth()->user();
    $heavyData = [];
    
    if ($user) {
        // Get a project with all relations (heavy load)
        $project = \App\Models\Project::where('user_id', $user->id)
            ->with([
                'category',
                'universityRelation',
                'facultyRelation', 
                'departmentRelation',
                'chapters',
                'outlines.sections',
            ])
            ->first();
            
        if ($project) {
            $heavyData['project'] = $project;
            $heavyData['allChapters'] = $project->chapters()->orderBy('chapter_number')->get();
            $heavyData['chapter'] = $project->chapters()->first();
            
            // Add more heavy data
            $heavyData['allProjects'] = \App\Models\Project::where('user_id', $user->id)
                ->with(['chapters', 'category'])
                ->get();
        }
    }
    
    // Also load some general heavy data
    $heavyData['sampleData'] = [
        'loremIpsum' => str_repeat('Lorem ipsum dolor sit amet, consectetur adipiscing elit. ', 100),
        'numbers' => range(1, 1000),
        'nestedData' => array_fill(0, 50, [
            'id' => rand(1, 10000),
            'name' => 'Sample Item ' . rand(1, 1000),
            'description' => str_repeat('This is a sample description. ', 20),
            'children' => array_fill(0, 10, [
                'childId' => rand(1, 10000),
                'childName' => 'Child Item',
            ]),
        ]),
    ];
    
    return Inertia::render('ThemeTest', [
        'heavyData' => $heavyData,
        'dataSize' => strlen(json_encode($heavyData)),
    ]);
})->middleware(['auth'])->name('theme-test');

// Admin routes
Route::prefix('admin')->middleware(['auth', 'role:super_admin|admin|support'])->group(function () {
    // Dashboard
    Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');

    // Users
    Route::prefix('users')->group(function () {
        Route::get('/', [AdminUserController::class, 'index'])->name('admin.users.index');
        Route::get('/{user}', [AdminUserController::class, 'show'])->name('admin.users.show');
        Route::put('/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');
        Route::post('/{user}/ban', [AdminUserController::class, 'ban'])->name('admin.users.ban');
        Route::post('/{user}/unban', [AdminUserController::class, 'unban'])->name('admin.users.unban');
        Route::post('/{user}/adjust-balance', [AdminUserController::class, 'adjustBalance'])->name('admin.users.adjust-balance');
        Route::delete('/{user}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
        Route::delete('/{user}/force', [AdminUserController::class, 'forceDestroy'])->name('admin.users.force-destroy');
        Route::post('/{user}/reset-password', [AdminUserController::class, 'resetPassword'])->name('admin.users.reset-password');
        Route::post('/{user}/impersonate', [AdminUserController::class, 'impersonate'])->name('admin.users.impersonate');
    });

    // Impersonation stop
    Route::post('/stop-impersonation', [AdminUserController::class, 'stopImpersonation'])->name('admin.stop-impersonation');

    // Payments
    Route::prefix('payments')->group(function () {
        Route::get('/', [AdminPaymentController::class, 'index'])->name('admin.payments.index');
        Route::get('/revenue', [AdminPaymentController::class, 'revenue'])->name('admin.payments.revenue');
        Route::get('/{payment}', [AdminPaymentController::class, 'show'])->name('admin.payments.show');
        Route::post('/{payment}/verify', [AdminPaymentController::class, 'verify'])->name('admin.payments.verify');
        Route::post('/{payment}/refund', [AdminPaymentController::class, 'refund'])->name('admin.payments.refund');
        Route::post('/manual-credit', [AdminPaymentController::class, 'manualCredit'])->name('admin.payments.manual-credit');
    });

    // Packages / Pricing
    Route::prefix('packages')->group(function () {
        Route::get('/', [AdminPackageController::class, 'index'])->name('admin.packages.index');
        Route::post('/', [AdminPackageController::class, 'store'])->name('admin.packages.store');
        Route::put('/{package}', [AdminPackageController::class, 'update'])->name('admin.packages.update');
        Route::delete('/{package}', [AdminPackageController::class, 'destroy'])->name('admin.packages.destroy');
        Route::put('/{package}/active', [AdminPackageController::class, 'toggleActive'])->name('admin.packages.toggle-active');
        Route::put('/{package}/popular', [AdminPackageController::class, 'togglePopular'])->name('admin.packages.toggle-popular');
    });

    // Analytics
    Route::prefix('analytics')->group(function () {
        Route::get('/', [AdminAnalyticsController::class, 'index'])->name('admin.analytics.index');
        Route::get('/users', [AdminAnalyticsController::class, 'users'])->name('admin.analytics.users');
        Route::get('/revenue', [AdminAnalyticsController::class, 'revenue'])->name('admin.analytics.revenue');
        Route::get('/usage', [AdminAnalyticsController::class, 'usage'])->name('admin.analytics.usage');
        Route::get('/export', [AdminAnalyticsController::class, 'export'])->name('admin.analytics.export');
    });

    // Projects
    Route::prefix('projects')->group(function () {
        Route::get('/', [AdminProjectController::class, 'index'])->name('admin.projects.index');
        Route::get('/{project}', [AdminProjectController::class, 'show'])->name('admin.projects.show');
        Route::delete('/{project}', [AdminProjectController::class, 'destroy'])->name('admin.projects.destroy');
        Route::get('/{project}/export', [AdminProjectController::class, 'export'])->name('admin.projects.export');
    });

    // AI Monitoring
    Route::prefix('ai')->group(function () {
        Route::get('/', [AdminAIController::class, 'index'])->name('admin.ai.index');
        Route::get('/queue', [AdminAIController::class, 'queue'])->name('admin.ai.queue');
        Route::get('/failures', [AdminAIController::class, 'failures'])->name('admin.ai.failures');
        Route::get('/metrics', [AdminAIController::class, 'metrics'])->name('admin.ai.metrics');
        Route::post('/refresh', [AdminAIController::class, 'refresh'])->name('admin.ai.refresh');
        Route::post('/retry/{generation}', [AdminAIController::class, 'retry'])->name('admin.ai.retry');
        Route::post('/circuit/{service}/reset', [AdminAIController::class, 'resetCircuit'])->name('admin.ai.reset-circuit');
    });

    // System
    Route::prefix('system')->group(function () {
        Route::get('/features', [AdminSystemController::class, 'features'])->name('admin.system.features');
        Route::put('/features/{flag}', [AdminSystemController::class, 'updateFeature'])->name('admin.system.update-feature');
        Route::get('/settings', [AdminSystemController::class, 'settings'])->name('admin.system.settings');
        Route::put('/settings', [AdminSystemController::class, 'updateSettings'])->name('admin.system.update-settings');
        Route::post('/cache/clear', [AdminSystemController::class, 'clearCache'])->name('admin.system.clear-cache');
    });

    // Audit Logs
    Route::get('/audit', [AdminAuditController::class, 'index'])->name('admin.audit.index');

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [AdminNotificationController::class, 'index'])->name('admin.notifications.index');
        Route::post('/{notification}/read', [AdminNotificationController::class, 'markRead'])->name('admin.notifications.read');
        Route::post('/read-all', [AdminNotificationController::class, 'markAllRead'])->name('admin.notifications.read-all');
    });
});

// Bulk project deletion (placed outside to avoid conflicts with numeric bindings)
Route::middleware(['auth', 'verified'])->match(['delete', 'post'], '/projects/bulk-destroy', [ProjectController::class, 'bulkDestroy'])
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])
    ->name('projects.bulk-destroy');

// Testing-only fallback for bulk delete to avoid routing conflicts
if (app()->environment('testing')) {
    Route::delete('/projects/bulk-destroy', [ProjectController::class, 'bulkDestroy'])
        ->name('projects.bulk-destroy');
}

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard - Main landing page after login
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Projects - Basic project management (no state checking needed)
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/topics', [TopicController::class, 'topicsIndex'])->name('projects.topics.index');

    // Wizard progress saving - AJAX endpoint to save progress as user fills form
    Route::post('/projects/wizard/save-progress', [ProjectController::class, 'saveWizardProgress'])->name('projects.save-wizard-progress');

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
        // Theme test inside project middleware (for debugging)
        Route::get('/projects/{project}/theme-test', function (\App\Models\Project $project) {
            $heavyData = [
                'project' => $project->load([
                    'category',
                    'universityRelation',
                    'facultyRelation',
                    'departmentRelation',
                    'chapters',
                    'outlines.sections',
                ]),
                'allChapters' => $project->chapters()->orderBy('chapter_number')->get(),
                'sampleData' => [
                    'loremIpsum' => str_repeat('Lorem ipsum dolor sit amet. ', 100),
                    'nestedData' => array_fill(0, 50, [
                        'id' => rand(1, 10000),
                        'children' => array_fill(0, 10, ['childId' => rand(1, 10000)]),
                    ]),
                ],
            ];
            
            return \Inertia\Inertia::render('ThemeTest', [
                'heavyData' => $heavyData,
                'dataSize' => strlen(json_encode($heavyData)),
                'insideMiddleware' => true,
            ]);
        })->name('projects.theme-test');
        
        // ManualEditor Debug Route - renders the same data as real ManualEditor
        Route::get('/projects/{project:slug}/manual-editor-debug/{chapter}', function (\App\Models\Project $project, int $chapter) {
            $chapterModel = \App\Models\Chapter::where('project_id', $project->id)
                ->where('chapter_number', $chapter)
                ->firstOrFail();
            
            $facultyStructureService = app(\App\Services\FacultyStructureService::class);
            
            return \Inertia\Inertia::render('projects/ManualEditorDebug', [
                'project' => $project->load([
                    'category',
                    'universityRelation',
                    'facultyRelation',
                    'departmentRelation',
                    'outlines.sections',
                ]),
                'chapter' => $chapterModel,
                'allChapters' => $project->chapters()->orderBy('chapter_number')->get(),
                'facultyChapters' => $facultyStructureService->getChapterStructure($project),
            ]);
        })->name('projects.manual-editor-debug');
        
        Route::get('/projects/{project}/topic-selection', [ProjectController::class, 'topicSelection'])->name('projects.topic-selection');
        Route::get('/projects/{project}/topic-approval', [ProjectController::class, 'topicApproval'])->name('projects.topic-approval');
        Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
        Route::post('/projects/{project}/set-active', [ProjectController::class, 'setActive'])->name('projects.set-active');
        Route::post('/projects/{project}/complete', [ProjectController::class, 'complete'])->name('projects.complete');

        // Topic management routes - also need state checking
        Route::post('/projects/{project}/topics/generate', [TopicController::class, 'generate'])->name('topics.generate');
        Route::get('/projects/{project}/topics/stream', [TopicController::class, 'stream'])->name('topics.stream');
        Route::get('/projects/{project}/topics/lab', [TopicLabController::class, 'lab'])->name('topics.lab');
        Route::post('/projects/{project}/topics/chat', [TopicLabController::class, 'chat'])->name('topics.chat');
        Route::post('/projects/{project}/topics/chat/rename', [TopicLabController::class, 'renameSession'])->name('topics.chat.rename');
        Route::post('/projects/{project}/topics/chat/save-topic', [TopicLabController::class, 'saveRefinedTopic'])->name('topics.chat.save-topic');
        Route::delete('/projects/{project}/topics/chat/session', [TopicLabController::class, 'deleteSession'])->name('topics.chat.delete-session');
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
        Route::get('/projects/{project}/chapters/{chapter}/ai-generate', [ChapterController::class, 'aiGenerate'])->name('chapters.ai-generate');
        Route::post('/projects/{project}/chapters/generate', [ChapterController::class, 'generate'])
            ->middleware(['prevent.duplicate:30', 'check.words'])
            ->name('chapters.generate');
        Route::get('/projects/{project}/chapters/{chapter}/stream', [ChapterController::class, 'stream'])
            ->middleware(['prevent.duplicate:30', 'check.words'])
            ->name('chapters.stream');
        Route::post('/projects/{project}/chapters/{chapter}/mark-complete', [ChapterController::class, 'markComplete'])
            ->name('chapters.mark-complete');
        Route::post('/projects/{project}/chapters/save', [ChapterController::class, 'save'])->name('chapters.save');
        Route::post('/projects/{project}/chapters/{chapter}/chat', [ChapterController::class, 'chat'])
            ->middleware(['prevent.duplicate:10', 'check.words'])
            ->name('chapters.chat');
        Route::get('/projects/{project}/chapters/{chapter}/chat/history', [ChapterController::class, 'getChatHistory'])->name('chapters.chat-history');
        Route::post('/projects/{project}/chapters/{chapter}/chat/stream', [ChapterController::class, 'streamChat'])
            ->middleware(['prevent.duplicate:10', 'check.words'])
            ->name('chapters.chat-stream');

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
            Route::post('/{chapter}/analyze', [ManualEditorController::class, 'analyzeAndSuggest'])->middleware(['prevent.duplicate:15', 'check.words'])->name('analyze');
            Route::post('/{chapter}/progressive-guidance', [ManualEditorController::class, 'progressiveGuidance'])->middleware(['prevent.duplicate:15', 'check.words'])->name('progressive-guidance');
            Route::post('/{chapter}/suggestions/{suggestion}/save', [ManualEditorController::class, 'saveSuggestion'])->middleware(['prevent.duplicate:10', 'check.words'])->name('suggestion.save');
            Route::post('/{chapter}/suggestions/{suggestion}/clear', [ManualEditorController::class, 'clearSuggestion'])->middleware(['prevent.duplicate:5', 'check.words'])->name('suggestion.clear');
            Route::post('/{chapter}/suggestions/{suggestion}/apply', [ManualEditorController::class, 'applySuggestion'])->middleware(['prevent.duplicate:10', 'check.words'])->name('suggestion.apply');
            Route::post('/{chapter}/chat', [ManualEditorController::class, 'chat'])->middleware(['prevent.duplicate:10', 'check.words'])->name('chat');
            // Quick Actions
            Route::post('/{chapter}/improve-text', [ManualEditorController::class, 'improveText'])->middleware(['prevent.duplicate:10', 'check.words'])->name('improve-text');
            Route::post('/{chapter}/expand-text', [ManualEditorController::class, 'expandText'])->middleware(['prevent.duplicate:10', 'check.words'])->name('expand-text');
            Route::post('/{chapter}/suggest-citations', [ManualEditorController::class, 'suggestCitations'])->middleware(['prevent.duplicate:10', 'check.words'])->name('suggest-citations');
            Route::post('/{chapter}/rephrase-text', [ManualEditorController::class, 'rephraseText'])->middleware(['prevent.duplicate:10', 'check.words'])->name('rephrase-text');
        });

        // Project Guidance routes
        Route::get('/projects/{project}/guidance', [ProjectGuidanceController::class, 'index'])->name('projects.guidance');
        Route::get('/projects/{project}/guidance/chapter/{chapterNumber}', [ProjectGuidanceController::class, 'chapterGuidance'])->name('projects.guidance-chapter');
        Route::get('/projects/{project}/guidance/writing-guidelines', [ProjectGuidanceController::class, 'writingGuidelines'])->name('projects.writing-guidelines');
        Route::post('/projects/{project}/guidance/proceed-to-writing', [ProjectGuidanceController::class, 'proceedToWriting'])->name('projects.proceed-to-writing');

        // Bulk chapter analysis page
        Route::get('/projects/{project:slug}/analysis', [ProjectAnalysisController::class, 'index'])
            ->name('projects.analysis');
    });

    // Simple test route to debug API routing
    Route::get('/api/test', function () {
        return response()->json(['message' => 'API routing works', 'time' => now()]);
    })->name('api.test');
}); // End of ProjectStateMiddleware group
