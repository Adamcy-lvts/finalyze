<?php

namespace App\Console\Commands;

use App\Services\AIContentGenerator;
use Illuminate\Console\Command;

class CheckAIProviders extends Command
{
    protected $signature = 'ai:check-providers {--json : Output as JSON}';

    protected $description = 'Check the status and capabilities of all AI providers';

    public function handle(AIContentGenerator $generator)
    {
        $this->info('🤖 Checking AI Provider Status...');
        $this->newLine();

        $providers = $generator->getAvailableProviders();
        $activeProvider = $generator->getActiveProvider();
        $history = $generator->getProviderHistory();

        if ($this->option('json')) {
            $this->line(json_encode([
                'active_provider' => $activeProvider ? $activeProvider->getName() : null,
                'available_providers' => count($providers),
                'provider_details' => array_map(function ($provider) {
                    return [
                        'name' => $provider->getName(),
                        'cost_per_1k' => $provider->getCostPer1KTokens(),
                        'capabilities' => $provider->getCapabilities(),
                    ];
                }, $providers),
                'history' => $history,
                'status' => count($providers) > 0 ? 'healthy' : 'degraded',
            ], JSON_PRETTY_PRINT));

            return;
        }

        // Console output
        $this->table(['Status', 'Provider', 'Cost/1K Tokens', 'Models'],
            array_map(function ($provider) use ($activeProvider) {
                $isActive = $activeProvider && $activeProvider->getName() === $provider->getName();

                return [
                    $isActive ? '🟢 ACTIVE' : '🟡 Available',
                    $provider->getName(),
                    '$'.number_format($provider->getCostPer1KTokens(), 3),
                    implode(', ', array_slice($provider->getCapabilities()['models'], 0, 2)).'...',
                ];
            }, $providers)
        );

        $this->newLine();
        $this->info('📊 Summary:');
        $this->line('• Active Provider: '.($activeProvider ? $activeProvider->getName() : 'None'));
        $this->line('• Available Providers: '.count($providers));
        $this->line('• System Status: '.(count($providers) > 0 ? '🟢 Healthy' : '🔴 Degraded'));

        if (count($history) > 0) {
            $this->newLine();
            $this->info('🕒 Recent Provider History:');
            foreach (array_slice($history, -3) as $entry) {
                $this->line("• {$entry['provider']} selected at {$entry['selected_at']} (\${$entry['cost_per_1k']}/1K tokens)");
            }
        }

        return count($providers) > 0 ? 0 : 1;
    }
}
