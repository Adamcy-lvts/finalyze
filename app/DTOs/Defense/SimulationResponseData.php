<?php

namespace App\DTOs\Defense;

use App\Models\DefenseMessage;

class SimulationResponseData
{
    /**
     * @param array<string, mixed> $evaluation
     * @param array<string, mixed> $metrics
     */
    public function __construct(
        public DefenseMessage $message,
        public array $evaluation,
        public array $metrics
    ) {
    }
}
