# Laravel CSV Eloquent

Cette extension Laravel émule Eloquent ORM pour interagir avec des fichiers CSV volumineux via une API REST.

## Caractéristiques

- Interface compatible avec Eloquent en lecture seule
- Support des clauses Where, OrderBy, Limit, Offset, etc.
- Pagination des résultats (standard, simple, et curseur)
- Scopes globaux et locaux
- Conversion automatique des types de données (casting)
- Intégration avec Laravel Nova
- Gestion des soft deletes
- Mise en cache automatique des résultats
- Compatible avec Laravel 8, 9, 10, 11 et 12

## Installation

### Via Composer (dépôt privé)

Ajoutez la source dans votre fichier `composer.json` :

```json
"repositories": [
{
"type": "vcs",
"url": "https://github.com/votre-organisation/laravel-csv-eloquent.git"
}
]
```

Puis installez le package :

```bash
composer require votre-organisation/laravel-csv-eloquent
```

### Via un dépôt local

Pour le développement local ou dans un environnement d'entreprise fermé :

```json
"repositories": [
{
"type": "path",
"url": "../laravel-csv-eloquent",
"options": {
"symlink": true
}
}
]
```

## Configuration

Publiez le fichier de configuration :

```bash
php artisan vendor:publish --tag=csv-eloquent-config
```

Configurez l'accès à l'API CSV dans votre fichier `.env` :

```
CSV_API_URL=https://votre-api-csv.example.com
CSV_API_USERNAME=votre_utilisateur
CSV_API_PASSWORD=votre_mot_de_passe
CSV_API_CACHE_TTL=60
```

## Utilisation

### Création d'un modèle

```php
<?php

namespace App\Models;

use Paymetrust\CsvEloquent\Models\ModelCSV;
use Paymetrust\CsvEloquent\Traits\HasCsvSchema;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends ModelCSV
{
    use HasCsvSchema, SoftDeletes;

    protected $csvFile = 'paiements.csv';

    protected $casts = [
        'id' => 'integer',
        'montant' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $columnMapping = [
        'amount' => 'montant',
        'status' => 'statut',
    ];
}
```

### Interrogation des données

```php
// Récupérer tous les enregistrements
$payments = Payment::all();

// Filtrage avec des clauses Where
$validPayments = Payment::where('status', 'Y')
    ->where('amount', '>', 1000)
    ->get();

// Pagination
$pagedPayments = Payment::where('status', 'Y')
    ->orderBy('created_at', 'desc')
    ->paginate(15);
```

### Utilisation avec Nova

```php
<?php

namespace App\Nova;

use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Badge;

class Payment extends Resource
{
    public static $model = \App\Models\Payment::class;

    public function fields(Request $request)
    {
        return [
            ID::make(__('ID'), 'id'),
            
            Number::make(__('Montant'), 'amount')
                ->sortable(),
                
            Badge::make(__('Statut'), 'status')
                ->map([
                    'Y' => 'success',
                    'P' => 'warning',
                    'N' => 'danger',
                ]),
        ];
    }
}
```

## Développement

### Tests

```bash
composer test
```

### Linting

```bash
composer lint
```

## Licence

Ce logiciel est propriétaire et confidentiel. Toute reproduction, distribution ou utilisation non autorisée est
strictement interdite.
