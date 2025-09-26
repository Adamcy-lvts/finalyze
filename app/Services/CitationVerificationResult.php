<?php

namespace App\Services;

use App\Models\Citation;

class CitationVerificationResult
{
    public function __construct(
        public bool $success,
        public ?Citation $citation = null,
        public float $confidence = 0.0,
        public string $source = '',
        public array $suggestions = [],
        public array $errors = [],
        public array $apiResponses = [],
        public int $processingTimeMs = 0
    ) {}

    public static function success(
        Citation $citation,
        float $confidence,
        string $source,
        array $apiResponses = [],
        int $processingTimeMs = 0
    ): self {
        return new self(
            success: true,
            citation: $citation,
            confidence: $confidence,
            source: $source,
            apiResponses: $apiResponses,
            processingTimeMs: $processingTimeMs
        );
    }

    public static function failed(
        array $suggestions = [],
        array $errors = [],
        array $apiResponses = [],
        int $processingTimeMs = 0
    ): self {
        return new self(
            success: false,
            suggestions: $suggestions,
            errors: $errors,
            apiResponses: $apiResponses,
            processingTimeMs: $processingTimeMs
        );
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'citation' => $this->citation?->toArray(),
            'confidence' => $this->confidence,
            'source' => $this->source,
            'suggestions' => $this->suggestions,
            'errors' => $this->errors,
            'api_responses' => $this->apiResponses,
            'processing_time_ms' => $this->processingTimeMs,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            success: $data['success'],
            citation: $data['citation'] ? Citation::make($data['citation']) : null,
            confidence: $data['confidence'] ?? 0.0,
            source: $data['source'] ?? '',
            suggestions: $data['suggestions'] ?? [],
            errors: $data['errors'] ?? [],
            apiResponses: $data['api_responses'] ?? [],
            processingTimeMs: $data['processing_time_ms'] ?? 0
        );
    }
}
