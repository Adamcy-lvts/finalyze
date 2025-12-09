<?php

use App\Http\Controllers\Api\CitationController;
use App\Http\Controllers\Api\PaperCollectionController;
use App\Http\Controllers\ChapterAnalysisController;
use App\Http\Controllers\DataCollectionController;
use App\Http\Controllers\DefenseController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectGuidanceController;
use Illuminate\Support\Facades\Route;

// Simple ping endpoint for connection quality check (no auth required)
Route::get('/ping', function () {
    return response()->noContent();
})->name('api.ping');

// API routes - using sanctum for SPA authentication
Route::middleware(['auth:sanctum'])->group(function () {
    // University, Faculty, and Department endpoints
    Route::get('/universities', [\App\Http\Controllers\Api\UniversityController::class, 'index'])
        ->name('api.universities.index');

    Route::get('/faculties', [\App\Http\Controllers\Api\FacultyController::class, 'index'])
        ->name('api.faculties.index');

    Route::get('/faculties/{faculty}/departments', [\App\Http\Controllers\Api\DepartmentController::class, 'byFaculty'])
        ->name('api.faculties.departments');

    Route::get('/departments', [\App\Http\Controllers\Api\DepartmentController::class, 'index'])
        ->name('api.departments.index');

    // Citation verification routes
    Route::post('/projects/{project}/chapters/{chapter}/verify-citations', [CitationController::class, 'verifyCitations'])
        ->name('api.projects.chapters.verify-citations')
        ->scopeBindings();

    // Keep old route for backward compatibility (will be deprecated)
    Route::post('/chapters/{chapter}/verify-citations', [CitationController::class, 'verifyCitations'])
        ->name('api.chapters.verify-citations');

    // Progress and result tracking endpoints
    Route::get('/citation-verification/progress/{sessionId}', [CitationController::class, 'getProgress'])
        ->name('api.citation-verification.progress');

    Route::get('/citation-verification/result/{sessionId}', [CitationController::class, 'getResult'])
        ->name('api.citation-verification.result');

    // Citation Helper routes
    Route::get('/citations/recent', [CitationController::class, 'getRecentCitations'])
        ->name('api.citations.recent');

    Route::post('/citations/generate', [CitationController::class, 'generateCitation'])
        ->name('api.citations.generate');

    Route::get('/citations/search', [CitationController::class, 'searchCitations'])
        ->name('api.citations.search');

    Route::delete('/citations/clear', [CitationController::class, 'clearCitations'])
        ->name('api.citations.clear');

    // Paper collection routes
    Route::prefix('projects/{project}/paper-collection')->name('api.projects.paper-collection.')->group(function () {
        Route::post('/start', [PaperCollectionController::class, 'startCollection'])
            ->name('start');

        Route::get('/status', [PaperCollectionController::class, 'getStatus'])
            ->name('status');

        Route::get('/papers', [PaperCollectionController::class, 'getPapers'])
            ->name('papers');

        Route::post('/reset', [PaperCollectionController::class, 'resetCollection'])
            ->name('reset');
    });

    // AI Section Suggestion API - using existing ChapterController
    Route::post('/projects/{project_id}/chapters/{chapter_id}/suggest-section', [\App\Http\Controllers\ChapterController::class, 'suggestNextSection'])
        ->name('api.ai.suggest-section');

    // Chapter Analysis API
    Route::prefix('chapters')->name('api.chapters.analysis.')->group(function () {
        Route::post('{chapter}/analyze', [ChapterAnalysisController::class, 'analyze'])
            ->name('analyze')
            ->where('chapter', '[0-9]+');
        Route::get('{chapter}/analysis/latest', [ChapterAnalysisController::class, 'latest'])
            ->name('latest')
            ->where('chapter', '[0-9]+');
        Route::get('{chapter}/analysis/history', [ChapterAnalysisController::class, 'history'])
            ->name('history')
            ->where('chapter', '[0-9]+');
    });

    Route::get('/projects/analysis/overview', [ChapterAnalysisController::class, 'overview'])
        ->name('api.projects.analysis.overview');

    // Bulk Chapter Analysis
    Route::prefix('projects/{project}')->group(function () {
        Route::post('/analysis/start', [\App\Http\Controllers\ChapterAnalysisBatchController::class, 'start'])
            ->name('api.projects.analysis.start');
        Route::get('/analysis/batches/{batch}', [\App\Http\Controllers\ChapterAnalysisBatchController::class, 'show'])
            ->name('api.projects.analysis.batch');
        Route::get('/analysis/results', [\App\Http\Controllers\ChapterAnalysisBatchController::class, 'results'])
            ->name('api.projects.analysis.results');
    });

    // Data Collection Placeholder API
    Route::prefix('chapters')->name('api.chapters.data-collection.')->group(function () {
        Route::get('{chapter}/detect', [DataCollectionController::class, 'detect'])
            ->name('detect');
        Route::get('{chapter}/placeholder', [DataCollectionController::class, 'generatePlaceholder'])
            ->name('placeholder');
        Route::get('{chapter}/suggestions', [DataCollectionController::class, 'getSuggestions'])
            ->name('suggestions');
        Route::post('{chapter}/insert-template', [DataCollectionController::class, 'insertTemplate'])
            ->name('insert-template');
    });

    Route::get('/data-collection/template', [DataCollectionController::class, 'getTemplate'])
        ->name('api.data-collection.template');
    Route::get('/data-collection/templates', [DataCollectionController::class, 'getAllTemplates'])
        ->name('api.data-collection.templates');

    // Defense Questions API - Use ID instead of slug for API routes
    Route::prefix('projects')->group(function () {
        Route::get('{project_id}/defense/questions', [DefenseController::class, 'getQuestions'])
            ->name('api.defense.questions');
        Route::post('{project_id}/defense/questions/generate', [DefenseController::class, 'generateQuestions'])
            ->name('api.defense.generate');
        Route::get('{project_id}/defense/stream', [DefenseController::class, 'streamGenerate'])
            ->name('api.defense.stream');
        Route::patch('{project_id}/defense/questions/{question}', [DefenseController::class, 'markHelpful'])
            ->name('api.defense.mark-helpful');
        Route::delete('{project_id}/defense/questions/{question}', [DefenseController::class, 'hideQuestion'])
            ->name('api.defense.hide');
    });
});

