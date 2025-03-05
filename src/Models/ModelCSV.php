<?php

namespace Adisaf\CsvEloquent\Models;

use Adisaf\CsvEloquent\Builder;
use Adisaf\CsvEloquent\Builder as CsvBuilder;
use Adisaf\CsvEloquent\CsvClient;
use Adisaf\CsvEloquent\CsvCollection;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

abstract class ModelCSV extends \Illuminate\Database\Eloquent\Model
{
    // Nous héritons déjà de tous ces traits via Model
    // Nous pouvons les retirer car ils sont déjà inclus

    /**
     * Les scopes globaux enregistrés pour le modèle.
     *
     * @var array
     */
    protected static $globalScopes = [];

    /**
     * Le nom du fichier CSV associé au modèle.
     *
     * @var string
     */
    protected $csvFile;

    /**
     * L'instance du client API CSV.
     *
     * @var \Adisaf\CsvEloquent\CsvClient
     */
    protected static $csvClient;

    /**
     * La clé primaire du modèle.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Le type de la clé primaire du modèle.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * Indique si l'ID du modèle est auto-incrémenté.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * Indique si le modèle doit être horodaté.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * Le nom de la colonne "created at".
     *
     * @var string|null
     */
    const CREATED_AT = 'created_at';

    /**
     * Le nom de la colonne "updated at".
     *
     * @var string|null
     */
    const UPDATED_AT = 'updated_at';

    /**
     * Le nom de la colonne "deleted at".
     *
     * @var string|null
     */
    const DELETED_AT = 'deleted_at';

    /**
     * Les modèles démarrés.
     *
     * @var array
     */
    protected static $booted = [];

    /**
     * Les initialiseurs de traits.
     *
     * @var array
     */
    protected static $traitInitializers = [];

    /**
     * Les attributs qui doivent être convertis.
     *
     * @var array
     */
    protected $casts = [];

    /**
     * Les attributs qui doivent être masqués pour les tableaux.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * Les attributs qui doivent être visibles dans les tableaux.
     *
     * @var array
     */
    protected $visible = [];

    /**
     * Les attributs du modèle.
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * Le mappage des colonnes CSV.
     *
     * @var array
     */
    protected $columnMapping = [];

    /**
     * Indique si le modèle existe dans la source de données.
     *
     * @var bool
     */
    public $exists = false;

    /**
     * Les relations chargées pour le modèle.
     *
     * @var array
     */
    protected $relations = [];

    /**
     * Les relations qui doivent être chargées en eager loading.
     *
     * @var array
     */
    protected $with = [];

    /**
     * Indique si l'entité est en train d'être chargée avec eager loading.
     *
     * @var bool
     */
    protected $eagerLoading = false;

    /**
     * Obtient le type de la clé primaire.
     *
     * Nécessaire pour le trait HasAttributes.
     *
     * @return string
     */
    public function getKeyType()
    {
        return $this->keyType;
    }

    /**
     * Définit le type de la clé primaire.
     *
     * @param string $type
     *
     * @return $this
     */
    public function setKeyType($type)
    {
        $this->keyType = $type;

        return $this;
    }

    /**
     * Obtient une pseudo-connexion pour satisfaire le trait HasAttributes.
     *
     * @return object
     */
    public function getConnection()
    {
        // Retourner un objet minimal qui implémente les méthodes
        // nécessaires pour le trait HasAttributes
        return new class {
            public function getQueryGrammar()
            {
                return new class {
                    public function getDateFormat()
                    {
                        return 'Y-m-d H:i:s';
                    }
                };
            }

            public function query()
            {
                return $this;
            }

            public function getPostProcessor()
            {
                return $this;
            }

            public function processSelectColumn($column)
            {
                return $column;
            }
        };
    }

    /**
     * Obtient le nom de la connexion du modèle.
     *
     * @return string|null
     */
    public function getConnectionName()
    {
        return 'default';
    }

    /**
     * Définit le nom de la connexion du modèle.
     *
     * @param string|null $name
     *
     * @return $this
     */
    public function setConnection($name)
    {
        return $this;
    }

    /**
     * Crée une nouvelle instance ModelCSV.
     *
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        $this->bootIfNotBooted();
        $this->initializeTraits();
        $this->syncOriginal();
        $this->fill($attributes);
    }

    /**
     * Vérifie si le modèle doit être démarré et si oui, le démarre.
     *
     * @return void
     */
    protected function bootIfNotBooted()
    {
        if (!isset(static::$booted[static::class])) {
            static::$booted[static::class] = true;

            static::bootTraits();
        }
    }

