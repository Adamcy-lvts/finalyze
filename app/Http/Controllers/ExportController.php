<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\Project;
use App\Services\ExportService;
use App\Services\TemplateVariableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Enums\Orientation;
use Spatie\LaravelPdf\Facades\Pdf;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    public function __construct(
        protected ExportService $exportService,
        protected TemplateVariableService $templateVariableService
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

    /**
     * EXPORT FULL PROJECT TO PDF
     * Generates a professional PDF document for the entire project
     * Uses Browsershot for reliable PDF generation
     */
    public function exportProjectPdf(Project $project)
    {
        $startTime = microtime(true);

        Log::info('Project PDF Export Request Received', [
            'project_id' => $project->id,
            'user_id' => auth()->id(),
            'project_slug' => $project->slug,
            'timestamp' => now()->toDateTimeString(),
        ]);

        try {
            // Ensure user owns the project
            Log::info('Project PDF Export: Checking user authorization', [
                'project_user_id' => $project->user_id,
                'auth_user_id' => auth()->id(),
                'is_authorized' => $project->user_id === auth()->id(),
            ]);
            abort_if($project->user_id !== auth()->id(), 403);

            // Get all chapters with content
            $chapters = $project->chapters()
                ->whereNotNull('content')
                ->where('content', '!=', '')
                ->orderBy('chapter_number')
                ->get();

            Log::info('Project PDF Export: Chapters found', [
                'total_chapters' => $chapters->count(),
                'chapter_numbers' => $chapters->pluck('chapter_number')->toArray(),
            ]);

            // Ensure project has content to export
            abort_if($chapters->isEmpty(), 404, 'No content available for export');

            // Load project with all necessary relationships for PDF generation
            $project->load(['user', 'category']);

            Log::info('Project PDF Export: Project data loaded', [
                'project_id' => $project->id,
                'has_user_relation' => $project->user !== null,
                'has_category_relation' => $project->category !== null,
                'user_name' => $project->user->name ?? 'N/A',
            ]);

            // Validate required relationships exist
            if (! $project->user) {
                Log::error('Project PDF Export: User relationship missing', [
                    'project_id' => $project->id,
                    'project_user_id' => $project->user_id,
                ]);
                throw new \Exception('Project user relationship is missing. Cannot generate PDF.');
            }

            // Validate required fields exist
            if (! $project->title || ! $project->type) {
                Log::error('Project PDF Export: Required fields missing', [
                    'project_id' => $project->id,
                    'has_title' => ! empty($project->title),
                    'has_type' => ! empty($project->type),
                ]);
                throw new \Exception('Project is missing required fields (title or type). Cannot generate PDF.');
            }

            // Convert Tiptap JSON content to HTML for all chapters
            $chapterContents = [];
            $totalWords = 0;

            Log::info('Project PDF Export: Starting content conversion', [
                'total_chapters' => $chapters->count(),
            ]);

            foreach ($chapters as $chapter) {
                try {
                    $html = $this->convertTiptapToHtml($chapter->content);
                    $chapterContents[$chapter->id] = $html;
                    $totalWords += $chapter->word_count;

                    Log::debug('Project PDF Export: Chapter content converted', [
                        'chapter_id' => $chapter->id,
                        'chapter_number' => $chapter->chapter_number,
                        'chapter_title' => $chapter->title,
                        'original_length' => strlen($chapter->content),
                        'converted_length' => strlen($html),
                        'word_count' => $chapter->word_count,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Project PDF Export: Chapter content conversion failed', [
                        'chapter_id' => $chapter->id,
                        'chapter_number' => $chapter->chapter_number,
                        'error' => $e->getMessage(),
                    ]);
                    // Provide fallback content
                    $chapterContents[$chapter->id] = '<p>Error converting chapter content.</p>';
                }
            }

            Log::info('Project PDF Export: Content conversion complete', [
                'total_words' => $totalWords,
                'chapters_converted' => count($chapterContents),
            ]);

            // Substitute template variables in preliminary pages
            Log::info('Project PDF Export: Starting template variable substitution');

            $processedProject = clone $project;
            if ($project->dedication) {
                $processedProject->dedication = $this->templateVariableService->substituteVariables(
                    $project->dedication,
                    $project
                );
            }
            if ($project->acknowledgements) {
                $processedProject->acknowledgements = $this->templateVariableService->substituteVariables(
                    $project->acknowledgements,
                    $project
                );
            }
            if ($project->abstract) {
                $processedProject->abstract = $this->templateVariableService->substituteVariables(
                    $project->abstract,
                    $project
                );
            }
            if ($project->declaration) {
                $processedProject->declaration = $this->templateVariableService->substituteVariables(
                    $project->declaration,
                    $project
                );
            }
            if ($project->certification) {
                $processedProject->certification = $this->templateVariableService->substituteVariables(
                    $project->certification,
                    $project
                );
            }

            Log::info('Project PDF Export: Template variable substitution complete');

            // Create a unique filename
            $fileName = sprintf(
                '%s_full_project_%s.pdf',
                Str::slug($project->title),
                now()->format('Ymd-His')
            );

            // Create directory if it doesn't exist
            $directory = storage_path('app/public/project-exports/'.date('Y/m'));
            if (! File::isDirectory($directory)) {
                File::makeDirectory($directory, 0755, true);
            }

            $filePath = $directory.'/'.$fileName;

            try {
                // Generate PDF using Spatie PDF with Browsershot for reliability
                $pdf = Pdf::view('pdf.project', [
                    'project' => $processedProject,
                    'chapters' => $chapters,
                    'chapterContents' => $chapterContents,
                    'totalWords' => $totalWords,
                    'isPdfMode' => true,
                ])
                    ->format('A4')
                    ->orientation(Orientation::Portrait)
                    ->withBrowsershot(function (Browsershot $browsershot) {
                        // Try to find installed browsers in the system
                        $chromePaths = [
                            config('app.chrome_path'), // First try the configured path
                            '/usr/bin/chromium-browser',
                            '/usr/bin/chromium',
                            '/usr/bin/google-chrome',
                            '/usr/bin/google-chrome-stable',
                            '/snap/bin/chromium',
                            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', // macOS
                        ];

                        Log::info('Project PDF Generation: Chrome Path Detection', [
                            'available_paths' => $chromePaths,
                            'config_chrome_path' => config('app.chrome_path'),
                        ]);

                        $chromePath = null;
                        foreach ($chromePaths as $path) {
                            Log::debug("Project PDF Generation: Testing Chrome path: {$path}");
                            if ($path && file_exists($path) && is_executable($path)) {
                                $chromePath = $path;
                                Log::info("Project PDF Generation: Chrome path found: {$chromePath}");
                                break;
                            }
                        }

                        if (! $chromePath) {
                            Log::error('Project PDF Generation: No Chrome path found!', [
                                'tested_paths' => $chromePaths,
                            ]);
                            throw new \Exception('Chrome/Chromium browser not found for PDF generation');
                        }

                        Log::info('Project PDF Generation: Configuring Browsershot', [
                            'chrome_path' => $chromePath,
                            'format' => 'A4',
                            'margins' => '20x20x20x20',
                            'timeout' => 180,
                        ]);

                        $browsershot->setChromePath($chromePath)
                            ->format('A4')
                            ->margins(0, 0, 0, 0) // Paged.js handles margins via CSS @page
                            ->showBackground()
                            ->waitUntilNetworkIdle() // Wait for all network requests (CDN scripts) to complete
                            ->delay(2000) // Wait 2 seconds for Paged.js to load and execute
                            ->windowStatus('ready_to_print') // Wait for Paged.js to finish
                            ->userAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36')
                            ->deviceScaleFactor(1.5) // Higher resolution for better quality
                            ->timeout(180) // Longer timeout for full project
                            ->noSandbox()
                            ->dismissDialogs() // Dismiss any alerts/confirms that might block
                            ->setOption('disable-web-security', true)
                            ->setOption('allow-running-insecure-content', true);
                    });

                Log::info('Project PDF Generation: Starting PDF creation', [
                    'view' => 'pdf.project',
                    'output_path' => $filePath,
                    'project_data' => [
                        'id' => $project->id,
                        'slug' => $project->slug,
                        'title' => $project->title,
                        'chapters_count' => $chapters->count(),
                        'total_words' => $totalWords,
                    ],
                ]);

                $pdf->save($filePath);

                Log::info('Project PDF Generation: Save operation completed', [
                    'file_path' => $filePath,
                    'file_exists' => File::exists($filePath),
                ]);

                if (! File::exists($filePath)) {
                    Log::error('Project PDF Generation: File was not created', [
                        'expected_path' => $filePath,
                        'directory_exists' => File::exists(dirname($filePath)),
                        'directory_writable' => is_writable(dirname($filePath)),
                    ]);
                    throw new \Exception("PDF file was not created at: {$filePath}");
                }

                // Validate PDF file format
                $fileSize = File::size($filePath);
                $fileHeader = file_get_contents($filePath, false, null, 0, 4);

                Log::info('Project PDF Generation: File validation', [
                    'file_size' => $fileSize,
                    'file_header' => bin2hex($fileHeader),
                    'is_valid_pdf' => $fileHeader === '%PDF',
                ]);

                if ($fileHeader !== '%PDF') {
                    Log::error('Project PDF Generation: Invalid PDF format detected', [
                        'file_path' => $filePath,
                        'file_size' => $fileSize,
                        'header_hex' => bin2hex($fileHeader),
                        'first_100_chars' => substr(file_get_contents($filePath), 0, 100),
                    ]);
                }

                // Log successful PDF generation
                $executionTime = round((microtime(true) - $startTime) * 1000, 2);
                Log::info('Project PDF Generated Successfully', [
                    'file' => $filePath,
                    'execution_time_ms' => $executionTime,
                ]);

                // Log download preparation
                Log::info('Project PDF Export: Preparing download response', [
                    'filename' => $fileName,
                    'content_type' => 'application/pdf',
                    'file_size' => File::size($filePath),
                    'project_id' => $project->id,
                    'user_id' => auth()->id(),
                    'execution_time_ms' => $executionTime,
                    'delete_after_send' => true,
                ]);

                // Return file download response
                return response()->download($filePath, $fileName, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
                ])->deleteFileAfterSend(true);

            } catch (\Exception $e) {
                Log::error('Project PDF Generation Error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'file_path' => $filePath ?? 'not_set',
                ]);

                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Project PDF Export Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'project_id' => $project->id ?? null,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'error' => 'Failed to generate PDF. Please try again.',
                'message' => 'PDF generation encountered an error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Convert Tiptap JSON content to HTML
     */
    private function convertTiptapToHtml(string $content): string
    {
        // If content is already HTML, return it
        if (str_starts_with(trim($content), '<')) {
            return $content;
        }

        // Try to decode as JSON (Tiptap format)
        $json = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // If not JSON, treat as plain text
            return nl2br(e($content));
        }

        // Basic Tiptap to HTML conversion
        return $this->tiptapNodeToHtml($json);
    }

    /**
     * Recursively convert Tiptap nodes to HTML
     */
    private function tiptapNodeToHtml(array $node): string
    {
        $html = '';

        if (! isset($node['type'])) {
            return $html;
        }

        $type = $node['type'];
        $content = $node['content'] ?? [];
        $marks = $node['marks'] ?? [];
        $attrs = $node['attrs'] ?? [];

        // Handle text nodes
        if ($type === 'text') {
            $text = $node['text'] ?? '';
            // Escape HTML entities to prevent malformed HTML
            $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

            // Apply marks (bold, italic, etc.)
            foreach ($marks as $mark) {
                $markType = $mark['type'];
                switch ($markType) {
                    case 'bold':
                        $text = "<strong>{$text}</strong>";
                        break;
                    case 'italic':
                        $text = "<em>{$text}</em>";
                        break;
                    case 'underline':
                        $text = "<u>{$text}</u>";
                        break;
                    case 'code':
                        $text = "<code>{$text}</code>";
                        break;
                    case 'link':
                        $href = htmlspecialchars($mark['attrs']['href'] ?? '#', ENT_QUOTES, 'UTF-8');
                        $text = "<a href=\"{$href}\">{$text}</a>";
                        break;
                }
            }

            return $text;
        }

        // Handle block nodes
        $childrenHtml = '';
        foreach ($content as $child) {
            $childrenHtml .= $this->tiptapNodeToHtml($child);
        }

        switch ($type) {
            case 'doc':
                return $childrenHtml;
            case 'paragraph':
                return "<p>{$childrenHtml}</p>";
            case 'heading':
                $level = $attrs['level'] ?? 1;

                return "<h{$level}>{$childrenHtml}</h{$level}>";
            case 'bulletList':
                return "<ul>{$childrenHtml}</ul>";
            case 'orderedList':
                return "<ol>{$childrenHtml}</ol>";
            case 'listItem':
                return "<li>{$childrenHtml}</li>";
            case 'blockquote':
                return "<blockquote>{$childrenHtml}</blockquote>";
            case 'codeBlock':
                return "<pre><code>{$childrenHtml}</code></pre>";
            case 'hardBreak':
                return '<br>';
            case 'horizontalRule':
                return '<hr>';
            default:
                return $childrenHtml;
        }
    }
}
