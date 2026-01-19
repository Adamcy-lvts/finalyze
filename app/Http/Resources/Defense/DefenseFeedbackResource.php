<?php

namespace App\Http\Resources\Defense;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property int $session_id
 * @property int|null $overall_score
 * @property array|null $strengths
 * @property array|null $weaknesses
 * @property string|null $recommendations
 * @property \Illuminate\Support\Carbon|string|null $generated_at
 */
class DefenseFeedbackResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'session_id' => $this->session_id,
            'overall_score' => $this->overall_score,
            'strengths' => $this->strengths,
            'weaknesses' => $this->weaknesses,
            'recommendations' => $this->recommendations,
            'generated_at' => $this->generated_at,
        ];
    }
}
