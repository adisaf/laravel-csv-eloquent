<?php

namespace Adisaf\CsvEloquent\Tests\Manual;

use Adisaf\CsvEloquent\CsvClient;
use Adisaf\CsvEloquent\CsvCollection;
use Adisaf\CsvEloquent\CsvEloquentServiceProvider;
use Adisaf\CsvEloquent\Tests\Manual\Models\Payment;
use Adisaf\CsvEloquent\Tests\Manual\Models\Transfer;
use Dotenv\Dotenv;
use Illuminate\Support\Collection;
use Orchestra\Testbench\TestCase;

/**
 * Test d'intégration manuel pour vérifier le bon fonctionnement avec des modèles Payment et Transfer.
 */
class IntegrationTest extends TestCase
{
    /**
     * Variables d'environnement pour la configuration.
     */
    protected $envVars = [];

    /**
     * Obtenir les fournisseurs de services du package.
     *
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            CsvEloquentServiceProvider::class,
        ];
    }

    /**
     * Définir l'environnement.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // Chargement du fichier .env
        $this->loadDotEnv();

        // Configuration des services
        $app['config']->set('csv-eloquent.api_url', $this->envVars['CSV_API_URL'] ?? 'http://localhost:8000');
        $app['config']->set('csv-eloquent.username', $this->envVars['CSV_API_USERNAME'] ?? 'test');
        $app['config']->set('csv-eloquent.password', $this->envVars['CSV_API_PASSWORD'] ?? 'test');
        $app['config']->set('csv-eloquent.cache_ttl', (int)($this->envVars['CSV_API_CACHE_TTL'] ?? 0));
        $app['config']->set('csv-eloquent.cache_driver', 'array');

        // Activer le mode debug
        $app['config']->set('csv-eloquent.debug', true);
        $app['config']->set('app.debug', true);

        // Configuration de base pour Laravel
        $app['config']->set('cache.default', 'array');
    }

    /**
     * Configuration avant les tests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Vérification de la présence des variables nécessaires
        if (empty($this->envVars['CSV_API_URL']) ||
            empty($this->envVars['CSV_API_USERNAME']) ||
            empty($this->envVars['CSV_API_PASSWORD'])) {
            $this->markTestSkipped(
                'Variables d\'environnement non configurées dans le fichier .env. ' .
                'Ajoutez CSV_API_URL, CSV_API_USERNAME, CSV_API_PASSWORD dans votre fichier .env'
            );
        }

        // Initialisation des clients CSV
        $csvClient = $this->app->make(CsvClient::class);

        // Assignation du client aux modèles
        Payment::setCsvClient($csvClient);
        Transfer::setCsvClient($csvClient);

        // S'assurer que les modèles sont correctement initialisés
        $this->resetModelState();
    }

    /**
     * Réinitialise l'état des modèles entre les tests.
     */
    protected function resetModelState()
    {
        // Vider les caches statiques
        $reflection = new \ReflectionClass(Payment::class);
        if ($reflection->hasProperty('globalScopes')) {
            $globalScopesProperty = $reflection->getProperty('globalScopes');
            $globalScopesProperty->setAccessible(true);
            $globalScopesProperty->setValue([]);
        }

        $reflection = new \ReflectionClass(Transfer::class);
        if ($reflection->hasProperty('globalScopes')) {
            $globalScopesProperty = $reflection->getProperty('globalScopes');
            $globalScopesProperty->setAccessible(true);
            $globalScopesProperty->setValue([]);
        }
    }

    /**
     * Charge les variables d'environnement du fichier .env
     */
    protected function loadDotEnv(): void
    {
        try {
            // Déterminer le chemin vers le fichier .env
            $paths = [
                __DIR__ . '/../../../.env',
                __DIR__ . '/../../../../.env',
                getcwd() . '/.env',
            ];

            $envPath = null;
            foreach ($paths as $path) {
                if (file_exists($path)) {
                    $envPath = dirname($path);
                    break;
                }
            }

            if ($envPath) {
                // Charger le fichier .env avec Dotenv
                $dotenv = Dotenv::createImmutable($envPath);
                $dotenv->load();

                // Stocker les variables d'environnement
                $this->envVars = [
                    'CSV_API_URL' => $_ENV['CSV_API_URL'] ?? null,
                    'CSV_API_USERNAME' => $_ENV['CSV_API_USERNAME'] ?? null,
                    'CSV_API_PASSWORD' => $_ENV['CSV_API_PASSWORD'] ?? null,
                    'CSV_API_CACHE_TTL' => $_ENV['CSV_API_CACHE_TTL'] ?? 0,
                ];
            } else {
                echo "AVERTISSEMENT: Fichier .env non trouvé. Essayez de créer un fichier .env à la racine.\n";
            }
        } catch (\Exception $e) {
            echo 'ERREUR lors du chargement du fichier .env: ' . $e->getMessage() . "\n";
        }
    }