// Project Guidance API
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/projects/{project}/guidance/proceed-to-writing', [ProjectGuidanceController::class, 'proceedToWriting'])
        ->name('api.projects.proceed-to-writing');

    Route::post('/projects/{project}/guidance/regenerate/{chapterNumber}', [ProjectGuidanceController::class, 'regenerateChapterGuidance'])
        ->name('api.projects.regenerate-chapter-guidance')
        ->where('chapterNumber', '[0-9]+');

    Route::post('/projects/{project}/guidance/regenerate-all', [ProjectGuidanceController::class, 'regenerateAllGuidance'])
        ->name('api.projects.regenerate-all-guidance');

    Route::get('/projects/{project}/guidance/stream-bulk-generation', [ProjectGuidanceController::class, 'streamBulkGeneration'])
        ->name('api.projects.stream-bulk-generation');

    // Bulk project generation
    Route::post('/projects/{project}/bulk-generate/start', [ProjectController::class, 'startBulkGeneration'])
        ->name('api.projects.bulk-generate.start');

    Route::get('/projects/{project}/bulk-generate/status', [ProjectController::class, 'checkBulkGenerationStatus'])
        ->name('api.projects.bulk-generate.status');

    Route::post('/projects/{project}/bulk-generate/cancel', [ProjectController::class, 'cancelBulkGeneration'])
        ->name('api.projects.bulk-generate.cancel');
});
