<?php

namespace App\Actions\Topics;

use App\Models\Project;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Enums\Orientation;
use Spatie\LaravelPdf\Facades\Pdf;

class ExportTopicPdfAction
{
    public function execute(Project $project, bool $isGuest = false)
    {
        $startTime = microtime(true);

        Log::info('PDF Export Request Received', [
            'project_id' => $project->id,
            'user_id' => auth()->id(),
            'project_slug' => $project->slug,
            'is_guest' => $isGuest,
            'timestamp' => now()->toDateTimeString(),
        ]);

        try {
            // Ensure user owns the project (skip if guest)
            if (! $isGuest) {
                Log::info('PDF Export: Checking user authorization', [
                    'project_user_id' => $project->user_id,
                    'auth_user_id' => auth()->id(),
                    'is_authorized' => $project->user_id === auth()->id(),
                ]);
                abort_if($project->user_id !== auth()->id(), 403);
            }

            // Ensure project has a topic to export
            Log::info('PDF Export: Checking project topic', [
                'has_topic' => ! empty($project->topic),
                'topic_length' => $project->topic ? strlen($project->topic) : 0,
            ]);
            abort_if(empty($project->topic), 404, 'No topic available for export');

            // Load project with all necessary relationships for PDF generation (skip if guest as they are already mocked)
            if (! $isGuest) {
                $project->load(['user', 'category']);
            }

            Log::info('PDF Export: Project data loaded', [
                'project_id' => $project->id,
                'user_id' => auth()->id(),
                'project_slug' => $project->slug,
                'has_user_relation' => $project->user !== null,
                'has_category_relation' => $project->category !== null,
                'user_name' => $project->user->name ?? 'N/A',
                'category_name' => $project->category->name ?? 'N/A',
            ]);

            // Create a unique filename
            $fileName = sprintf(
                'project_topic_proposal_%s_%s_%s.pdf',
                $project->slug,
                now()->format('Ymd-His'),
                Str::random(6)
            );

            // Create directory if it doesn't exist
            $directory = storage_path('app/public/topic-proposals/'.date('Y/m'));
            if (! File::isDirectory($directory)) {
                File::makeDirectory($directory, 0755, true);
            }

            $filePath = $directory.'/'.$fileName;

            try {
                // Generate PDF using Spatie PDF with Browsershot for reliability
                $pdf = Pdf::view('pdf.topic-proposal', [
                    'project' => $project,
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

                        Log::info('PDF Generation: Chrome Path Detection', [
                            'available_paths' => $chromePaths,
                            'config_chrome_path' => config('app.chrome_path'),
                        ]);

                        $chromePath = null;
                        foreach ($chromePaths as $path) {
                            Log::debug("PDF Generation: Testing Chrome path: {$path}");
                            if ($path && file_exists($path) && is_executable($path)) {
                                $chromePath = $path;
                                Log::info("PDF Generation: Chrome path found: {$chromePath}");
                                break;
                            }
                        }

                        if (! $chromePath) {
                            Log::error('PDF Generation: No Chrome path found!', [
                                'tested_paths' => $chromePaths,
                            ]);
                            throw new \Exception('Chrome/Chromium browser not found for PDF generation');
                        }

                        Log::info('PDF Generation: Configuring Browsershot', [
                            'chrome_path' => $chromePath,
                            'format' => 'A4',
                            'margins' => '20x20x20x20',
                            'timeout' => 120,
                        ]);

                        $browsershot->setChromePath($chromePath)
                            ->format('A4')
                            ->margins(20, 20, 20, 20) // Professional academic margins
                            ->showBackground()
                            ->setTemporaryDirectory(storage_path('app/browsershot')) // ⭐ THIS FIX
                            ->waitUntilNetworkIdle() // Wait for all resources to load
                            ->userAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36')
                            ->deviceScaleFactor(1.5) // Higher resolution for better quality
                            ->timeout(120)
                            ->showBrowserHeaderAndFooter()
                            ->hideHeader()
                            // ->footerHtml('<div style="text-align: center; font-size: 10px; color: #6b7280; font-family: Times New Roman, serif; padding: 8px 0; width: 100%; display: block;">Generated by Finalyze AI Academic Assistant | '.now()->format('F j, Y \a\t g:i A').'</div>')
                            ->noSandbox()
                            ->setOption('disable-web-security', true)
                            ->setOption('allow-running-insecure-content', true);
                    });

                Log::info('PDF Generation: Starting PDF creation', [
                    'view' => 'pdf.topic-proposal',
                    'output_path' => $filePath,
                    'project_data' => [
                        'id' => $project->id,
                        'slug' => $project->slug,
                        'has_title' => ! empty($project->title),
                        'has_topic' => ! empty($project->topic),
                        'has_user' => ! empty($project->user),
                        'has_category' => ! empty($project->category),
                    ],
                ]);

                $pdf->save($filePath);

                Log::info('PDF Generation: Save operation completed', [
                    'file_path' => $filePath,
                    'file_exists' => File::exists($filePath),
                ]);

                if (! File::exists($filePath)) {
                    Log::error('PDF Generation: File was not created', [
                        'expected_path' => $filePath,
                        'directory_exists' => File::exists(dirname($filePath)),
                        'directory_writable' => is_writable(dirname($filePath)),
                    ]);
                    throw new \Exception("PDF file was not created at: {$filePath}");
                }

                // Validate PDF file format
                $fileSize = File::size($filePath);
                $fileHeader = file_get_contents($filePath, false, null, 0, 4);

                Log::info('PDF Generation: File validation', [
                    'file_size' => $fileSize,
                    'file_header' => bin2hex($fileHeader),
                    'is_valid_pdf' => $fileHeader === '%PDF',
                ]);

                if ($fileHeader !== '%PDF') {
                    Log::error('PDF Generation: Invalid PDF format detected', [
                        'file_path' => $filePath,
                        'file_size' => $fileSize,
                        'header_hex' => bin2hex($fileHeader),
                        'first_100_chars' => substr(file_get_contents($filePath), 0, 100),
                    ]);
                }

                // Log successful PDF generation
                $executionTime = round((microtime(true) - $startTime) * 1000, 2);
                Log::info('PDF Topic Proposal Generated Successfully', [
                    'file' => $filePath,
                    'execution_time_ms' => $executionTime,
                ]);

                // Log download preparation
                Log::info('PDF Export: Preparing download response', [
                    'filename' => $fileName,
                    'content_type' => 'application/pdf',
                    'file_size' => File::size($filePath),
                    'project_id' => $project->id,
                    'project_slug' => $project->slug,
                    'user_id' => auth()->id(),
                    'execution_time_ms' => $executionTime,
                    'delete_after_send' => true,
                ]);

                // Read file contents and delete explicitly for maximum reliability
                $content = File::get($filePath);
                $size = File::size($filePath);
                File::delete($filePath);

                Log::info('PDF Export: Sending response from memory', [
                    'filename' => $fileName,
                    'file_size' => $size,
                ]);

                return response($content, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
                    'Content-Length' => $size,
                    'Cache-Control' => 'no-cache, no-store, must-revalidate',
                    'Pragma' => 'no-cache',
                    'Expires' => '0',
                ]);

            } catch (\Exception $e) {
                Log::error('PDF Generation Error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    // 'project_id' => $project->id,
                    'file_path' => $filePath ?? 'not_set',
                ]);

                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Topic Proposal PDF Export Failed', [
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
}
