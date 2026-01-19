<?php

namespace App\DTOs\Defense;

class StartDefenseSessionData
{
    /**
     * @param string[] $selectedPanelists
     */
    public function __construct(
        public array $selectedPanelists,
        public ?string $difficultyLevel,
        public ?int $timeLimitMinutes,
        public ?int $questionLimit
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            $payload['selected_panelists'] ?? [],
            $payload['difficulty_level'] ?? null,
            $payload['time_limit_minutes'] ?? null,
            $payload['question_limit'] ?? null
        );
    }
}
