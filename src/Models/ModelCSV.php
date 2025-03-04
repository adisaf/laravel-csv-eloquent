<?php

namespace App\Models;

use App\Models\Csv\Builder as CsvBuilder;
use App\Models\Csv\CsvClient;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Concerns\GuardsAttributes;
use Illuminate\Database\Eloquent\Concerns\HasAttributes;
use Illuminate\Database\Eloquent\Concerns\HasEvents;
use Illuminate\Database\Eloquent\Concerns\HasGlobalScopes;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HidesAttributes;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\ForwardsCalls;
use JsonSerializable;

abstract class ModelCSV implements Arrayable, Jsonable, JsonSerializable
{
    use ForwardsCalls,
        GuardsAttributes,
        HasAttributes,
        HasEvents,
        HasGlobalScopes,
        HasTimestamps,
        HidesAttributes;

    /**
     * Le nom du fichier CSV associé au modèle.
     *
     * @var string
     */
    protected $csvFile;

    /**
     * L'instance du client API CSV.
     *
     * @var \App\Models\Csv\CsvClient
     */
    protected static $csvClient;

    /**
     * La clé primaire du modèle.
     *
     * @var string
     */
    protected $primaryKey = 'id';

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
     * Crée une nouvelle instance ModelCSV.
     *
     * @param array $attributes
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
     * @param array $attributes
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
        return $this->csvFile ?? Str::snake(Str::pluralStudly(class_basename($this))) . '.csv';
    }

    /**
     * Définit le nom du fichier CSV.
     *
     * @param string $csvFile
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
     * @return \App\Models\Csv\CsvClient
     */
    public static function getCsvClient()
    {
        if (!static::$csvClient) {
            static::$csvClient = new CsvClient();
        }

        return static::$csvClient;
    }

    /**
     * Définit l'instance du client API CSV.
     *
     * @param \App\Models\Csv\CsvClient $client
     * @return void
     */
    public static function setCsvClient(CsvClient $client)
    {
        static::$csvClient = $client;
    }

    /**
     * Obtient un nouveau constructeur de requête pour le modèle.
     *
     * @return \App\Models\Csv\Builder
     */
    public function newQuery()
    {
        return $this->newCsvBuilder()->setModel($this);
    }

    /**
     * Obtient une nouvelle instance de constructeur de requête pour le fichier CSV.
     *
     * @return \App\Models\Csv\Builder
     */
    protected function newCsvBuilder()
    {
        return new CsvBuilder(static::getCsvClient());
    }

    /**
     * Crée une nouvelle instance de Collection Eloquent.
     *
     * @param array $models
     * @return \Illuminate\Support\Collection
     */
    public function newCollection(array $models = [])
    {
        return new Collection($models);
    }

    /**
     * Convertit l'instance du modèle en tableau.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->attributesToArray();
    }

    /**
     * Convertit l'instance du modèle en JSON.
     *
     * @param int $options
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
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Mappe un nom de colonne au nom de champ CSV.
     *
     * @param string $column
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
     * @return string
     */
    public function mapFieldToColumn($field)
    {
        $flipped = array_flip($this->columnMapping);

        return $flipped[$field] ?? $field;
    }

    /**
     * Récupère dynamiquement les attributs sur le modèle.
     *
     * @param string $key
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
            Log::error('Échec de la récupération du schéma pour le fichier CSV: ' . $this->getCsvFile(), [
                'exception' => $e->getMessage()
            ]);

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
     * @return Collection
     */
    public static function all()
    {
        return (new static)->newQuery()->get();
    }

    /**
     * Trouve un modèle par sa clé primaire.
     *
     * @param mixed $id
     * @param array $columns
     * @return \App\Models\ModelCSV|Collection|null
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

        return static::query()->whereIn((new static)->getKeyName(), $ids)->get($columns);
    }

    /**
     * Trouve un modèle par sa clé primaire ou lance une exception.
     *
     * @param mixed $id
     * @param array $columns
     * @return \App\Models\ModelCSV
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
     * @return static
     */
    public function newInstance($attributes = [], $exists = false)
    {
        $model = new static((array)$attributes);

        $model->exists = $exists;

        return $model;
    }

    /**
     * Commence à interroger le modèle.
     *
     * @return \App\Models\Csv\Builder
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
}