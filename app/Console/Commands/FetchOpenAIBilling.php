<?php

namespace App\Console\Commands;

use App\Events\AIProvisioningUpdated;
use App\Models\AdminNotification;
use App\Models\AIUsageDaily;
use App\Notifications\LowCreditAlertNotification;
use App\Services\AIProvisioningService;
use App\Services\OpenAIBillingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class FetchOpenAIBilling extends Command
{
    protected $signature = 'ai:fetch-openai-billing';

    protected $description = 'Fetch OpenAI billing balance/usage and store a snapshot';

    public function handle(OpenAIBillingService $billingService, AIProvisioningService $provisioningService): int
    {
        $snapshot = $billingService->fetchAndStore();

        if (! $snapshot) {
            $this->error('Failed to fetch OpenAI billing snapshot.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Fetched billing snapshot: available $%s, used $%s, expires %s',
            $snapshot->available_usd,
            $snapshot->used_usd,
            optional($snapshot->expires_at)->toDateString() ?? 'n/a'
        ));

        // Evaluate provisioning status and create admin notification on warning/critical
        $metrics = $provisioningService->getMetrics();
        $status = $metrics['status'] ?? 'safe';

        if (config('ai.alerts_enabled') && in_array($status, ['warning', 'critical'])) {
            // Create in-app notification
            AdminNotification::create([
                'type' => 'ai_provisioning',
                'title' => $status === 'critical' ? 'OpenAI balance critical' : 'OpenAI balance warning',
                'message' => sprintf(
                    'Available: $%s | Liability: %s tokens | Runway: %s days',
                    $metrics['snapshot']?->available_usd ?? 0,
                    number_format($metrics['liability_tokens'] ?? 0),
                    $metrics['runway_days'] ?? 'n/a'
                ),
                'severity' => $status,
                'data' => $metrics,
                'is_read' => false,
            ]);

            // Send email alert if configured
            $alertEmail = config('ai.alert_email');
            if ($alertEmail) {
                Notification::route('mail', $alertEmail)
                    ->notify(new LowCreditAlertNotification($status, $metrics));

                $this->info("Alert email sent to: {$alertEmail}");
            }
        }

        // Broadcast updated metrics to admins
        $usageByModel = AIUsageDaily::query()
            ->where('date', '>=', now()->subDays(7))
            ->selectRaw('model, SUM(prompt_tokens) as prompt_tokens, SUM(completion_tokens) as completion_tokens, SUM(total_tokens) as total_tokens')
            ->groupBy('model')
            ->orderByDesc('total_tokens')
            ->get()
            ->map(function ($row) {
                return [
                    'model' => $row->model ?? 'unknown',
                    'input_tokens' => (int) $row->prompt_tokens,
                    'output_tokens' => (int) $row->completion_tokens,
                ];
            })
            ->values()
            ->toArray();

        broadcast(new AIProvisioningUpdated(
            $metrics,
            $usageByModel,
            [
                'available_usd' => $snapshot->available_usd,
                'used_usd' => $snapshot->used_usd,
                'granted_usd' => $snapshot->granted_usd,
                'period_start' => optional($snapshot->period_start)->toDateString(),
                'period_end' => optional($snapshot->period_end)->toDateString(),
            ]
        ));

        return self::SUCCESS;
    }
}
