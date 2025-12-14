<?php

namespace App\Providers;

use App\Models\Payment;
use App\Models\Project;
use App\Observers\PaymentObserver;
use App\Observers\ProjectObserver;
use App\Services\PromptSystem\ContentDecisionEngine;
use App\Services\PromptSystem\ContextMatcher;
use App\Services\PromptSystem\MockDataGenerator;
use App\Services\PromptSystem\PlaceholderInstructionBuilder;
use App\Services\PromptSystem\PromptBuilder;
use App\Services\PromptSystem\PromptRouter;
use App\Services\PromptSystem\Requirements\DiagramRequirements;
use App\Services\PromptSystem\Requirements\TableRequirements;
use App\Services\PromptSystem\Requirements\ToolRecommendations;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Pulse\Facades\Pulse;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register PromptSystem services
        // $this->app->singleton(ContextMatcher::class);
        // $this->app->singleton(TableRequirements::class);
        // $this->app->singleton(DiagramRequirements::class);
        // $this->app->singleton(ToolRecommendations::class);
        // $this->app->singleton(MockDataGenerator::class);
        // $this->app->singleton(PlaceholderInstructionBuilder::class);

        // $this->app->singleton(ContentDecisionEngine::class, function ($app) {
        //     return new ContentDecisionEngine(
        //         $app->make(TableRequirements::class),
        //         $app->make(DiagramRequirements::class),
        //         $app->make(ToolRecommendations::class)
        //     );
        // });

        // $this->app->singleton(PromptBuilder::class, function ($app) {
        //     return new PromptBuilder(
        //         $app->make(MockDataGenerator::class),
        //         $app->make(PlaceholderInstructionBuilder::class)
        //     );
        // });

        // $this->app->singleton(PromptRouter::class, function ($app) {
        //     return new PromptRouter(
        //         $app->make(ContextMatcher::class),
        //         $app->make(ContentDecisionEngine::class),
        //         $app->make(PromptBuilder::class)
        //     );
        // });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Payment::observe(PaymentObserver::class);
        Project::observe(ProjectObserver::class);

        // Authorize access to Pulse dashboard (only for admins)
        Gate::define('viewPulse', function ($user) {
            return $user->hasRole('super_admin');
        });

        // Optionally, resolve the user for Pulse recordings
        Pulse::user(fn ($user) => [
            'name' => $user->name,
            'extra' => $user->email,
            'avatar' => 'https://ui-avatars.com/api/?name='.urlencode($user->name).'&background=6366f1&color=fff',
        ]);
    }
}