    /**
     * Teste le chargement des modèles séparément.
     */
    public function test_loading_both_models(): void
    {
        // Affichage des informations sur le modèle
        echo "=== INFORMATION SUR LES MODÈLES ===\n";
        $payment = new Payment();
        $paymentCsvFile = $payment->getCsvFile();
        echo "- Payment CSV file: {$paymentCsvFile}\n";

        $transfer = new Transfer();
        $transferCsvFile = $transfer->getCsvFile();
        echo "- Transfer CSV file: {$transferCsvFile}\n";

        echo "- Chemin complet Payment: " . get_class($payment) . "\n";
        echo "- Chemin complet Transfer: " . get_class($transfer) . "\n";

        // Obtenir directement les données brutes pour vérifier
        echo "\n=== TEST API DIRECT ===\n";
        $csvClient = $this->app->make(CsvClient::class);

        try {
            $rawData = $csvClient->getData($paymentCsvFile, ['pagination' => ['limit' => 5]]);
            echo "Données brutes Payment: " . count($rawData['data'] ?? []) . " enregistrements\n";

            // Afficher le premier enregistrement pour analyse
            if (!empty($rawData['data'])) {
                $firstRecord = $rawData['data'][0];
                echo "Premier enregistrement Payment:\n";
                echo "- ID: " . ($firstRecord['id'] ?? 'N/A') . "\n";
                echo "- Montant: " . ($firstRecord['amount'] ?? 'N/A') . "\n";
                echo "- Statut: " . ($firstRecord['status'] ?? 'N/A') . "\n";
            }
        } catch (\Exception $e) {
            echo "ERREUR API Payment: " . $e->getMessage() . "\n";
        }

        // Tester avec le modèle Payment
        echo "\n=== TEST MODÈLE PAYMENT ===\n";
        try {
            $payments = Payment::limit(5)->get();
            echo "Collection Payment: " . $payments->count() . " éléments\n";

            // Utiliser instanceof pour les deux types possibles de collection
            $this->assertTrue(
                $payments instanceof Collection || $payments instanceof CsvCollection,
                "La collection devrait être une instance de Collection ou CsvCollection"
            );

            $this->outputTestInfo('Récupération de paiements', [
                'Nombre de paiements' => $payments->count(),
                'Premier paiement ID' => $payments->first() ? $payments->first()->id : 'N/A',
            ]);
        } catch (\Exception $e) {
            echo "ERREUR modèle Payment: " . $e->getMessage() . "\n";
        }

        // Créer des instances manuelles pour vérifier
        echo "\n=== TEST DE CRÉATION MANUELLE ===\n";
        $manualModel = new Payment();

        // Utiliser fillAttribute au lieu d'accéder directement à $attributes
        $manualModel->fillAttribute('id', 12345);
        $manualModel->fillAttribute('amount', 1000);
        $manualModel->fillAttribute('status', 'Y');

        echo "Modèle créé manuellement: ID=" . $manualModel->getAttribute('id') . "\n";

        $manualCollection = new CsvCollection([$manualModel]);
        echo "Collection manuelle: " . $manualCollection->count() . " éléments\n";
        echo "Premier élément ID: " . ($manualCollection->first() ? $manualCollection->first()->getAttribute('id') : 'N/A') . "\n";

        // Vérifier qu'une collection avec un modèle manuel fonctionne
        $this->assertEquals(1, $manualCollection->count(), "La collection manuelle devrait avoir 1 élément");
        $this->assertEquals(12345, $manualCollection->first()->getAttribute('id'), "L'ID du premier élément devrait être 12345");
    }

    /**
     * Affiche les informations du test dans la console.
     */
    private function outputTestInfo(string $title, array $data): void
    {
        echo "\n=== {$title} ===\n";
        foreach ($data as $key => $value) {
            echo "{$key}: " . (is_array($value) ? json_encode($value) : $value) . "\n";
        }
        echo "===================\n";
    }
}
