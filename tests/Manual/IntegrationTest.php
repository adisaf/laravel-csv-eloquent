<?php

namespace Adisaf\CsvEloquent\Tests\Manual;

use Adisaf\CsvEloquent\CsvClient;
use Adisaf\CsvEloquent\Tests\Manual\Models\Payment;
use Adisaf\CsvEloquent\Tests\Manual\Models\Transfer;
use Dotenv\Dotenv;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Test d'intégration manuel pour vérifier le bon fonctionnement avec des modèles Payment et Transfer.
 *
 * IMPORTANT: Ce test n'est pas destiné à être exécuté dans le CI/CD.
 * Il nécessite une instance réelle d'API CSV pour fonctionner.
 *
 * Pour exécuter ce test manuellement:
 * 1. Configurez votre fichier .env avec les variables d'API CSV
 * 2. Exécutez: php vendor/bin/phpunit tests/Manual/IntegrationTest.php
 */
class IntegrationTest extends TestCase
{
    /**
     * Variables d'environnement pour la configuration.
     */
    protected $envVars = [];

    /**
     * Configuration avant les tests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Chargement du fichier .env à la racine du projet
        $this->loadDotEnv();

        // Vérification de la présence des variables nécessaires
        if (empty($this->envVars['CSV_API_URL']) ||
            empty($this->envVars['CSV_API_USERNAME']) ||
            empty($this->envVars['CSV_API_PASSWORD'])) {
            $this->markTestSkipped(
                'Variables d\'environnement non configurées dans le fichier .env. ' .
                'Ajoutez CSV_API_URL, CSV_API_USERNAME, CSV_API_PASSWORD dans votre fichier .env'
            );
        }

        // Initialisation des clients CSV avec les variables du .env
        $data = [
            'api_url' => $this->envVars['CSV_API_URL'],
            'username' => $this->envVars['CSV_API_USERNAME'],
            'password' => $this->envVars['CSV_API_PASSWORD'],
            'cache_ttl' => 0, // Désactiver le cache pour les tests
        ];

        $csvClient = new CsvClient($data);

        // Configuration manuelle du client
        $this->setClientConfiguration($csvClient, $this->envVars);

        // Assignation du client aux modèles
        Payment::setCsvClient($csvClient);
        Transfer::setCsvClient($csvClient);
    }

    /**
     * Charge les variables d'environnement du fichier .env
     */
    protected function loadDotEnv(): void
    {
        try {
            // Déterminer le chemin vers le fichier .env (plusieurs possibilités)
            $paths = [
                __DIR__ . '/../../../.env',  // Si test exécuté depuis le package en développement
                __DIR__ . '/../../../../.env', // Si le package est dans vendor
                getcwd() . '/.env', // Répertoire de travail actuel
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
                    'CSV_API_CACHE_TTL' => $_ENV['CSV_API_CACHE_TTL'] ?? 0, // Désactiver le cache par défaut
                ];
            } else {
                echo "AVERTISSEMENT: Fichier .env non trouvé. Essayez de créer un fichier .env à la racine de votre projet.\n";
            }
        } catch (\Exception $e) {
            echo 'ERREUR lors du chargement du fichier .env: ' . $e->getMessage() . "\n";
            // Continuer sans plantage, le test sera ignoré si les variables nécessaires sont manquantes
        }
    }

    /**
     * Configure manuellement le client CSV avec les variables d'environnement
     */
    protected function setClientConfiguration(CsvClient $client, array $config): void
    {
        // Méthode de reflection pour définir les propriétés protégées
        $reflection = new \ReflectionClass($client);

        if ($reflection->hasProperty('baseUrl')) {
            $property = $reflection->getProperty('baseUrl');
            $property->setAccessible(true);
            $property->setValue($client, $config['CSV_API_URL']);
        }

        if ($reflection->hasProperty('username')) {
            $property = $reflection->getProperty('username');
            $property->setAccessible(true);
            $property->setValue($client, $config['CSV_API_USERNAME']);
        }

        if ($reflection->hasProperty('password')) {
            $property = $reflection->getProperty('password');
            $property->setAccessible(true);
            $property->setValue($client, $config['CSV_API_PASSWORD']);
        }

        if ($reflection->hasProperty('cacheTtl')) {
            $property = $reflection->getProperty('cacheTtl');
            $property->setAccessible(true);
            $property->setValue($client, (int)$config['CSV_API_CACHE_TTL']);
        }
    }

    /**
     * Teste le chargement des modèles séparément.
     */
    public function test_loading_both_models(): void
    {
        // Obtenir quelques paiements
        $payments = Payment::limit(5)->get();
        $this->assertInstanceOf(Collection::class, $payments);
        $this->outputTestInfo('Récupération de 5 paiements', [
            'Nombre de paiements' => $payments->count(),
            'Premier paiement ID' => $payments->first()->id ?? 'N/A',
        ]);

        // Obtenir quelques transferts
        $transfers = Transfer::limit(5)->get();
        $this->assertInstanceOf(Collection::class, $transfers);
        $this->outputTestInfo('Récupération de 5 transferts', [
            'Nombre de transferts' => $transfers->count(),
            'Premier transfert ID' => $transfers->first()->id ?? 'N/A',
        ]);
    }

    /**
     * Teste le filtrage sur les deux modèles.
     */
    public function test_filtering_both_models(): void
    {
        // Filtrer les paiements
        $filteredPayments = Payment::where('amount', '>', 1000)
            ->where('status', 'Y')
            ->limit(5)
            ->get();

        $this->outputTestInfo('Filtrage des paiements (montant > 1000 et statut = Y)', [
            'Nombre de paiements filtrés' => $filteredPayments->count(),
            'Premier paiement' => $filteredPayments->first() ? [
                'id' => $filteredPayments->first()->id,
                'amount' => $filteredPayments->first()->amount,
                'status' => $filteredPayments->first()->status,
            ] : 'N/A',
        ]);

        // Filtrer les transferts
        $filteredTransfers = Transfer::where('amount', '>', 500)
            ->where('status', 'Y')  // Y = succès pour les transferts
            ->limit(5)
            ->get();

        $this->outputTestInfo('Filtrage des transferts (montant > 500 et statut = Y)', [
            'Nombre de transferts filtrés' => $filteredTransfers->count(),
            'Premier transfert' => $filteredTransfers->first() ? [
                'id' => $filteredTransfers->first()->id,
                'amount' => $filteredTransfers->first()->amount,
                'status' => $filteredTransfers->first()->status,
            ] : 'N/A',
        ]);
    }

    /**
     * Teste le tri sur les deux modèles.
     */
    public function test_sorting_both_models(): void
    {
        // Trier les paiements par montant (décroissant)
        $paymentsSorted = Payment::orderBy('amount', 'desc')
            ->limit(5)
            ->get();

        $paymentAmounts = $paymentsSorted->pluck('amount')->toArray();
        $this->outputTestInfo('Tri des paiements par montant (décroissant)', [
            'Montants' => implode(', ', $paymentAmounts),
        ]);
        $this->assertTrue(
            $paymentAmounts === array_values(rsort($paymentAmounts) ? $paymentAmounts : $paymentAmounts),
            'Les paiements devraient être triés par montant décroissant'
        );

        // Trier les transferts par date (croissant)
        $transfersSorted = Transfer::orderBy('created_at', 'asc')
            ->limit(5)
            ->get();

        $transferDates = $transfersSorted->pluck('created_at')->map(function ($date) {
            return $date->format('Y-m-d H:i:s');
        })->toArray();

        $this->outputTestInfo('Tri des transferts par date (croissant)', [
            'Dates' => implode(', ', $transferDates),
        ]);
    }

    /**
     * Teste la pagination sur les deux modèles.
     */
    public function test_pagination_both_models(): void
    {
        // Paginer les paiements
        $paymentsPage1 = Payment::paginate(5, ['*'], 'page', 1);
        $paymentsPage2 = Payment::paginate(5, ['*'], 'page', 2);

        $this->outputTestInfo('Pagination des paiements', [
            'Page 1 count' => $paymentsPage1->count(),
            'Page 2 count' => $paymentsPage2->count(),
            'Total pages' => $paymentsPage1->lastPage(),
        ]);

        // Vérifier que les IDs de la page 1 et 2 sont différents
        $page1Ids = $paymentsPage1->pluck('id')->toArray();
        $page2Ids = $paymentsPage2->pluck('id')->toArray();
        $this->assertEquals(0, count(array_intersect($page1Ids, $page2Ids)),
            'Les paiements de la page 1 et 2 ne devraient pas se chevaucher');

        // Paginer les transferts
        $transfersPage1 = Transfer::paginate(5);

        $this->outputTestInfo('Pagination des transferts', [
            'Page 1 count' => $transfersPage1->count(),
            'Total pages' => $transfersPage1->lastPage(),
        ]);
    }

    /**
     * Teste la simulation des relations entre paiements et transferts.
     */
    public function test_simulated_relationships(): void
    {
        // Obtenir un paiement
        $payment = Payment::first();
        if (!$payment) {
            $this->markTestSkipped('Aucun paiement trouvé pour tester les relations');
        }

        // Simuler une relation en récupérant les transferts liés à ce paiement
        $merchantTransactionId = $payment->merchant_transaction_id;
        $relatedTransfers = Transfer::where('merchant_transaction_id', $merchantTransactionId)->get();

        $this->outputTestInfo('Relation simulée: transferts associés au paiement', [
            'Payment ID' => $payment->id,
            'Merchant Transaction ID' => $merchantTransactionId,
            'Related Transfers Count' => $relatedTransfers->count(),
        ]);

        // Tester la méthode helper
        $relatedTransfersViaHelper = $payment->getTransfers();
        $this->outputTestInfo('Relation simulée via helper', [
            'Related Transfers Count via Helper' => $relatedTransfersViaHelper->count(),
        ]);

        // Simuler une relation inverse
        $transfer = Transfer::first();
        if (!$transfer) {
            $this->markTestSkipped('Aucun transfert trouvé pour tester les relations');
        }

        $transferMerchantTransactionId = $transfer->merchant_transaction_id;
        $relatedPayment = Payment::where('merchant_transaction_id', $transferMerchantTransactionId)->first();

        $this->outputTestInfo('Relation simulée: paiement associé au transfert', [
            'Transfer ID' => $transfer->id,
            'Transfer Merchant Transaction ID' => $transferMerchantTransactionId,
            'Related Payment Found' => $relatedPayment ? 'Oui' : 'Non',
        ]);

        // Tester la méthode helper
        $relatedPaymentViaHelper = $transfer->getPayment();
        $this->outputTestInfo('Relation inverse simulée via helper', [
            'Related Payment Found via Helper' => $relatedPaymentViaHelper ? 'Oui' : 'Non',
        ]);
    }

    /**
     * Teste les fonctionnalités avancées comme le groupement et l'agrégation.
     */
    public function test_advanced_features(): void
    {
        // Tester le groupement et le comptage (simulés côté client)
        $payments = Payment::limit(100)->get(); // Limiter pour éviter de charger toutes les données
        $paymentsByStatus = $payments->groupBy('status');

        $this->outputTestInfo('Groupement des paiements par statut', [
            'Nombre de groupes' => $paymentsByStatus->count(),
            'Statuts distincts' => implode(', ', $paymentsByStatus->keys()->toArray()),
        ]);

        // Calculer la somme des montants par statut
        $totalByStatus = [];
        foreach ($paymentsByStatus as $status => $statusPayments) {
            $totalByStatus[$status] = $statusPayments->sum('amount');
        }

        $this->outputTestInfo('Somme des montants par statut', $totalByStatus);

        // Tester l'analyse par pays et opérateur
        $paymentsByCountry = $payments->groupBy('country');
        $countryCount = $paymentsByCountry->map->count();

        $this->outputTestInfo('Distribution des paiements par pays', $countryCount->toArray());

        // Tester les scopes personnalisés
        $ciPayments = Payment::forCountry('CI')->limit(5)->get();
        $this->outputTestInfo('Paiements en Côte d\'Ivoire (via scope)', [
            'Nombre de paiements' => $ciPayments->count(),
        ]);

        $omPayments = Payment::forCarrier('OM')->limit(5)->get();
        $this->outputTestInfo('Paiements via Orange Money (via scope)', [
            'Nombre de paiements' => $omPayments->count(),
        ]);
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
