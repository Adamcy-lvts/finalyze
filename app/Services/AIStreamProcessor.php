<?php

namespace App\Services;

use App\Events\CitationDetected;
use App\Events\CitationFailed;
use App\Events\CitationVerified;
use Illuminate\Support\Facades\Log;

class AIStreamProcessor
{
    private string $buffer = '';

    private array $detectedCitations = [];

    private array $verificationQueue = [];

    /**
     * Process streaming AI response with citation detection
     */
    public function processStream($generator, string $sessionId)
    {
        foreach ($generator as $chunk) {
            $this->buffer .= $chunk;

            // Detect complete sentences for citation checking
            if ($this->hasCompleteSentence()) {
                $sentences = $this->extractCompleteSentences();

                foreach ($sentences as $sentence) {
                    $citations = $this->detectCitationsInSentence($sentence);

                    if (! empty($citations)) {
                        foreach ($citations as $citation) {
                            $this->queueForVerification($citation, $sessionId);
                        }
                    }

                    // Yield the processed sentence
                    yield $this->processedSentence($sentence);
                }
            } else {
                // Yield partial content
                yield $chunk;
            }
        }

        // Process any remaining buffer
        if (! empty($this->buffer)) {
            yield $this->processedSentence($this->buffer);
        }

        // Final verification pass
        $this->finalizeVerifications($sessionId);
    }

    /**
     * Check if buffer contains a complete sentence
     */
    private function hasCompleteSentence(): bool
    {
        return preg_match('/[.!?]\s/', $this->buffer) === 1;
    }

    /**
     * Extract complete sentences from buffer
     */
    private function extractCompleteSentences(): array
    {
        $sentences = [];

        while (preg_match('/^(.*?[.!?]\s+)(.*)$/s', $this->buffer, $matches)) {
            $sentences[] = $matches[1];
            $this->buffer = $matches[2];
        }

        return $sentences;
    }