    /**
     * Démarre tous les traits démarrables sur le modèle.
     *
     * @return void
     */
    protected static function bootTraits()
    {
        $class = static::class;

        foreach (class_uses_recursive($class) as $trait) {
            if (method_exists($class, $method = 'boot' . class_basename($trait))) {
                forward_static_call([$class, $method]);
            }
        }
    }

    /**
     * Initialise tous les traits sur le modèle.
     *
     * @return void
     */
    protected function initializeTraits()
    {
        foreach (static::$traitInitializers[static::class] ?? [] as $method) {
            $this->{$method}();
        }
    }

    /**
     * Remplit le modèle avec un tableau d'attributs.
     *
     * @return $this
     */
    public function fill(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    /**
     * Obtient le nom du fichier CSV associé au modèle.
     *
     * @return string
     */
    public function getCsvFile()
    {
        $file = $this->csvFile ?? Str::snake(Str::pluralStudly(class_basename($this)));

        // Enlever l'extension .csv du nom de fichier s'il est présent
        return str_replace('.csv', '', $file);
    }

    /**
     * Définit le nom du fichier CSV.
     *
     * @param string $csvFile
     *
     * @return $this
     */
    public function setCsvFile($csvFile)
    {
        $this->csvFile = $csvFile;

        return $this;
    }

    /**
     * Obtient l'instance du client API CSV.
     *
     * @return \Adisaf\CsvEloquent\CsvClient
     */
    public static function getCsvClient()
    {
        if (!static::$csvClient) {
            static::$csvClient = new CsvClient;
        }

        return static::$csvClient;
    }

    /**
     * Définit l'instance du client API CSV.
     *
     * @return void
     */
    public static function setCsvClient(CsvClient $client)
    {
        static::$csvClient = $client;
    }

    /**
     * Obtient un nouveau constructeur de requête pour le modèle.
     *
     * @return CsvBuilder
     */
    public function newQuery()
    {
        $builder = $this->newCsvBuilder();
        $builder->setModel($this);

        return $builder;
    }

    /**
     * Obtient une nouvelle instance de constructeur de requête pour le fichier CSV.
     *
     * @return CsvBuilder
     */
    protected function newCsvBuilder()
    {
        return Builder::createWithCsvClient(static::getCsvClient());
    }

    /**
     * Crée une nouvelle instance de Collection Eloquent.
     *
     * @return \Illuminate\Support\Collection
     */
    public function newCollection(array $models = [])
    {
        if (config('csv-eloquent.debug', false) && app()->bound('log')) {
            Log::debug("ModelCSV::newCollection - Création d'une collection avec " . count($models) . " modèles\n");
        }

        // Vérifier le contenu des modèles
        if (!empty($models)) {
            if (config('csv-eloquent.debug', false) && app()->bound('log')) {
                Log::debug('Premier modèle de type: ' . get_class($models[0]));
            }

            // Vérifier si les attributs sont accessibles
            $firstModel = $models[0];
            if (isset($firstModel->attributes)) {
                if (config('csv-eloquent.debug', false) && app()->bound('log')) {
                    Log::debug('Attributs du premier modèle: ' . count($firstModel->attributes));
                }

                // Afficher les attributs
                if (!empty($firstModel->attributes)) {
                    if (config('csv-eloquent.debug', false) && app()->bound('log')) {
                        Log::debug("Exemples d'attributs:\n");
                    }
                    $i = 0;
                    foreach ($firstModel->attributes as $key => $value) {
                        if (config('csv-eloquent.debug', false) && app()->bound('log')) {
                            Log::debug("- $key: " . (is_string($value) ? $value : gettype($value)));
                        }
                        $i++;
                        if ($i >= 3) {
                            break;
                        } // Limiter à 3 attributs pour la clarté
                    }
                }
            } else {
                if (config('csv-eloquent.debug', false) && app()->bound('log')) {
                    Log::debug("ATTENTION: Les attributs du premier modèle ne sont pas accessibles\n");
                }
            }
        }

        // Utiliser notre collection personnalisée au lieu de la Collection standard
        return new CsvCollection($models);
    }

    /**
     * Obtient un attribut du modèle.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getAttribute($key)
    {
        if (isset($this->attributes[$key])) {
            return $this->attributes[$key];
        }

        return null;
    }

    /**
     * Convertit l'instance du modèle en tableau.
     *
     * @return array
     */
    public function toArray()
    {
        // Version simplifiée pour le debug
        return $this->attributes ?? [];
    }

    /**
     * Convertit l'instance du modèle en JSON.
     *
     * @param int $options
     *
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Convertit l'objet en quelque chose de sérialisable en JSON.
     *
     * @return array
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * Mappe un nom de colonne au nom de champ CSV.
     *
     * @param string $column
     *
     * @return string
     */
    public function mapColumnToField($column)
    {
        return $this->columnMapping[$column] ?? $column;
    }

    /**
     * Mappe un champ CSV au nom de colonne.
     *
     * @param string $field
     *
     * @return string
     */
    public function mapFieldToColumn($field)
    {
        $flipped = array_flip($this->columnMapping);

        return $flipped[$field] ?? $field;
    }

    /**
     * Définit un attribut sur le modèle.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return $this
     */
    public function setAttribute($key, $value)
    {
        // Méthode simplifiée pour définir un attribut
        $this->fillAttribute($key, $value);

        return $this;
    }

    /**
     * Remplit un attribut spécifique sur le modèle, en contournant les problèmes d'attributs surchargés.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return $this
     */
    public function fillAttribute($key, $value)
    {
        // Déterminer s'il s'agit d'une date
        $isDateAttribute = in_array($key, [
            $this::CREATED_AT,
            $this::UPDATED_AT,
            $this::DELETED_AT,
        ]);

        // Traitement spécial pour les dates
        if ($isDateAttribute && !is_null($value)) {
            $value = $this->asDateTime($value);
        }

        // Tenter de caster la valeur si un cast est défini
        $castType = $this->casts[$key] ?? null;
        if ($castType && !is_null($value)) {
            try {
                $value = $this->castAttribute($key, $value);
            } catch (\Exception $e) {
                if (config('csv-eloquent.debug', false) && app()->bound('log')) {
                    Log::debug("ATTENTION: Erreur de casting pour '$key': " . $e->getMessage());
                }
                // Continuer avec la valeur non-castée
            }
        }

        // Définir la valeur directement en initialisant l'array au besoin
        if (!isset($this->attributes)) {
            $this->attributes = [];
        }

        // Affecter de manière sûre
        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Récupère dynamiquement les attributs sur le modèle.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function __get($key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Définit dynamiquement les attributs sur le modèle.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return void
     */
    public function __set($key, $value)
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Détermine si un attribut ou une relation existe sur le modèle.
     *
     * @param string $key
     *
     * @return bool
     */
    public function __isset($key)
    {
        return !is_null($this->getAttribute($key));
    }

    /**
     * Désactive un attribut sur le modèle.
     *
     * @param string $key
     *
     * @return void
     */
    public function __unset($key)
    {
        unset($this->attributes[$key]);
    }

    /**
     * Gère les appels de méthode dynamiques vers le modèle.
     *
     * @param string $method
     * @param array $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->forwardCallTo($this->newQuery(), $method, $parameters);
    }

    /**
     * Gère les appels de méthode statiques dynamiques vers le modèle.
     *
     * @param string $method
     * @param array $parameters
     *
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        return (new static)->$method(...$parameters);
    }

    /**
     * Obtient la valeur indiquant si les IDs sont auto-incrémentés.
     *
     * @return bool
     */
    public function getIncrementing()
    {
        return $this->incrementing;
    }

    /**
     * Définit si les IDs sont auto-incrémentés.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setIncrementing($value)
    {
        $this->incrementing = $value;

        return $this;
    }

    /**
     * Obtient la clé primaire du modèle.
     *
     * @return string
     */
    public function getKeyName()
    {
        return $this->primaryKey;
    }

    /**
     * Obtient la valeur de la clé primaire du modèle.
     *
     * @return mixed
     */
    public function getKey()
    {
        return $this->getAttribute($this->getKeyName());
    }

    /**
     * Définit la clé primaire du modèle.
     *
     * @param string $key
     *
     * @return $this
     */
    public function setKeyName($key)
    {
        $this->primaryKey = $key;

        return $this;
    }

    /**
     * Obtient le schéma du fichier CSV.
     *
     * @return array
     */
    public function getSchema()
    {
        try {
            $response = static::getCsvClient()->getSchema($this->getCsvFile());

            return $response['data']['schema'] ?? [];
        } catch (\Exception $e) {
            if (app()->bound('log')) {
                Log::error('Échec de la récupération du schéma pour le fichier CSV: ' . $this->getCsvFile(), [
                    'exception' => $e->getMessage(),
                ]);
            }

            return [];
        }
    }

    /**
     * Détermine si le modèle utilise des suppressions logiques.
     *
     * @return bool
     */
    public function usesSoftDeletes()
    {
        return in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive($this));
    }

    /**
     * Obtient tous les modèles du fichier CSV.
     *
     * @param string[] $columns
     *
     * @return Collection
     */
    public static function all($columns = ['*'])
    {
        return (new static)->newQuery()->get();
    }

    /**
     * Trouve un modèle par sa clé primaire.
     *
     * @param mixed $id
     * @param array $columns
     *
     * @return \Adisaf\CsvEloquent\Models\ModelCSV|Collection|null
     */
    public static function find($id, $columns = ['*'])
    {
        if (is_array($id)) {
            return static::findMany($id, $columns);
        }

        return static::query()->find($id, $columns);
    }

    /**
     * Trouve plusieurs modèles par leurs clés primaires.
     *
     * @param \Illuminate\Contracts\Support\Arrayable|array $ids
     * @param array $columns
     *
     * @return \Illuminate\Support\Collection
     */
    public static function findMany($ids, $columns = ['*'])
    {
        if ($ids instanceof Arrayable) {
            $ids = $ids->toArray();
        }

        if (empty($ids)) {
            return collect();
        }

        return static::query()->whereIn((new static)->getKeyName(), $ids, 'and', false)->get($columns);
    }

    /**
     * Trouve un modèle par sa clé primaire ou lance une exception.
     *
     * @param mixed $id
     * @param array $columns
     *
     * @return \Adisaf\CsvEloquent\Models\ModelCSV
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function findOrFail($id, $columns = ['*'])
    {
        $result = static::find($id, $columns);

        if (is_array($id)) {
            if (count($result) === count(array_unique($id))) {
                return $result;
            }
        } elseif (!is_null($result)) {
            return $result;
        }

        throw (new ModelNotFoundException)->setModel(
            static::class, $id
        );
    }

    /**
     * Crée une nouvelle instance du modèle.
     *
     * @param array $attributes
     * @param bool $exists
     *
     * @return static
     */
    public function newInstance($attributes = [], $exists = false)
    {
        try {
            // Créer directement une nouvelle instance de la classe actuelle
            $className = get_class($this);
            $model = new $className;

            // Définir si l'instance existe déjà
            $model->exists = $exists;

            // Remplir avec les attributs fournis
            if (!empty($attributes)) {
                $model->fill($attributes);
            }

            return $model;
        } catch (\Exception $e) {
            if (config('csv-eloquent.debug', false) && app()->bound('log')) {
                Log::debug("ERREUR lors de la création d'instance: " . $e->getMessage());
            }
            // Fallback à l'approche simple
            $model = new static;
            $model->exists = $exists;

            // Remplir avec les attributs fournis
            if (!empty($attributes)) {
                foreach ($attributes as $key => $value) {
                    $model->attributes[$key] = $value;
                }
            }

            return $model;
        }
    }

    /**
     * Commence à interroger le modèle.
     *
     * @return CsvBuilder
     */
    public static function query()
    {
        return (new static)->newQuery();
    }

    /**
     * Obtient la table associée au modèle.
     *
     * @return string
     */
    public function getTable()
    {
        return $this->getCsvFile();
    }

    /**
     * Commence une requête pour le modèle avec eager loading.
     *
     * @param array|string $relations
     *
     * @return \Adisaf\CsvEloquent\Builder
     */
    public static function with($relations)
    {
        $instance = new static;

        if (is_string($relations)) {
            $relations = func_get_args();
        }

        $query = $instance->newQuery();

        // Nous stockons les relations pour la compatibilité, même si elles ne font rien
        // dans notre implémentation CSV actuelle
        $instance->with = array_merge($instance->with, $relations);

        return $query;
    }

    /**
     * Récupère les relations chargées pour le modèle.
     *
     * @return array
     */
    public function getRelations()
    {
        return $this->relations;
    }

    /**
     * Obtient une relation spécifique.
     *
     * @param string $relation
     *
     * @return mixed
     */
    public function getRelation($relation)
    {
        return $this->relations[$relation] ?? null;
    }

    /**
     * Définit les relations qui doivent être eager loaded.
     *
     * @return $this
     */
    public function setRelations(array $relations)
    {
        $this->relations = $relations;

        return $this;
    }

    /**
     * Ajoute une relation dans la liste des relations du modèle.
     *
     * @param string $relation
     * @param mixed $value
     *
     * @return $this
     */
    public function setRelation($relation, $value)
    {
        $this->relations[$relation] = $value;

        return $this;
    }

    /**
     * Détermine si une relation est chargée.
     *
     * @param string $key
     *
     * @return bool
     */
    public function relationLoaded($key)
    {
        return array_key_exists($key, $this->relations);
    }

    /**
     * Obtient les noms de toutes les relations définies sur le modèle.
     *
     * @return array
     */
    public function getRelationNames()
    {
        // Dans notre contexte, nous n'avons pas encore implémenté de vraies relations
        return [];
    }

    /**
     * Obtient les relations à charger en eager loading.
     *
     * @return array
     */
    public function getEagerLoads()
    {
        return $this->with;
    }
}
