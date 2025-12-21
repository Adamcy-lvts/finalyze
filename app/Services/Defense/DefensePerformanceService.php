<?php

namespace App\Services\Defense;

use App\Models\DefenseSession;

class DefensePerformanceService
{
    public function calculatePerformanceMetrics(DefenseSession $session): array
    {
        $session->loadMissing('messages');
        $responses = $session->messages->where('role', 'student');

        $clarityScores = [];
        $depthScores = [];
        $responseTimes = [];

        foreach ($responses as $response) {
            $feedback = $response->ai_feedback ?? [];
            if (isset($feedback['clarity'])) {
                $clarityScores[] = (int) $feedback['clarity'];
            }
            if (isset($feedback['technical_depth'])) {
                $depthScores[] = (int) $feedback['technical_depth'];
            }
            if ($response->response_time_ms) {
                $responseTimes[] = (int) $response->response_time_ms;
            }
        }

        $clarity = $this->average($clarityScores);
        $technicalDepth = $this->average($depthScores);
        $responseTimeSec = $this->average($responseTimes) / 1000;

        $questionsAsked = max(1, (int) $session->questions_asked);
        $questionCoverage = (int) round((count($responses) / $questionsAsked) * 100);
        $confidenceScore = (int) round(($clarity + $technicalDepth) / 2);

        $readinessScore = (int) round(($clarity + $technicalDepth) / 2);

        return [
            'clarity' => $clarity,
            'technical_depth' => $technicalDepth,
            'response_time' => $responseTimeSec,
            'question_coverage' => $questionCoverage,
            'confidence_score' => $confidenceScore,
            'readiness_score' => $readinessScore,
        ];
    }

    private function average(array $values): int
    {
        $values = array_filter($values, fn ($value) => is_numeric($value));
        if (count($values) === 0) {
            return 0;
        }

        return (int) round(array_sum($values) / count($values));
    }
}
