<?php

namespace App\DTOs\Defense;

class DefenseQuestionsQueryData
{
    public function __construct(
        public ?int $chapterNumber,
        public int $limit,
        public bool $forceRefresh,
        public ?string $difficulty,
        public bool $skipGeneration
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            isset($payload['chapter_number']) ? (int) $payload['chapter_number'] : null,
            isset($payload['limit']) ? (int) $payload['limit'] : 5,
            (bool) ($payload['force_refresh'] ?? false),
            $payload['difficulty'] ?? null,
            (bool) ($payload['skip_generation'] ?? false)
        );
    }
}
