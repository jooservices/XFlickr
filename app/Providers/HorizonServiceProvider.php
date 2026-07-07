<?php

namespace App\Providers;

use App\Models\User;
use App\Support\HorizonRuntimeConfig;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->applyRuntimeHorizonConfig();

        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    private function applyRuntimeHorizonConfig(): void
    {
        if (! app()->bound(HorizonRuntimeConfig::class)) {
            return;
        }

        /** @var HorizonRuntimeConfig $horizonConfig */
        $horizonConfig = app(HorizonRuntimeConfig::class);
        $environment = (string) config('app.env');

        foreach ($horizonConfig->supervisorPaths() as $supervisor => $path) {
            config()->set(
                "horizon.environments.{$environment}.{$supervisor}.maxProcesses",
                $horizonConfig->effectiveMaxProcesses($supervisor),
            );
        }
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (?User $user = null): bool {
            return $user !== null;
        });
    }
}
