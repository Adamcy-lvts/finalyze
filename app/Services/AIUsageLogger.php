<?php

namespace App\Services;

use App\Models\AIUsageLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AIUsageLogger
{
    /**
     * Log a single AI call and roll up the daily aggregate.
     */
    public function log(
        ?int $userId,
        ?string $feature,
        ?string $model,
        int $promptTokens,
        int $completionTokens,
        ?string $requestId = null,
        array $metadata = []
    ): AIUsageLog {
        $totalTokens = $promptTokens + $completionTokens;
        $costUsd = $this->estimateCost($model, $promptTokens, $completionTokens);

        $log = AIUsageLog::create([
            'user_id' => $userId,
            'feature' => $feature,
            'model' => $model,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
            'cost_usd' => $costUsd,
            'request_id' => $requestId,
            'metadata' => $metadata ?: null,
        ]);

        $this->rollupDaily($model, $promptTokens, $completionTokens, $costUsd);

        return $log;
    }

    /**
     * Estimate cost using model pricing.
     */
    private function estimateCost(?string $model, int $promptTokens, int $completionTokens): float
    {
        $pricing = config('ai.model_pricing.'.($model ?? ''), null);

        if (! $pricing) {
            return 0;
        }

        $promptCost = ($promptTokens / 1000) * ($pricing['prompt'] ?? 0);
        $completionCost = ($completionTokens / 1000) * ($pricing['completion'] ?? 0);

        return round($promptCost + $completionCost, 6);
    }

    /**
     * Roll up to the daily aggregate.
     */
    private function rollupDaily(?string $model, int $promptTokens, int $completionTokens, float $costUsd): void
    {
        $date = Carbon::today();
        $totalTokens = $promptTokens + $completionTokens;

        DB::table('ai_usage_daily')->updateOrInsert(
            ['date' => $date->toDateString(), 'model' => $model],
            [
                'prompt_tokens' => DB::raw("prompt_tokens + {$promptTokens}"),
                'completion_tokens' => DB::raw("completion_tokens + {$completionTokens}"),
                'total_tokens' => DB::raw("total_tokens + {$totalTokens}"),
                'cost_usd' => DB::raw("cost_usd + {$costUsd}"),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
