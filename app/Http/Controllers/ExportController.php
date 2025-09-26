<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    public function __construct(
        protected ExportService $exportService
    ) {}

    /**
     * Export entire project to Word document
     */
    public function exportWord(Project $project): BinaryFileResponse|JsonResponse
    {
        // Ensure user owns the project
        abort_if($project->user_id !== auth()->id(), 403);

        try {
            // Check if project has any content
            $hasContent = $project->chapters()
                ->whereNotNull('content')
                ->where('content', '!=', '')
                ->exists();

            if (! $hasContent) {
                return response()->json([
                    'message' => 'This project has no chapter content to export. Please add content to at least one chapter before exporting.',
                    'error' => 'no_content',
                ], 422);
            }

            // Generate the export
            Log::info('Starting project export via web interface', [
                'project_id' => $project->id,
                'user_id' => auth()->id(),
                'project_title' => $project->title,
            ]);

            $filename = $this->exportService->exportToWord($project);

            // Verify file exists and is readable
            if (! file_exists($filename) || ! is_readable($filename)) {
                throw new \Exception('Export file could not be created or is not readable');
            }

            // Check file size
            $filesize = filesize($filename);
            if ($filesize === 0) {
                throw new \Exception('Export file is empty');
            }

            Log::info('Project export successful', [
                'project_id' => $project->id,
                'filename' => basename($filename),
                'size' => $filesize,
            ]);

            // Return file download
            return response()->download($filename, "{$project->slug}.docx", [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'Content-Disposition' => 'attachment; filename="'.$project->slug.'.docx"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Project export failed', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'We encountered an issue while preparing your document for export. Please try again in a few moments.',
                'error' => 'export_failed',
                'details' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Export single chapter to Word document
     */
    public function exportChapter(Project $project, int $chapterNumber): BinaryFileResponse|JsonResponse
    {
        Log::info('Chapter export request', [
            'project_id' => $project->id,
            'chapter_number' => $chapterNumber,
            'user_id' => auth()->id(),
        ]);

        // Ensure user owns the project
        abort_if($project->user_id !== auth()->id(), 403);

        try {
            // Find the chapter
            $chapter = $project->chapters()
                ->where('chapter_number', $chapterNumber)
                ->first();

            if (! $chapter) {
                return response()->json([
                    'message' => 'Chapter not found.',
                    'error' => 'chapter_not_found',
                ], 404);
            }

            // Check if chapter has content
            if (empty($chapter->content)) {
                return response()->json([
                    'message' => 'This chapter has no content to export. Please add content before exporting.',
                    'error' => 'no_content',
                ], 422);
            }

            Log::info('Chapter found for export', [
                'chapter_id' => $chapter->id,
                'chapter_title' => $chapter->title,
                'content_length' => strlen($chapter->content),
            ]);

            // Generate the export
            Log::info('Starting single chapter export via web interface', [
                'project_id' => $project->id,
                'chapter_id' => $chapter->id,
                'chapter_number' => $chapterNumber,
                'user_id' => auth()->id(),
            ]);

            $filename = $this->exportService->exportChapterToWord($project, $chapter);

            // Verify file
            if (! file_exists($filename) || ! is_readable($filename)) {
                throw new \Exception('Export file could not be created');
            }

            $filesize = filesize($filename);
            Log::info('Chapter export successful', [
                'filename' => basename($filename),
                'file_size' => $filesize,
            ]);

            return response()->download($filename, "{$project->slug}-chapter-{$chapterNumber}.docx", [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'Content-Disposition' => 'attachment; filename="'.$project->slug.'-chapter-'.$chapterNumber.'.docx"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Chapter export failed', [
                'project_id' => $project->id,
                'chapter_number' => $chapterNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'We encountered an issue while preparing your chapter for export. Please try again in a few moments.',
                'error' => 'chapter_export_failed',
                'chapter' => $chapterNumber,
                'details' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Export multiple selected chapters to Word document
     */
    public function exportChapters(Project $project, Request $request): BinaryFileResponse|JsonResponse
    {
        // Ensure user owns the project
        abort_if($project->user_id !== auth()->id(), 403);

        // Validate chapter numbers
        try {
            $validated = $request->validate([
                'chapters' => 'required|array|min:1|max:20',
                'chapters.*' => 'integer|min:1|max:50',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Please select valid chapters to export (1-20 chapters).',
                'error' => 'validation_failed',
                'errors' => $e->errors(),
            ], 422);
        }

        $chapterNumbers = array_unique($validated['chapters']);
        sort($chapterNumbers);

        try {
            // Verify chapters exist and have content
            $chapters = $project->chapters()
                ->whereIn('chapter_number', $chapterNumbers)
                ->get();

            if ($chapters->isEmpty()) {
                return response()->json([
                    'message' => 'No valid chapters found for the selected numbers.',
                    'error' => 'no_chapters_found',
                    'requested' => $chapterNumbers,
                ], 404);
            }

            // Check if any chapter has content
            $chaptersWithContent = $chapters->filter(function ($chapter) {
                return ! empty($chapter->content);
            });

            if ($chaptersWithContent->isEmpty()) {
                return response()->json([
                    'message' => 'None of the selected chapters have content to export.',
                    'error' => 'no_content',
                    'chapters' => $chapterNumbers,
                ], 422);
            }

            // Update chapter numbers to only include those with content
            $validChapterNumbers = $chaptersWithContent->pluck('chapter_number')->toArray();

            Log::info('Starting multiple chapters export', [
                'project_id' => $project->id,
                'requested_chapters' => $chapterNumbers,
                'valid_chapters' => $validChapterNumbers,
                'chapter_count' => count($validChapterNumbers),
            ]);

            // Generate the export
            $filename = $this->exportService->exportMultipleChaptersToWord($project, $validChapterNumbers);

            // Verify file
            if (! file_exists($filename) || ! is_readable($filename)) {
                throw new \Exception('Export file could not be created');
            }

            $filesize = filesize($filename);
            if ($filesize === 0) {
                throw new \Exception('Export file is empty');
            }

            $chaptersString = implode('-', $validChapterNumbers);

            Log::info('Multiple chapters export successful', [
                'filename' => basename($filename),
                'file_size' => $filesize,
                'chapters_exported' => $validChapterNumbers,
            ]);

            return response()->download($filename, "{$project->slug}-chapters-{$chaptersString}.docx", [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'Content-Disposition' => 'attachment; filename="'.$project->slug.'-chapters-'.$chaptersString.'.docx"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Multiple chapters export failed', [
                'project_id' => $project->id,
                'chapter_numbers' => $chapterNumbers,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'We encountered an issue while preparing your selected chapters for export. Please try again.',
                'error' => 'multiple_chapters_export_failed',
                'chapters' => $chapterNumbers,
                'details' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get export status and availability for a project
     */
    public function getExportStatus(Project $project): JsonResponse
    {
        // Ensure user owns the project
        abort_if($project->user_id !== auth()->id(), 403);

        $chapters = $project->chapters()->get();
        $chaptersWithContent = $chapters->filter(function ($chapter) {
            return ! empty($chapter->content);
        });

        return response()->json([
            'can_export' => $chaptersWithContent->isNotEmpty(),
            'total_chapters' => $chapters->count(),
            'chapters_with_content' => $chaptersWithContent->count(),
            'available_chapters' => $chaptersWithContent->map(function ($chapter) {
                return [
                    'chapter_number' => $chapter->chapter_number,
                    'title' => $chapter->title,
                    'word_count' => $chapter->word_count,
                    'status' => $chapter->status,
                ];
            })->values(),
            'has_references' => ! empty($project->references),
            'project_word_count' => $chaptersWithContent->sum('word_count'),
        ]);
    }
}
