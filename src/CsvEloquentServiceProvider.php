<?php

namespace Paymetrust\CsvEloquent;

use Illuminate\Support\ServiceProvider;

class CsvEloquentServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Enregistre les services dans le conteneur.
     *
     * @return void
     */
    public function register()
    {
        // Fusionne la configuration
        $this->mergeConfigFrom(
            __DIR__.'/../config/csv-eloquent.php', 'csv-eloquent'
        );

        // Enregistre le singleton du client CSV
        $this->app->singleton(CsvClient::class, function ($app) {
            return new CsvClient;
        });
    }

    /**
     * Bootstrap les services de l'application.
     *
     * @return void
     */
    public function boot()
    {
        // Publie la configuration
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/csv-eloquent.php' => config_path('csv-eloquent.php'),
            ], 'csv-eloquent-config');
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides()
    {
        return [CsvClient::class];
    }
}
