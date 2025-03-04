<?php

return [
    /*
    |--------------------------------------------------------------------------
    | URL de l'API CSV
    |--------------------------------------------------------------------------
    |
    | URL de base pour accéder à l'API CSV.
    |
    */
    'api_url' => env('CSV_API_URL', 'http://localhost:8000'),

    /*
    |--------------------------------------------------------------------------
    | Identifiants de l'API CSV
    |--------------------------------------------------------------------------
    |
    | Les identifiants utilisés pour l'authentification basic à l'API CSV.
    |
    */
    'username' => env('CSV_API_USERNAME', ''),
    'password' => env('CSV_API_PASSWORD', ''),

    /*
    |--------------------------------------------------------------------------
    | Paramètres de cache
    |--------------------------------------------------------------------------
    |
    | Configuration liée à la mise en cache des résultats d'API.
    |
    */
    'cache_ttl' => env('CSV_API_CACHE_TTL', 60), // Durée en secondes
    'cache_driver' => env('CSV_API_CACHE_DRIVER', null), // Null = default driver

    /*
    |--------------------------------------------------------------------------
    | Options de déboggage
    |--------------------------------------------------------------------------
    |
    | Options pour faciliter le débogage des requêtes API CSV.
    |
    */
    'debug' => env('CSV_API_DEBUG', false),
    'log_requests' => env('CSV_API_LOG_REQUESTS', false),
    'log_channel' => env('CSV_API_LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Table de correspondance des modèles
    |--------------------------------------------------------------------------
    |
    | La correspondance entre les noms de modèles et les fichiers CSV.
    | Si non spécifié, le nom du fichier sera dérivé du nom du modèle.
    |
    */
    'model_mapping' => [
        'Paymetrust\CsvEloquent\Models\Payment' => 'payments',
        'Paymetrust\CsvEloquent\Models\Transfer' => 'transfers',
    ],
];
