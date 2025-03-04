<?php

namespace Paymetrust\CsvEloquent\Tests;

use Mockery;
use Orchestra\Testbench\TestCase as BaseTestCase;
use VotreOrganisation\CsvEloquent\CsvClient;
use VotreOrganisation\CsvEloquent\CsvEloquentServiceProvider;

abstract class TestCase extends BaseTestCase
{
    /**
     * Mock de client CSV.
     *
     * @var \Mockery\MockInterface
     */
    protected $csvClientMock;

    /**
     * Configurez l'environnement de test.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // Configuration du test
        $app['config']->set('csv-eloquent.api_url', 'http://test-api.example.com');
        $app['config']->set('csv-eloquent.username', 'test-user');
        $app['config']->set('csv-eloquent.password', 'test-password');
        $app['config']->set('csv-eloquent.cache_ttl', 0); // Désactive le cache pour les tests
    }

    /**
     * Obtenir les fournisseurs de services du package.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            CsvEloquentServiceProvider::class,
        ];
    }

    /**
     * Configurez avant chaque test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Créer un mock du client CSV
        $this->csvClientMock = Mockery::mock(CsvClient::class);
        $this->app->instance(CsvClient::class, $this->csvClientMock);
    }

    /**
     * Nettoie après chaque test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}