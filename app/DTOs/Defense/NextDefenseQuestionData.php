<?php

namespace App\DTOs\Defense;

class NextDefenseQuestionData
{
    public function __construct(
        public ?string $persona,
        public bool $requestHint
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            $payload['persona'] ?? null,
            (bool) ($payload['request_hint'] ?? false)
        );
    }
}
