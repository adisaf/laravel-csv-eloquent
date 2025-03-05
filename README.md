# Laravel CSV Eloquent

[![Build Status](https://github.com/adisaf/laravel-csv-eloquent/workflows/Tests/badge.svg)](https://github.com/adisaf/laravel-csv-eloquent/actions)
[![Latest Stable Version](https://poser.pugx.org/adisaf/laravel-csv-eloquent/v/stable.svg)](https://packagist.org/packages/adisaf/laravel-csv-eloquent)
[![Total Downloads](https://poser.pugx.org/adisaf/laravel-csv-eloquent/downloads.svg)](https://packagist.org/packages/adisaf/laravel-csv-eloquent)
[![License](https://poser.pugx.org/adisaf/laravel-csv-eloquent/license.svg)](https://packagist.org/packages/adisaf/laravel-csv-eloquent)

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

## Prérequis

- PHP 8.0 ou supérieur (PHP 8.1+ pour Laravel 10+, PHP 8.2+ pour Laravel 11+)
- Laravel 8.x, 9.x, 10.x, 11.x ou 12.x
- Une API REST pour les fichiers CSV compatibles (voir section API CSV ci-dessous)

## Installation

## Installation

### Via Composer

Si votre module est sur **GitHub** et non sur Packagist, suivez ces étapes pour l'installer avec Composer.

#### 1. Ajouter le dépôt GitHub (si nécessaire)

Dans votre fichier `composer.json`, ajoutez le dépôt sous la clé `repositories` :

```json
{
    "repositories": [
        {
            "laravel-csv-eloquent": {
                "type": "vcs",
                "url": "https://github.com/adisaf/laravel-csv-eloquent.git"
            }
        }
    ]
}
```

#### 2. Installer le package

Exécutez la commande suivante dans votre terminal :

```bash
composer require adisaf/laravel-csv-eloquent
```

#### 4. Vérifier l'installation

Après installation, vérifiez que le package est bien disponible en exécutant :

```bash
composer show adisaf/laravel-csv-eloquent
```

Si tout est correct, vous pouvez maintenant utiliser `laravel-csv-eloquent` dans votre projet Laravel !

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

use Adisaf\CsvEloquent\Models\ModelCSV;
use Adisaf\CsvEloquent\Traits\HasCsvSchema;
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

## API CSV Requise

Ce package nécessite une API REST pour les fichiers CSV avec les endpoints suivants :

- `GET /api/` - Liste des fichiers CSV disponibles
- `GET /api/{nom_fichier}` - Données avec filtrage et pagination
- `GET /api/{nom_fichier}/schema` - Structure du fichier

L'API doit supporter :

- L'authentification Basic Auth
- Le filtrage via des opérateurs (`$eq`, `$ne`, `$gt`, etc.)
- La pagination et le tri

## Développement

### Tests

```bash
composer test
```

### Linting

```bash
composer lint
```

### Analyse statique

```bash
composer stan
```

## Contribuer

Veuillez consulter [CONTRIBUTING.md](CONTRIBUTING.md) pour les détails sur notre code de conduite et le processus de
soumission des pull requests.

## Licence

Ce package est la propriété de Fawaz Adisa et est distribué sous la licence [MIT](LICENSE).

## Changelog

Consultez [CHANGELOG.md](CHANGELOG.md) pour une liste des modifications récentes.
