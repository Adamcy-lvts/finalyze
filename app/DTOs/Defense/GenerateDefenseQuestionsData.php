<?php

namespace App\DTOs\Defense;

class GenerateDefenseQuestionsData
{
    public function __construct(
        public ?int $chapterNumber,
        public int $count
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            isset($payload['chapter_number']) ? (int) $payload['chapter_number'] : null,
            isset($payload['count']) ? (int) $payload['count'] : 5
        );
    }
}
