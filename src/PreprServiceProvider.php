<?php

namespace Preprio;

use Illuminate\Support\ServiceProvider;

class PreprServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/config.php' => config_path('prepr.php'),
        ], 'config');
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'prepr');
        // Register the main class to use with the facade
        $this->app->singleton('prepr', function () {
            return new Prepr;
        });
    }
}