    /**
     * Detect citations in a sentence using various patterns
     */
    private function detectCitationsInSentence(string $sentence): array
    {
        $citations = [];

        // Multiple patterns for different citation styles
        $patterns = [
            '/\(([A-Za-z\s&]+(?:et al\.)?),?\s*(\d{4})\)/',  // (Author, 2020)
            '/\[(\d+)\]/',                                      // [1]
            '/\(([A-Za-z\s&]+)\s+(\d{4}):\s*(\d+)\)/',        // (Author 2020: 45)
            '/\[CITE:[^\]]+\]/',                               // [CITE: structured citation]
            '/\[UNVERIFIED_CITE:[^\]]+\]/',                   // [UNVERIFIED_CITE: ...]
            '/\[CITATION_NEEDED:[^\]]+\]/',                   // [CITATION_NEEDED: ...]
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $sentence, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $citations[] = [
                        'id' => uniqid('cite_'),
                        'raw' => $match[0],
                        'parsed' => $this->parseInlineCitation($match),
                        'position' => strpos($sentence, $match[0]),
                        'status' => 'pending',
                    ];
                }
            }
        }

        return $citations;
    }

    /**
     * Parse an inline citation match into structured data
     */
    private function parseInlineCitation(array $match): array
    {
        $raw = $match[0];

        // Handle structured citation formats
        if (strpos($raw, '[CITE:') === 0) {
            return $this->parseStructuredCitation($raw);
        }

        if (strpos($raw, '[UNVERIFIED_CITE:') === 0) {
            return ['type' => 'unverified', 'raw' => $raw];
        }

        if (strpos($raw, '[CITATION_NEEDED:') === 0) {
            return ['type' => 'needed', 'raw' => $raw];
        }

        // Handle regular citation patterns
        if (preg_match('/\(([A-Za-z\s&]+(?:et al\.)?),?\s*(\d{4})\)/', $raw, $matches)) {
            return [
                'type' => 'author_year',
                'author' => trim($matches[1]),
                'year' => intval($matches[2]),
                'raw' => $raw,
            ];
        }

        if (preg_match('/\[(\d+)\]/', $raw, $matches)) {
            return [
                'type' => 'numbered',
                'number' => intval($matches[1]),
                'raw' => $raw,
            ];
        }

        return ['type' => 'unknown', 'raw' => $raw];
    }

    /**
     * Parse structured citation format like [CITE: Author, Year, "Title"]
     */
    private function parseStructuredCitation(string $citation): array
    {
        if (preg_match('/\[CITE:\s*(.+)\]/', $citation, $matches)) {
            $content = $matches[1];
            $parts = str_getcsv($content);

            return [
                'type' => 'structured',
                'author' => trim($parts[0] ?? ''),
                'year' => intval($parts[1] ?? 0),
                'title' => trim($parts[2] ?? '', '"'),
                'doi' => $parts[3] ?? null,
                'raw' => $citation,
            ];
        }

        return ['type' => 'structured', 'raw' => $citation];
    }

    /**
     * Queue citation for verification
     */
    private function queueForVerification(array $citation, string $sessionId): void
    {
        $this->detectedCitations[] = $citation;

        $this->verificationQueue[] = [
            'citation' => $citation,
            'session_id' => $sessionId,
            'queued_at' => now(),
        ];

        // Broadcast event if events exist
        try {
            if (class_exists(CitationDetected::class)) {
                broadcast(new CitationDetected($citation, $sessionId));
            }
        } catch (\Exception $e) {
            Log::debug('Citation event broadcast failed (events may not be configured)', [
                'error' => $e->getMessage(),
            ]);
        }

        // Start async verification if queue is getting full
        if (count($this->verificationQueue) >= 5) {
            $this->processBatchVerification();
        }
    }

    /**
     * Process batch of citations for verification
     */
    private function processBatchVerification(): void
    {
        $batch = array_splice($this->verificationQueue, 0, 5);

        try {
            // Dispatch to queue for parallel processing
            dispatch(function () use ($batch) {
                foreach ($batch as $item) {
                    try {
                        $result = app(CitationService::class)->verifyCitation(
                            $item['citation']['raw'],
                            ['session_id' => $item['session_id']]
                        );

                        // Update citation status
                        $this->updateCitationStatus($item['citation']['id'],
                            $result['verified'] ? 'verified' : 'failed');

                        // Broadcast verification result
                        if ($result['verified']) {
                            if (class_exists(CitationVerified::class)) {
                                broadcast(new CitationVerified($item['citation'], $result));
                            }
                        } else {
                            if (class_exists(CitationFailed::class)) {
                                broadcast(new CitationFailed($item['citation'], $result));
                            }
                        }

                    } catch (\Exception $e) {
                        Log::error('Citation verification failed in batch processing', [
                            'citation' => $item['citation'],
                            'error' => $e->getMessage(),
                        ]);

                        $this->updateCitationStatus($item['citation']['id'], 'failed');
                    }
                }
            })->onQueue('citations');

        } catch (\Exception $e) {
            Log::error('Failed to dispatch citation verification batch', [
                'error' => $e->getMessage(),
                'batch_size' => count($batch),
            ]);
        }
    }

    /**
     * Update citation status in detected citations array
     */
    private function updateCitationStatus(string $citationId, string $status): void
    {
        foreach ($this->detectedCitations as &$citation) {
            if ($citation['id'] === $citationId) {
                $citation['status'] = $status;
                break;
            }
        }
    }

    /**
     * Process sentence with citation markup
     */
    private function processedSentence(string $sentence): string
    {
        // Add HTML markers for detected citations
        foreach ($this->detectedCitations as $citation) {
            if (strpos($sentence, $citation['raw']) !== false) {
                $replacement = sprintf(
                    '<span class="citation-marker" data-status="%s" data-id="%s">%s</span>',
                    $citation['status'] ?? 'pending',
                    $citation['id'],
                    $citation['raw']
                );
                $sentence = str_replace($citation['raw'], $replacement, $sentence);
            }
        }

        return $sentence;
    }

    /**
     * Finalize verifications and prepare review data
     */
    private function finalizeVerifications(string $sessionId): void
    {
        // Process any remaining citations in queue
        if (! empty($this->verificationQueue)) {
            $this->processBatchVerification();
        }

        // Generate post-generation review data
        $unverifiedCitations = array_filter($this->detectedCitations, function ($citation) {
            return ($citation['status'] ?? 'pending') !== 'verified';
        });

        if (! empty($unverifiedCitations)) {
            session()->flash('unverified_citations', $unverifiedCitations);
            session()->flash('show_citation_review', true);

            Log::info('Citations requiring review detected', [
                'session_id' => $sessionId,
                'unverified_count' => count($unverifiedCitations),
            ]);
        }
    }

    /**
     * Get detected citations
     */
    public function getDetectedCitations(): array
    {
        return $this->detectedCitations;
    }

    /**
     * Get verification queue
     */
    public function getVerificationQueue(): array
    {
        return $this->verificationQueue;
    }

    /**
     * Reset processor state
     */
    public function reset(): void
    {
        $this->buffer = '';
        $this->detectedCitations = [];
        $this->verificationQueue = [];
    }
}
