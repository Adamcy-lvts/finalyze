<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentAnalysisService
{
    /**
     * Supported file types for analysis
     */
    const SUPPORTED_TYPES = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'txt' => 'text/plain',
        'rtf' => 'application/rtf',
        'md' => 'text/markdown',
    ];

    /**
     * Maximum file size in bytes (10MB)
     */
    const MAX_FILE_SIZE = 10 * 1024 * 1024;

    public function __construct(
        private AIContentGenerator $aiGenerator
    ) {}

    /**
     * Validate and process uploaded file for chat analysis
     */
    public function processUploadedFile(UploadedFile $file, int $userId, int $projectId): array
    {
        // Validate file
        $this->validateFile($file);

        // Store file securely
        $storedPath = $this->storeFile($file, $userId, $projectId);

        // Extract text content
        $textContent = $this->extractTextFromFile($file, $storedPath);

        // Analyze document
        $analysis = $this->analyzeDocument($textContent, $file->getClientOriginalName());

        return [
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'stored_path' => $storedPath,
            'text_content' => $textContent,
            'analysis' => $analysis,
            'upload_id' => Str::uuid()->toString(),
        ];
    }

    /**
     * Validate uploaded file meets requirements
     */
    private function validateFile(UploadedFile $file): void
    {
        // Check file size
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException('File size exceeds maximum limit of 10MB');
        }

        // Check file type
        $mimeType = $file->getMimeType();
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($mimeType, self::SUPPORTED_TYPES) && ! array_key_exists($extension, self::SUPPORTED_TYPES)) {
            throw new \InvalidArgumentException('Unsupported file type. Please upload PDF, DOC, DOCX, TXT, RTF, or MD files.');
        }

        // Check for malicious files
        if ($this->isFilemalicious($file)) {
            throw new \InvalidArgumentException('File appears to be malicious and cannot be processed');
        }
    }

    /**
     * Store file securely in project-specific directory
     */
    private function storeFile(UploadedFile $file, int $userId, int $projectId): string
    {
        $fileName = time().'_'.Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'.'.$file->getClientOriginalExtension();
        $path = "chat-uploads/user-{$userId}/project-{$projectId}/{$fileName}";

        Storage::disk('private')->put($path, file_get_contents($file->getRealPath()));

        return $path;
    }

    /**
     * Extract text content from various file types
     */
    private function extractTextFromFile(UploadedFile $file, string $storedPath): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $content = '';

        try {
            switch ($extension) {
                case 'pdf':
                    $content = $this->extractFromPDF($storedPath);
                    break;

                case 'doc':
                case 'docx':
                    $content = $this->extractFromWord($storedPath);
                    break;

                case 'txt':
                case 'md':
                case 'rtf':
                    $content = Storage::disk('private')->get($storedPath);
                    break;

                default:
                    throw new \InvalidArgumentException("Unsupported file type: {$extension}");
            }

            // Clean and validate extracted content
            $content = $this->cleanExtractedText($content);

            if (empty(trim($content))) {
                throw new \RuntimeException('No readable text found in the document');
            }

            return $content;

        } catch (\Exception $e) {
            Log::error('Text extraction failed', [
                'file' => $file->getClientOriginalName(),
                'path' => $storedPath,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Failed to extract text from document: '.$e->getMessage());
        }
    }

    /**
     * Extract text from PDF using available tools
     */
    private function extractFromPDF(string $path): string
    {
        $fullPath = Storage::disk('private')->path($path);

        // Try using pdftotext if available
        if ($this->commandExists('pdftotext')) {
            $output = shell_exec("pdftotext '{$fullPath}' -");
            if (! empty($output)) {
                return $output;
            }
        }

        // Fallback: Basic PDF text extraction (limited functionality)
        $content = file_get_contents($fullPath);

        // Simple PDF text extraction - this is basic and may not work for all PDFs
        if (preg_match_all('/\(([^)]+)\)/', $content, $matches)) {
            return implode(' ', $matches[1]);
        }

        throw new \RuntimeException('PDF text extraction requires pdftotext to be installed on the server');
    }

    /**
     * Extract text from Word documents
     */
    private function extractFromWord(string $path): string
    {
        $fullPath = Storage::disk('private')->path($path);
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        if ($extension === 'docx') {
            return $this->extractFromDocx($fullPath);
        } else {
            // For older .doc files, we'd need additional tools
            throw new \RuntimeException('Legacy .doc files are not supported. Please convert to .docx format.');
        }
    }

    /**
     * Extract text from DOCX files
     */
    private function extractFromDocx(string $path): string
    {
        if (! class_exists('ZipArchive')) {
            throw new \RuntimeException('PHP ZipArchive extension required for DOCX processing');
        }

        $zip = new \ZipArchive;
        $content = '';

        if ($zip->open($path) === true) {
            $xml = $zip->getFromName('word/document.xml');
            if ($xml !== false) {
                $content = strip_tags($xml);
                $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
            }
            $zip->close();
        }

        if (empty($content)) {
            throw new \RuntimeException('Failed to extract text from DOCX file');
        }

        return $content;
    }

    /**
     * Clean extracted text content
     */
    private function cleanExtractedText(string $content): string
    {
        // Remove excessive whitespace
        $content = preg_replace('/\s+/', ' ', $content);

        // Remove control characters
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content);

        // Trim
        $content = trim($content);

        return $content;
    }

    /**
     * Analyze document content using AI
     */
    private function analyzeDocument(string $content, string $fileName): array
    {
        $prompt = "Analyze this academic document and provide insights:

DOCUMENT: {$fileName}

CONTENT:
{$content}

Please provide:
1. Document type (research paper, thesis chapter, proposal, etc.)
2. Main topics and themes
3. Key arguments or findings
4. Academic quality assessment
5. Citations and references found
6. Potential areas for improvement
7. Relevance to academic research

Format your response as a structured analysis.";

        try {
            $analysis = $this->aiGenerator->generate($prompt, [
                'model' => 'gpt-4o-mini',
                'temperature' => 0.3,
                'max_tokens' => 2000,
            ]);

            return [
                'ai_analysis' => $analysis,
                'word_count' => str_word_count($content),
                'character_count' => strlen($content),
                'estimated_pages' => ceil(str_word_count($content) / 250), // Rough estimate
                'main_topics' => $this->extractMainTopics($content),
                'citations_found' => $this->countCitations($content),
            ];

        } catch (\Exception $e) {
            Log::error('Document analysis failed', [
                'file' => $fileName,
                'error' => $e->getMessage(),
            ]);

            return [
                'ai_analysis' => 'Analysis temporarily unavailable',
                'word_count' => str_word_count($content),
                'character_count' => strlen($content),
                'estimated_pages' => ceil(str_word_count($content) / 250),
                'main_topics' => [],
                'citations_found' => 0,
            ];
        }
    }

    /**
     * Extract main topics using simple keyword analysis
     */
    private function extractMainTopics(string $content): array
    {
        // Simple topic extraction - could be enhanced with NLP
        $words = str_word_count(strtolower($content), 1);
        $stopWords = ['the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'];
        $words = array_filter($words, fn ($word) => ! in_array($word, $stopWords) && strlen($word) > 3);

        $wordCounts = array_count_values($words);
        arsort($wordCounts);

        return array_slice(array_keys($wordCounts), 0, 10);
    }

    /**
     * Count potential citations in document
     */
    private function countCitations(string $content): int
    {
        $citationPatterns = [
            '/\([A-Za-z]+,?\s+\d{4}\)/',  // (Author, 2023)
            '/\([A-Za-z]+\s+et\s+al\.?,?\s+\d{4}\)/', // (Author et al., 2023)
            '/\[[0-9]+\]/', // [1]
            '/\b[A-Za-z]+\s+\(\d{4}\)/', // Author (2023)
        ];

        $totalCitations = 0;
        foreach ($citationPatterns as $pattern) {
            $totalCitations += preg_match_all($pattern, $content);
        }

        return $totalCitations;
    }

    /**
     * Check if command exists on system
     */
    private function commandExists(string $command): bool
    {
        $which = shell_exec("which {$command}");

        return ! empty($which);
    }

    /**
     * Basic malicious file detection
     */
    private function isFilemalicious(UploadedFile $file): bool
    {
        // Check for suspicious extensions
        $suspiciousExtensions = ['exe', 'bat', 'cmd', 'scr', 'pif', 'vbs', 'js'];
        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, $suspiciousExtensions)) {
            return true;
        }

        // Check file content for malicious patterns (basic check)
        $content = file_get_contents($file->getRealPath(), false, null, 0, 1024);
        $maliciousPatterns = ['<script', '<?php', '#!/bin/bash', 'eval('];

        foreach ($maliciousPatterns as $pattern) {
            if (stripos($content, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get file content for chat context
     */
    public function getFileContent(string $storedPath): string
    {
        return Storage::disk('private')->get($storedPath);
    }

    /**
     * Delete uploaded file
     */
    public function deleteFile(string $storedPath): bool
    {
        return Storage::disk('private')->delete($storedPath);
    }
}
