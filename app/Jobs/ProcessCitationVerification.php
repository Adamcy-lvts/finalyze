<?php

namespace App\Jobs;

use App\Models\Chapter;
use App\Services\CitationService;
use App\Services\ReferenceVerificationService;
use App\Services\SimpleCitationExtractor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessCitationVerification implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes timeout

    public int $tries = 1; // Don't retry failed jobs

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Chapter $chapter,
        public string $sessionId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        ReferenceVerificationService $referenceService,
        SimpleCitationExtractor $extractor,
        CitationService $citationService
    ): void {
        Log::info('=== STARTING ASYNC CITATION VERIFICATION JOB ===', [
            'chapter_id' => $this->chapter->id,
            'session_id' => $this->sessionId,
        ]);

        try {
            $content = $this->chapter->content ?? '';

            // Initialize progress tracking
            $this->updateProgress(0, 'Starting citation verification...');

            if (empty($content)) {
                $this->completeWithNoContent();

                return;
            }

            $this->updateProgress(10, 'Analyzing content...');

            // Step 1: Try reference-based verification
            Log::info('Attempting reference-based verification', [
                'chapter_id' => $this->chapter->id,
                'session_id' => $this->sessionId,
            ]);

            $this->updateProgress(20, 'Parsing references section...');
            $referenceResults = $referenceService->verifyReferences($content);

            if ($referenceResults['success'] && ! empty($referenceResults['references'])) {
                Log::info('Reference-based verification successful', [
                    'chapter_id' => $this->chapter->id,
                    'session_id' => $this->sessionId,
                    'references_found' => $referenceResults['summary']['total'],
                    'verified' => $referenceResults['summary']['verified'],
                    'success_rate' => $referenceResults['summary']['success_rate'],
                ]);

                $this->updateProgress(100, 'Verification completed');
                $this->completeWithResults([
                    'success' => true,
                    'method' => 'reference_parsing',
                    'references' => $referenceResults['references'],
                    'citations' => [], // No inline citations for reference method
                    'summary' => $referenceResults['summary'],
                    'processing_time_ms' => $referenceResults['processing_time_ms'],
                    'message' => "Verified {$referenceResults['summary']['verified']} out of {$referenceResults['summary']['total']} references using bibliography parsing",
                    'session_id' => $this->sessionId,
                ]);

                return;
            }

            // Step 2: Fallback to inline citation verification
            Log::info('Reference-based verification failed, falling back to inline citation extraction', [
                'chapter_id' => $this->chapter->id,
                'session_id' => $this->sessionId,
            ]);

            $this->updateProgress(30, 'Extracting inline citations...');

            $extractedCitations = $extractor->extractCitations($content);
            $references = $extractor->extractReferences($content);

            Log::info('Citation extraction completed', [
                'chapter_id' => $this->chapter->id,
                'session_id' => $this->sessionId,
                'citations_count' => count($extractedCitations),
                'references_count' => count($references),
            ]);

            if (empty($extractedCitations)) {
                $this->completeWithNoCitations();

                return;
            }

            // Step 3: Verify each citation
            $verifiedCitations = [];
            $totalCitations = count($extractedCitations);

            $this->updateProgress(40, "Verifying {$totalCitations} citations...");

            foreach ($extractedCitations as $index => $citation) {
                $progress = 40 + (($index + 1) / $totalCitations) * 50; // 40-90% range
                $this->updateProgress(
                    (int) $progress,
                    'Verifying citation '.($index + 1)." of {$totalCitations}..."
                );

                try {
                    $citationText = $citation['text'] ?? '';
                    if (! empty($citation['author']) && ! empty($citation['year'])) {
                        $citationText = "{$citation['author']} ({$citation['year']})";
                    }

                    Log::info('Verifying citation', [
                        'chapter_id' => $this->chapter->id,
                        'session_id' => $this->sessionId,
                        'citation_text' => $citationText,
                        'citation_id' => $citation['id'],
                        'progress' => $index + 1,
                        'total' => $totalCitations,
                    ]);

                    $verificationResult = $citationService->verifyCitation($citationText, [
                        'session_id' => $this->sessionId,
                    ]);

                    $verifiedCitation = $citation;
                    if ($verificationResult->success) {
                        $verifiedCitation['status'] = 'verified';
                        $verifiedCitation['confidence'] = $verificationResult->confidence;
                        $verifiedCitation['source'] = $verificationResult->source;
                        $verifiedCitation['details'] = [
                            'title' => $verificationResult->citation->title ?? '',
                            'journal' => $verificationResult->citation->journal ?? '',
                            'doi' => $verificationResult->citation->doi ?? '',
                            'url' => $verificationResult->citation->url ?? '',
                        ];
                    } else {
                        $verifiedCitation['status'] = 'failed';
                        $verifiedCitation['confidence'] = 0;
                        $verifiedCitation['suggestions'] = $verificationResult->suggestions;
                        $verifiedCitation['errors'] = $verificationResult->errors;
                    }

                    $verifiedCitations[] = $verifiedCitation;

                } catch (\Exception $e) {
                    Log::error('Citation verification failed', [
                        'chapter_id' => $this->chapter->id,
                        'session_id' => $this->sessionId,
                        'citation_id' => $citation['id'],
                        'error' => $e->getMessage(),
                    ]);

                    $verifiedCitation = $citation;
                    $verifiedCitation['status'] = 'failed';
                    $verifiedCitation['confidence'] = 0;
                    $verifiedCitation['errors'] = ['Verification error: '.$e->getMessage()];
                    $verifiedCitations[] = $verifiedCitation;
                }
            }

            $this->updateProgress(95, 'Generating final summary...');

            $summary = $this->generateVerificationSummary($verifiedCitations);

            Log::info('Citation verification process completed', [
                'chapter_id' => $this->chapter->id,
                'session_id' => $this->sessionId,
                'total_citations' => count($verifiedCitations),
                'summary' => $summary,
            ]);

            $this->updateProgress(100, 'Verification completed');

            $this->completeWithResults([
                'success' => true,
                'method' => 'inline_citations',
                'citations' => $verifiedCitations,
                'references' => $references,
                'summary' => $summary,
                'message' => $summary['total'] > 0
                    ? "Verified {$summary['total']} citations using external academic APIs"
                    : 'No citations found in chapter content',
                'session_id' => $this->sessionId,
            ]);

        } catch (\Exception $e) {
            Log::error('Citation verification job failed', [
                'chapter_id' => $this->chapter->id,
                'session_id' => $this->sessionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->updateProgress(100, 'Verification failed');
            $this->completeWithError($e->getMessage());
        }
    }

    private function updateProgress(int $percentage, string $message): void
    {
        $progressKey = "citation_verification_progress_{$this->sessionId}";

        Cache::put($progressKey, [
            'percentage' => $percentage,
            'message' => $message,
            'completed' => $percentage >= 100,
            'timestamp' => now()->toISOString(),
        ], 300); // Cache for 5 minutes

        Log::info('Progress updated', [
            'session_id' => $this->sessionId,
            'percentage' => $percentage,
            'message' => $message,
        ]);
    }

    private function completeWithNoContent(): void
    {
        $this->completeWithResults([
            'success' => true,
            'citations' => [],
            'references' => [],
            'summary' => [
                'total' => 0,
                'verified' => 0,
                'failed' => 0,
                'unverified' => 0,
                'pending' => 0,
            ],
            'message' => 'No content found to verify',
            'session_id' => $this->sessionId,
        ]);
    }

    private function completeWithNoCitations(): void
    {
        $this->completeWithResults([
            'success' => true,
            'citations' => [],
            'references' => [],
            'summary' => [
                'total' => 0,
                'verified' => 0,
                'failed' => 0,
                'unverified' => 0,
                'pending' => 0,
            ],
            'message' => 'No citations found in chapter content',
            'session_id' => $this->sessionId,
        ]);
    }

    private function completeWithResults(array $results): void
    {
        $resultKey = "citation_verification_result_{$this->sessionId}";
        Cache::put($resultKey, $results, 300); // Cache for 5 minutes
    }

    private function completeWithError(string $error): void
    {
        $resultKey = "citation_verification_result_{$this->sessionId}";
        Cache::put($resultKey, [
            'success' => false,
            'message' => 'Failed to verify citations: '.$error,
            'session_id' => $this->sessionId,
        ], 300);
    }

    private function generateVerificationSummary(array $citations): array
    {
        $total = count($citations);
        $statusCounts = array_count_values(array_column($citations, 'status'));

        return [
            'total' => $total,
            'verified' => $statusCounts['verified'] ?? 0,
            'failed' => $statusCounts['failed'] ?? 0,
            'pending' => $statusCounts['pending'] ?? 0,
            'unverified' => $statusCounts['unverified'] ?? 0,
        ];
    }
}
