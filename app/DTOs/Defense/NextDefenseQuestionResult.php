<?php

namespace App\DTOs\Defense;

use App\Models\DefenseMessage;
use App\Models\DefenseSession;

class NextDefenseQuestionResult
{
    public function __construct(
        public DefenseMessage $message,
        public DefenseSession $session
    ) {
    }
}
