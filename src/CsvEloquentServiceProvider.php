<?php

namespace Adisaf\CsvEloquent;

use Illuminate\Support\ServiceProvider;

class CsvEloquentServiceProvider extends ServiceProvider
{
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

    public function boot()
    {
        // Publie la configuration
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/csv-eloquent.php' => config_path('csv-eloquent.php'),
            ], 'csv-eloquent-config');
        }

        // Log pour vérifier que le provider démarre
        \Illuminate\Support\Facades\Log::info('CsvEloquentServiceProvider démarré');
    }

    public function provides()
    {
        return [CsvClient::class];
    }
}
