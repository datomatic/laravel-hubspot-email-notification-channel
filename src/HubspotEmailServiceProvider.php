<?php

namespace Datomatic\LaravelHubspotEmailNotificationChannel;

use Illuminate\Support\ServiceProvider;

class HubspotEmailServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        if ($this->app->runningUnitTests()) {
            $this->loadViewsFrom(__DIR__.'/../resources/views', 'hubspot-engagement');
        }

        $this->mergeConfigFrom(__DIR__.'/../config/hubspot.php', 'hubspot');

        $this->publishes(
            [
                __DIR__.'/../config/hubspot.php' => config_path('hubspot.php'),
            ]
        );
    }

    /**
     * Register the application services.
     */
    public function register()
    {
    }
}
