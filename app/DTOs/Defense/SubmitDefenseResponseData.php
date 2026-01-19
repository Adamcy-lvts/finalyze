<?php

namespace App\DTOs\Defense;

class SubmitDefenseResponseData
{
    public function __construct(
        public string $response,
        public ?int $responseTimeMs
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            $payload['response'],
            $payload['response_time_ms'] ?? null
        );
    }
}
