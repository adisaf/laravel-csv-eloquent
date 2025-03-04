<?php

namespace Adisaf\CsvEloquent;

use Adisaf\CsvEloquent\Models\ModelCSV;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class Builder implements \Illuminate\Contracts\Database\Query\Builder
{
    /**
     * Le modèle interrogé.
     *
     * @var ModelCSV
     */
    protected $model;

    /**
     * L'instance du client API CSV.
     *
     * @var CsvClient
     */
    protected $csvClient;

    /**
     * Les colonnes qui doivent être retournées.
     *
     * @var array
     */
    protected $columns = ['*'];

    /**
     * Les contraintes where pour la requête.
     *
     * @var array
     */
    protected $wheres = [];

    /**
     * Les ordres pour la requête.
     *
     * @var array
     */
    protected $orders = [];

    /**
     * Le nombre maximum d'enregistrements à retourner.
     *
     * @var int
     */
    protected $limit;

    /**
     * Le nombre d'enregistrements à sauter.
     *
     * @var int
     */
    protected $offset;

    /**
     * Les groupements de requête.
     *
     * @var array
     */
    protected $groups = [];

    /**
     * Les contraintes having pour la requête.
     *
     * @var array
     */
    protected $havings = [];

    /**
     * Indique si les modèles supprimés logiquement doivent être inclus.
     *
     * @var bool
     */
    protected $withTrashed = false;

    /**
     * Indique si seuls les modèles supprimés logiquement doivent être inclus.
     *
     * @var bool
     */
    protected $onlyTrashed = false;

    /**
     * Crée une nouvelle instance de constructeur de requête.
     *
     *
     * @return void
     */
    public function __construct(CsvClient $csvClient)
    {
        $this->csvClient = $csvClient;
    }

    /**
     * Définit l'instance du modèle pour la requête.
     *
     * @return $this
     */
    public function setModel(ModelCSV $model)
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Obtient l'instance du modèle interrogé.
     *
     * @return ModelCSV
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Définit les colonnes à sélectionner.
     *
     * @param array|mixed $columns
     *
     * @return $this
     */
    public function select($columns = ['*'])
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();

        return $this;
    }

    /**
     * Ajoute une clause where de base à la requête.
     *
     * @param string|array|\Closure $column
     * @param mixed $operator
     * @param mixed $value
     * @param string $boolean
     *
     * @return $this
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        // Si la colonne est un tableau, on suppose qu'il s'agit d'un tableau de paires clé-valeur
        if (is_array($column)) {
            return $this->addArrayOfWheres($column, $boolean);
        }

        // Si la colonne est en fait une Closure, nous supposerons que le développeur veut
        // commencer une instruction where imbriquée qui est enveloppée entre parenthèses.
        if ($column instanceof \Closure) {
            return $this->whereNested($column, $boolean);
        }

        // Si aucun opérateur n'est donné, nous le déterminerons en fonction de la valeur
        if (func_num_args() === 2) {
            [$value, $operator] = [$operator, '='];
        }

        // Si la valeur est nulle et l'opérateur est égal, nous le convertirons en is null
        if (is_null($value) && $operator === '=') {
            $operator = 'is null';
        }

        // Si la valeur est nulle et l'opérateur n'est pas égal, nous le convertirons en is not null
        if (is_null($value) && $operator === '!=') {
            $operator = 'is not null';
        }

        // Mapper les opérateurs Laravel aux opérateurs API
        $mappedOperator = $this->mapOperator($operator);

        // Nous ajouterons la clause where au tableau de wheres
        $this->wheres[] = [
            'column' => $this->mapColumnToField($column),
            'operator' => $mappedOperator,
            'value' => $value,
            'boolean' => $boolean,
        ];

        return $this;
    }

    protected function addArrayOfWheres($column, $boolean = 'and', $method = 'where')
    {
        foreach ($column as $key => $value) {
            $this->$method($key, '=', $value, $boolean);
        }

        return $this;
    }

    /**
     * Ajoute une instruction where imbriquée à la requête.
     *
     * @param string $boolean
     *
     * @return $this
     */
    public function whereNested(\Closure $callback, $boolean = 'and')
    {
        $builder = new static($this->csvClient);
        $builder->setModel($this->model);

        call_user_func($callback, $builder);

        $this->wheres[] = [
            'type' => 'nested',
            'query' => $builder->wheres,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Ajoute une clause "or where" à la requête.
     *
     * @param string|array|\Closure $column
     * @param mixed $operator
     * @param mixed $value
     *
     * @return $this
     */
    public function orWhere($column, $operator = null, $value = null)
    {
        return $this->where($column, $operator, $value, 'or');
    }

    /**
     * Ajoute une clause "where not" à la requête.
     *
     * @param string|array|\Closure $column
     * @param mixed $operator
     * @param mixed $value
     * @param string $boolean
     *
     * @return $this
     */
    public function whereNot($column, $operator = null, $value = null, $boolean = 'and')
    {
        // Si la colonne est un tableau, on suppose qu'il s'agit d'un tableau de paires clé-valeur
        if (is_array($column)) {
            return $this->whereNotArray($column, $boolean);
        }

        // En cas de closure, nous créerons une instruction where not imbriquée
        if ($column instanceof \Closure) {
            return $this->whereNotNested($column, $boolean);
        }

        return $this->where($column, $operator, $value, "{$boolean} not");
    }

    /**
     * Ajoute un tableau de clauses where not à la requête.
     *
     * @param array $wheres
     * @param string $boolean
     *
     * @return $this
     */
    protected function whereNotArray($wheres, $boolean = 'and')
    {
        foreach ($wheres as $column => $value) {
            $this->whereNot($column, '=', $value, $boolean);
        }

        return $this;
    }

    /**
     * Ajoute une instruction where not imbriquée à la requête.
     *
     * @param string $boolean
     *
     * @return $this
     */
    public function whereNotNested(\Closure $callback, $boolean = 'and')
    {
        $builder = new static($this->csvClient);
        $builder->setModel($this->model);

        call_user_func($callback, $builder);

        $this->wheres[] = [
            'type' => 'not_nested',
            'query' => $builder->wheres,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Ajoute une clause "where in" à la requête.
     *
     * @param \Illuminate\Contracts\Database\Query\Expression|string $column
     * @param mixed $values
     * @param string $boolean
     * @param bool $not
     *
     * @return $this
     */
    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        $operator = $not ? '$notIn' : '$in';

        $this->wheres[] = [
            'column' => $this->mapColumnToField($column),
            'operator' => $operator,
            'value' => is_array($values) ? $values : [$values],
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Ajoute une clause "or where in" à la requête.
     *
     * @param \Illuminate\Contracts\Database\Query\Expression|string $column
     * @param mixed $values
     *
     * @return $this
     */
    public function orWhereIn($column, $values)
    {
        return $this->whereIn($column, $values, 'or', false);
    }

    /**
     * Ajoute une clause "where not in" à la requête.
     *
     * @param \Illuminate\Contracts\Database\Query\Expression|string $column
     * @param mixed $values
     * @param string $boolean
     *
     * @return $this
     */
    public function whereNotIn($column, $values, $boolean = 'and')
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    /**
     * Ajoute une clause "or where not in" à la requête.
     *
     * @param \Illuminate\Contracts\Database\Query\Expression|string $column
     * @param mixed $values
     *
     * @return $this
     */
    public function orWhereNotIn($column, $values)
    {
        return $this->whereIn($column, $values, 'or', true);
    }

    /**
     * Ajoute une clause "where between" à la requête.
     *
     * @param \Illuminate\Contracts\Database\Query\Expression|string $column
     * @param string $boolean
     * @param bool $not
     *
     * @return $this
     */
    public function whereBetween($column, iterable $values, $boolean = 'and', $not = false)
    {
        $valuesArray = is_array($values) ? $values : iterator_to_array($values);

        if ($not) {
            $this->wheres[] = [
                'column' => $this->mapColumnToField($column),
                'operator' => '$not',
                'value' => [
                    'operator' => '$between',
                    'value' => $valuesArray,
                ],
                'boolean' => $boolean,
            ];
        } else {
            $this->wheres[] = [
                'column' => $this->mapColumnToField($column),
                'operator' => '$between',
                'value' => $valuesArray,
                'boolean' => $boolean,
            ];
        }

        return $this;
    }

    /**
     * Ajoute une clause "or where between" à la requête.
     *
     * @param \Illuminate\Contracts\Database\Query\Expression|string $column
     *
     * @return $this
     */
    public function orWhereBetween($column, iterable $values)
    {
        return $this->whereBetween($column, $values, 'or', false);
    }

    /**
     * Ajoute une clause "where not between" à la requête.
     *
     * @param \Illuminate\Contracts\Database\Query\Expression|string $column
     * @param string $boolean
     *
     * @return $this
     */
    public function whereNotBetween($column, iterable $values, $boolean = 'and')
    {
        return $this->whereBetween($column, $values, $boolean, true);
    }

    /**
     * Ajoute une clause "or where not between" à la requête.
     *
     * @param \Illuminate\Contracts\Database\Query\Expression|string $column
     *
     * @return $this
     */
    public function orWhereNotBetween($column, iterable $values)
    {
        return $this->whereBetween($column, $values, 'or', true);
    }

    /**
     * Ajoute une clause "where null" à la requête.
     *
     * @param \Illuminate\Contracts\Database\Query\Expression|string $column
     * @param string $boolean
     * @param bool $not
     *
     * @return $this
     */
    public function whereNull($column, $boolean = 'and', $not = false)
    {
        $operator = $not ? 'is not null' : 'is null';

        $this->wheres[] = [
            'column' => $this->mapColumnToField($column),
            'operator' => $operator,
            'value' => null,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Ajoute une clause "or where null" à la requête.
     *
     * @param \Illuminate\Contracts\Database\Query\Expression|string $column
     *
     * @return $this
     */
    public function orWhereNull($column)
    {
        return $this->whereNull($column, 'or', false);
    }

    /**
     * Ajoute une clause "where not null" à la requête.
     *
     * @param \Illuminate\Contracts\Database\Query\Expression|string $column
     * @param string $boolean
     *
     * @return $this
     */
    public function whereNotNull($column, $boolean = 'and')
    {
        return $this->whereNull($column, $boolean, true);
    }

    /**
     * Ajoute une clause "or where not null" à la requête.
     *
     * @param \Illuminate\Contracts\Database\Query\Expression|string $column
     *
     * @return $this
     */
    public function orWhereNotNull($column)
    {
        return $this->whereNull($column, 'or', true);
    }

    /**
     * Ajoute une clause where pour JSON contains à la requête.
     *
     *
     * @param string $column
     * @param mixed $value
     * @param string $boolean
     * @param false $not
     *
     * @return $this
     */
    public function whereJsonContains($column, $value, $boolean = 'and', $not = false)
    {
        $this->wheres[] = [
            'column' => $this->mapColumnToField($column),
            'operator' => '$contains',
            'value' => $value,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Ajoute une clause where pour JSON contains key à la requête.
     *
     * @param string $column
     * @param string $boolean
     * @param bool $not
     *
     * @return $this
     */
    public function whereJsonContainsKey($column, $boolean = 'and', $not = false)
    {
        // Pour l'API CSV, nous utiliserons une approche similaire en utilisant l'opérateur contains
        $operator = $not ? '$notContains' : '$contains';

        $this->wheres[] = [
            'column' => $this->mapColumnToField($column),
            'operator' => $operator,
            'value' => null, // Adapté pour vérifier juste l'existence de la clé
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Ajoute une clause "where date" à la requête.
     *
     * @param string $column
     * @param string $operator
     * @param mixed $value
     * @param string $boolean
     *
     * @return $this
     */
    public function whereDate($column, $operator, $value = null, $boolean = 'and')
    {
        if (func_num_args() === 2) {
            [$value, $operator] = [$operator, '='];
        }

        // Formate la valeur de date si nécessaire
        if ($value instanceof \DateTime) {
            $value = $value->format('Y-m-d');
        }

        return $this->where($column, $operator, $value, $boolean);
    }

    /**
     * Ajoute une clause "order by" à la requête.
     *
     * @param string $column
     * @param string $direction
     *
     * @return $this
     */
    public function orderBy($column, $direction = 'asc')
    {
        $this->orders[] = [
            'column' => $this->mapColumnToField($column),
            'direction' => strtolower($direction) === 'asc' ? 'asc' : 'desc',
        ];

        return $this;
    }

    /**
     * Ajoute une clause "order by" descendante à la requête.
     *
     * @param string $column
     *
     * @return $this
     */
    public function orderByDesc($column)
    {
        return $this->orderBy($column, 'desc');
    }

    /**
     * Définit la valeur "offset" de la requête.
     *
     * @param int $value
     *
     * @return $this
     */
    public function offset($value)
    {
        $this->offset = max(0, $value);

        return $this;
    }

    /**
     * Alias pour limit(), pour compatibilité Eloquent.
     *
     * @param int $value
     *
     * @return $this
     */
    public function take($value)
    {
        return $this->limit($value);
    }

    /**
     * Définit la valeur "limit" de la requête.
     *
     * @param int $value
     *
     * @return $this
     */
    public function limit($value)
    {
        if ($value >= 0) {
            $this->limit = $value;
        }

        return $this;
    }

    /**
     * Définit la limite et l'offset pour une page donnée.
     *
     * @param int $page
     * @param int $perPage
     *
     * @return $this
     */
    public function forPage($page, $perPage = 15)
    {
        return $this->offset(($page - 1) * $perPage)->limit($perPage);
    }

    /**
     * Ajoute une clause "group by" à la requête.
     *
     * @param array|string $groups
     *
     * @return $this
     */
    public function groupBy(...$groups)
    {
        foreach (is_array($groups) ? $groups : func_get_args() as $group) {
            $this->groups[] = $this->mapColumnToField($group);
        }

        return $this;
    }

    /**
     * Ajoute une clause "having" à la requête.
     *
     * @param string $column
     * @param string|null $operator
     * @param string|null $value
     * @param string $boolean
     *
     * @return $this
     */
    public function having($column, $operator = null, $value = null, $boolean = 'and')
    {
        // Having n'est pas directement pris en charge dans l'API, donc nous le gérerons manuellement
        // en filtrant les résultats après les avoir obtenus
        $this->havings[] = [
            'column' => $this->mapColumnToField($column),
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Récupère le résultat "count" de la requête.
     *
     * @param string $columns
     *
     * @return int
     */
    public function count($columns = '*')
    {
        return $this->get($columns)->count();
    }

    /**
     * Mappe un opérateur Laravel à un opérateur API.
     *
     * @param string $operator
     *
     * @return string
     */
    protected function mapOperator($operator)
    {
        $map = [
            '=' => '$eq',
            '!=' => '$ne',
            '<>' => '$ne',
            '>' => '$gt',
            '>=' => '$gte',
            '<' => '$lt',
            '<=' => '$lte',
            'like' => '$contains',
            'ilike' => '$icontains',
            'is null' => 'is null',
            'is not null' => 'is not null',
        ];

        return $map[$operator] ?? $operator;
    }

    /**
     * Mappe un nom de colonne au nom de champ CSV.
     *
     * @param string $column
     *
     * @return string
     */
    protected function mapColumnToField($column)
    {
        if ($this->model) {
            return $this->model->mapColumnToField($column);
        }

        return $column;
    }

    /**
     * Construit les paramètres API basés sur la requête.
     *
     * @return array
     */
    protected function buildApiParameters()
    {
        $params = [];

        // Gère les filtres
        if (! empty($this->wheres)) {
            $params['filters'] = $this->buildFilters($this->wheres);
        }

        // Gère l'ordre
        if (! empty($this->orders)) {
            $sortParts = [];
            foreach ($this->orders as $order) {
                $sortParts[] = $order['column'].':'.$order['direction'];
            }
            $params['sort'] = implode(',', $sortParts);
        }

        // Gère la pagination
        if ($this->limit !== null) {
            $params['pagination'] = [
                'limit' => $this->limit,
            ];

            if ($this->offset !== null) {
                $params['pagination']['start'] = $this->offset;
            } else {
                $params['pagination']['page'] = 1;
                $params['pagination']['pageSize'] = $this->limit;
            }

            // Ajoute le flag withCount pour le comptage total des enregistrements
            $params['pagination']['withCount'] = true;
        }

        return $params;
    }

    /**
     * Construit les filtres API basés sur les clauses where de la requête.
     *
     * @return array
     */
    protected function buildFilters(array $wheres)
    {
        $filters = [];

        // Gère les suppressions logiques
        if ($this->model && $this->model->usesSoftDeletes()) {
            if ($this->onlyTrashed) {
                $wheres[] = [
                    'column' => $this->model::DELETED_AT,
                    'operator' => 'is not null',
                    'value' => null,
                    'boolean' => 'and',
                ];
            } elseif (! $this->withTrashed) {
                $wheres[] = [
                    'column' => $this->model::DELETED_AT,
                    'operator' => 'is null',
                    'value' => null,
                    'boolean' => 'and',
                ];
            }
        }

        // Traite les clauses where
        foreach ($wheres as $where) {
            if (isset($where['type']) && $where['type'] === 'nested') {
                $nestedFilters = $this->buildFilters($where['query']);

                if ($where['boolean'] === 'and') {
                    $filters['$and'][] = $nestedFilters;
                } else {
                    $filters['$or'][] = $nestedFilters;
                }
            } elseif (isset($where['type']) && $where['type'] === 'not_nested') {
                $nestedFilters = $this->buildFilters($where['query']);
                $filters['$not'][] = $nestedFilters;
            } else {
                $column = $where['column'];
                $operator = $where['operator'];
                $value = $where['value'];
                $boolean = $where['boolean'];

                // Gère les opérateurs spéciaux
                if ($operator === 'is null') {
                    $filters[$column]['$eq'] = null;
                } elseif ($operator === 'is not null') {
                    $filters[$column]['$ne'] = null;
                } else {
                    // Détermine l'opérateur booléen
                    if (strpos($boolean, 'not') !== false) {
                        $filters[$column]['$not'] = [$operator => $value];
                    } elseif ($boolean === 'or') {
                        if (! isset($filters['$or'])) {
                            $filters['$or'] = [];
                        }
                        $filters['$or'][] = [$column => [$operator => $value]];
                    } else {
                        $filters[$column][$operator] = $value;
                    }
                }
            }
        }

        return $filters;
    }

    /**
     * Exécute la requête et obtient le premier résultat.
     *
     * @param array $columns
     *
     * @return ModelCSV|null
     */
    public function first($columns = ['*'])
    {
        return $this->limit(1)->get($columns)->first();
    }

    /**
     * Exécute la requête et obtient le premier résultat ou lance une exception.
     *
     * @param array $columns
     *
     * @return ModelCSV
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function firstOrFail($columns = ['*'])
    {
        $result = $this->first($columns);

        if (! $result) {
            throw (new ModelNotFoundException)->setModel(
                get_class($this->model)
            );
        }

        return $result;
    }

    /**
     * Trouve un modèle par sa clé primaire.
     *
     * @param mixed $id
     * @param array $columns
     *
     * @return ModelCSV|null
     */
    public function find($id, $columns = ['*'])
    {
        if ($this->model) {
            return $this->where($this->model->getKeyName(), '=', $id)->first($columns);
        }

        return null;
    }

    /**
     * Exécute la requête et obtient les résultats.
     *
     * @param array $columns
     *
     * @return Collection
     */
    public function get($columns = ['*'])
    {
        try {
            $csvFile = $this->model->getCsvFile();
            $params = $this->buildApiParameters();

            // Debug
            if (config('csv-eloquent.debug', false) && app()->bound('log')) {
                Log::debug("Builder::get - Récupération des données pour $csvFile\n");
            }

            $response = $this->csvClient->getData($csvFile, $params);

            $records = $response['data'] ?? [];

            // Debug
            if (config('csv-eloquent.debug', false) && app()->bound('log')) {
                Log::debug("Builder::get - Nombre d'enregistrements récupérés: ".count($records));
            }
            if (! empty($records)) {
                if (config('csv-eloquent.debug', false) && app()->bound('log')) {
                    Log::debug("Builder::get - Premier enregistrement:\n");
                }
                if (config('csv-eloquent.debug', false) && app()->bound('log')) {
                    Log::debug("\n");
                }
            }

            return $this->processRecords($records, $columns);
        } catch (\Exception $e) {
            if (config('csv-eloquent.debug', false) && app()->bound('log')) {
                Log::debug('ERREUR dans Builder::get: '.$e->getMessage());
            }

            return new Collection;
        }
    }

    /**
     * Traite la réponse API et la convertit en une collection de modèles.
     *
     * @param array $records Array of records from API
     * @param array $columns Columns to select
     *
     * @return Collection
     */
    protected function processRecords(array $records, array $columns = ['*'])
    {
        if (config('csv-eloquent.debug', false) && app()->bound('log')) {
            Log::debug('processRecords - Début avec '.count($records)." enregistrements\n");
        }

        $models = [];
        $recordCount = 0;

        foreach ($records as $index => $record) {
            $recordCount++;
            if (config('csv-eloquent.debug', false) && app()->bound('log')) {
                Log::debug("Traitement de l'enregistrement #$recordCount...\n");
            }

            try {
                // Vérifier que record est bien un tableau
                if (! is_array($record)) {
                    if (config('csv-eloquent.debug', false) && app()->bound('log')) {
                        Log::debug("ATTENTION: L'enregistrement #$recordCount n'est pas un tableau\n");
                    }

                    continue;
                }

                // Créer une nouvelle instance du modèle
                if (config('csv-eloquent.debug', false) && app()->bound('log')) {
                    Log::debug("Création d'une nouvelle instance de modèle...\n");
                }
                $model = $this->model->newInstance();
                $model->exists = true;  // Marquer comme existant

                // Convertit les champs API en attributs de modèle
                foreach ($record as $field => $value) {
                    // Mapper le nom du champ API vers l'attribut du modèle
                    $attribute = $this->model->mapFieldToColumn($field);

                    if ($index === 0) {
                        if (config('csv-eloquent.debug', false) && app()->bound('log')) {
                            Log::debug("Attribution: $field => $attribute = ".(is_string($value) ? $value : gettype($value)));
                        }
                    }

                    try {
                        // Utiliser la méthode fill au lieu de manipuler attributes directement
                        $model->fillAttribute($attribute, $value);
                    } catch (\Exception $e) {
                        if (config('csv-eloquent.debug', false) && app()->bound('log')) {
                            Log::debug("ERREUR lors de l'attribution de {$attribute}: ".$e->getMessage());
                        }
                    }
                }

                $models[] = $model;
            } catch (\Exception $e) {
                if (config('csv-eloquent.debug', false) && app()->bound('log')) {
                    Log::debug("EXCEPTION lors du traitement de l'enregistrement #{$recordCount}: ".$e->getMessage());
                }
            }
        }

        if (config('csv-eloquent.debug', false) && app()->bound('log')) {
            Log::debug('processRecords - Modèles créés: '.count($models));
        }

        // Vérifier le premier modèle
        if (! empty($models)) {
            if (config('csv-eloquent.debug', false) && app()->bound('log')) {
                Log::debug('Premier modèle: '.get_class($models[0]));
            }

            $firstModel = $models[0];
            if (config('csv-eloquent.debug', false) && app()->bound('log')) {
                Log::debug('ID du premier modèle: '.$firstModel->getKey());
            }
            if (config('csv-eloquent.debug', false) && app()->bound('log')) {
                Log::debug('Status du premier modèle: '.$firstModel->getAttribute('status'));
            }
        }

        // Crée une collection de modèles
        $collection = $this->model->newCollection($models);

        if (config('csv-eloquent.debug', false) && app()->bound('log')) {
            Log::debug('Collection créée avec '.$collection->count()." éléments\n");
        }

        // Applique les clauses having si nécessaire
        if (! empty($this->havings)) {
            $collection = $this->applyHavingClauses($collection);
        }

        return $collection;
    }

    /**
     * Applique les clauses having à la collection.
     *
     * @return Collection
     */
    protected function applyHavingClauses(Collection $collection)
    {
        foreach ($this->havings as $having) {
            $column = $having['column'];
            $operator = $having['operator'];
            $value = $having['value'];
            $boolean = $having['boolean'];

            $filtered = $collection->filter(function ($model) use ($column, $operator, $value) {
                $attribute = $model->getAttribute($column);

                switch ($operator) {
                    case '=':
                        return $attribute == $value;
                    case '!=':
                    case '<>':
                        return $attribute != $value;
                    case '>':
                        return $attribute > $value;
                    case '>=':
                        return $attribute >= $value;
                    case '<':
                        return $attribute < $value;
                    case '<=':
                        return $attribute <= $value;
                    case 'like':
                        return strpos($attribute, str_replace('%', '', $value)) !== false;
                    default:
                        return true;
                }
            });

            $collection = $boolean === 'or' ? $collection->merge($filtered) : $filtered;
        }

        return $collection;
    }

    /**
     * Pagine la requête donnée.
     *
     *
     * @param int $perPage
     * @param array|string|string[] $columns
     * @param string $pageName
     * @param null $page
     * @param null $total
     *
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function paginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null, $total = null)
    {
        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $this->forPage($page, $perPage);

        $results = $this->get($columns);

        try {
            $csvFile = $this->model->getCsvFile();
            $params = $this->buildApiParameters();

            $response = $this->csvClient->getData($csvFile, $params);

            $total = $response['meta']['pagination']['totalRecords'] ?? count($results);

            return new LengthAwarePaginator(
                $results,
                $total,
                $perPage,
                $page,
                [
                    'path' => Paginator::resolveCurrentPath(),
                    'pageName' => $pageName,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Échec de la récupération des métadonnées de pagination depuis l\'API CSV', [
                'exception' => $e->getMessage(),
                'file' => $this->model->getCsvFile(),
            ]);

            return new LengthAwarePaginator(
                $results,
                count($results),
                $perPage,
                $page,
                [
                    'path' => Paginator::resolveCurrentPath(),
                    'pageName' => $pageName,
                ]
            );
        }
    }

    /**
     * Pagine la requête donnée en un paginateur simple.
     *
     * @param int $perPage
     * @param array $columns
     * @param string $pageName
     * @param int|null $page
     *
     * @return \Illuminate\Pagination\Paginator
     */
    public function simplePaginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null)
    {
        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $this->forPage($page, $perPage);

        $results = $this->get($columns);

        return new Paginator(
            $results,
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ]
        );
    }

    /**
     * Pagine la requête donnée en un paginateur de curseur.
     *
     * @param int $perPage
     * @param array $columns
     * @param string $cursorName
     * @param string|null $cursor
     *
     * @return CursorPaginator
     */
    public function cursorPaginate($perPage = 15, $columns = ['*'], $cursorName = 'cursor', $cursor = null)
    {
        // La pagination par curseur est plus complexe avec l'API CSV,
        // mais nous pouvons implémenter une version de base
        $cursor = $cursor ?: CursorPaginator::resolveCurrentCursor($cursorName);

        if ($cursor) {
            $cursorParams = json_decode(base64_decode($cursor), true);
            $this->where($this->model->getKeyName(), '>', $cursorParams['_id']);
        }

        $this->limit($perPage);

        $results = $this->get($columns);

        return new CursorPaginator(
            $results,
            $perPage,
            $cursor,
            [
                'path' => Paginator::resolveCurrentPath(),
                'cursorName' => $cursorName,
                'parameters' => [$this->model->getKeyName()],
            ]
        );
    }

    /**
     * Inclut les enregistrements supprimés logiquement dans les résultats.
     *
     * @return $this
     */
    public function withTrashed()
    {
        $this->withTrashed = true;

        return $this;
    }

    /**
     * Obtient uniquement les enregistrements supprimés logiquement.
     *
     * @return $this
     */
    public function onlyTrashed()
    {
        $this->onlyTrashed = true;

        return $this;
    }

    /**
     * Obtient une instance de constructeur de requêtes de base.
     *
     * @return $this
     */
    public function toBase()
    {
        return $this;
    }

    /**
     * Permet d'exécuter une fonction sur le builder et de retourner le builder.
     *
     * @return $this
     */
    public function tap(callable $callback)
    {
        $callback($this);

        return $this;
    }

    /**
     * Exécute le callback lorsque la condition est vraie.
     *
     * @param mixed $value
     *
     * @return $this
     */
    public function when($value, ?callable $callback = null, ?callable $default = null)
    {
        if ($value) {
            return $callback($this, $value) ?: $this;
        } elseif ($default) {
            return $default($this, $value) ?: $this;
        }

        return $this;
    }

    /**
     * Exécute le callback lorsque la condition est fausse.
     *
     * @param mixed $value
     *
     * @return $this
     */
    public function unless($value, callable $callback, ?callable $default = null)
    {
        return $this->when(! $value, $callback, $default);
    }

    /**
     * Vérifie si la requête retourne au moins un enregistrement.
     *
     * @return bool
     */
    public function exists()
    {
        return $this->get()->isNotEmpty();
    }

    /**
     * Vérifie si la requête ne retourne aucun enregistrement.
     *
     * @return bool
     */
    public function doesntExist()
    {
        return ! $this->exists();
    }

    /**
     * Récupère une collection des valeurs d'une colonne.
     *
     * @param string $column
     * @param string|null $key
     *
     * @return Collection
     */
    public function pluck($column, $key = null)
    {
        return $this->get([$column])->pluck($column, $key);
    }

    /**
     * Insère un nouvel enregistrement dans la base de données.
     *
     * @return bool
     */
    public function insert(array $values)
    {
        // Non implémenté pour CSV
        throw new \BadMethodCallException('La méthode insert() n\'est pas supportée pour les CSV');
    }

    /**
     * Insère un nouvel enregistrement et récupère l'ID.
     *
     * @param string|null $sequence
     *
     * @return int
     */
    public function insertGetId(array $values, $sequence = null)
    {
        // Non implémenté pour CSV
        throw new \BadMethodCallException('La méthode insertGetId() n\'est pas supportée pour les CSV');
    }

    /**
     * Insère ou ignore un enregistrement.
     *
     * @return int
     */
    public function insertOrIgnore(array $values)
    {
        // Non implémenté pour CSV
        throw new \BadMethodCallException('La méthode insertOrIgnore() n\'est pas supportée pour les CSV');
    }

    /**
     * Insère en utilisant une sous-requête.
     *
     * @param \Closure|\Illuminate\Database\Query\Builder|string $query
     *
     * @return int
     */
    public function insertUsing(array $columns, $query)
    {
        // Non implémenté pour CSV
        throw new \BadMethodCallException('La méthode insertUsing() n\'est pas supportée pour les CSV');
    }

    /**
     * Met à jour des enregistrements dans la base de données.
     *
     * @return int
     */
    public function update(array $values)
    {
        // Non implémenté pour CSV
        throw new \BadMethodCallException('La méthode update() n\'est pas supportée pour les CSV');
    }

    /**
     * Met à jour ou insère un enregistrement.
     *
     * @return bool
     */
    public function updateOrInsert(array $attributes, $values = [])
    {
        // Non implémenté pour CSV
        throw new \BadMethodCallException('La méthode updateOrInsert() n\'est pas supportée pour les CSV');
    }

    /**
     * Supprime des enregistrements de la base de données.
     *
     * @param mixed $id
     *
     * @return int
     */
    public function delete($id = null)
    {
        // Non implémenté pour CSV
        throw new \BadMethodCallException('La méthode delete() n\'est pas supportée pour les CSV');
    }

    /**
     * Vide la table.
     *
     * @return bool
     */
    public function truncate()
    {
        // Non implémenté pour CSV
        throw new \BadMethodCallException('La méthode truncate() n\'est pas supportée pour les CSV');
    }

    /**
     * Crée une expression SQL brute.
     *
     * @param mixed $value
     *
     * @return \Illuminate\Database\Query\Expression
     */
    public function raw($value)
    {
        // Non implémenté pour CSV, mais retourne la valeur pour compatibilité
        return $value;
    }

    /**
     * Exécute une fonction d'agrégation sur la requête.
     *
     * @param string $function
     * @param array $columns
     *
     * @return mixed
     */
    public function aggregate($function, $columns = ['*'])
    {
        $results = $this->get($columns);

        if ($function === 'count') {
            return $results->count();
        }

        if (empty($results)) {
            return 0;
        }

        // Récupère la valeur de la colonne pour chaque élément
        $column = isset($columns[0]) && $columns[0] !== '*' ? $columns[0] : null;
        if ($column) {
            $results = $results->pluck($column);
        }

        switch ($function) {
            case 'sum':
                return $results->sum();
            case 'avg':
            case 'average':
                return $results->avg();
            case 'min':
                return $results->min();
            case 'max':
                return $results->max();
        }

        return 0;
    }

    /**
     * Récupère la somme des valeurs.
     *
     * @param string $column
     *
     * @return mixed
     */
    public function sum($column)
    {
        return $this->aggregate('sum', [$column]);
    }

    /**
     * Récupère la moyenne des valeurs.
     *
     * @param string $column
     *
     * @return mixed
     */
    public function avg($column)
    {
        return $this->aggregate('avg', [$column]);
    }

    /**
     * Alias pour la méthode avg().
     *
     * @param string $column
     *
     * @return mixed
     */
    public function average($column)
    {
        return $this->avg($column);
    }

    /**
     * Récupère la valeur maximale.
     *
     * @param string $column
     *
     * @return mixed
     */
    public function max($column)
    {
        return $this->aggregate('max', [$column]);
    }

    /**
     * Récupère la valeur minimale.
     *
     * @param string $column
     *
     * @return mixed
     */
    public function min($column)
    {
        return $this->aggregate('min', [$column]);
    }

    /**
     * Ajoute une clause where brute.
     *
     * @param string $sql
     * @param mixed $bindings
     * @param string $boolean
     *
     * @return $this
     */
    public function whereRaw($sql, $bindings = [], $boolean = 'and')
    {
        // Non implémenté pour CSV
        throw new \BadMethodCallException('La méthode whereRaw() n\'est pas supportée pour les CSV');
    }

    /**
     * Ajoute une clause or where brute.
     *
     * @param string $sql
     * @param mixed $bindings
     *
     * @return $this
     */
    public function orWhereRaw($sql, $bindings = [])
    {
        // Non implémenté pour CSV
        throw new \BadMethodCallException('La méthode orWhereRaw() n\'est pas supportée pour les CSV');
    }

    /**
     * Ajoute une clause union all.
     *
     * @param \Illuminate\Database\Query\Builder|\Closure $query
     *
     * @return $this
     */
    public function unionAll($query)
    {
        // Non implémenté pour CSV
        throw new \BadMethodCallException('La méthode unionAll() n\'est pas supportée pour les CSV');
    }

    /**
     * Ajoute une clause union.
     *
     * @param \Illuminate\Database\Query\Builder|\Closure $query
     * @param bool $all
     *
     * @return $this
     */
    public function union($query, $all = false)
    {
        // Non implémenté pour CSV
        throw new \BadMethodCallException('La méthode union() n\'est pas supportée pour les CSV');
    }

    /**
     * Force la requête à retourner des valeurs distinctes.
     *
     * @param string|array|\Illuminate\Contracts\Database\Query\Expression $column
     *
     * @return $this
     */
    public function distinct($column = true)
    {
        // Pour l'implémentation CSV, nous le gérerons au moment de l'exécution de la requête
        return $this;
    }

    /**
     * Ajoute une clause join à la requête.
     *
     * @param string $table
     * @param \Closure|string $first
     * @param string|null $operator
     * @param string|null $second
     * @param string $type
     * @param bool $where
     *
     * @return $this
     */
    public function join($table, $first, $operator = null, $second = null, $type = 'inner', $where = false)
    {
        // Non implémenté pour CSV
        throw new \BadMethodCallException('La méthode join() n\'est pas supportée pour les CSV');
    }

    /**
     * Ajoute une clause left join à la requête.
     *
     * @param string $table
     * @param \Closure|string $first
     * @param string|null $operator
     * @param string|null $second
     *
     * @return $this
     */
    public function leftJoin($table, $first, $operator = null, $second = null)
    {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    /**
     * Ajoute une clause right join à la requête.
     *
     * @param string $table
     * @param \Closure|string $first
     * @param string|null $operator
     * @param string|null $second
     *
     * @return $this
     */
    public function rightJoin($table, $first, $operator = null, $second = null)
    {
        return $this->join($table, $first, $operator, $second, 'right');
    }

    /**
     * Ajoute une clause cross join à la requête.
     *
     * @param string $table
     * @param \Closure|string|null $first
     * @param string|null $operator
     * @param string|null $second
     *
     * @return $this
     */
    public function crossJoin($table, $first = null, $operator = null, $second = null)
    {
        return $this->join($table, $first, $operator, $second, 'cross');
    }

    /**
     * Fusionne les clauses where.
     *
     * @param array $wheres
     * @param array $bindings
     *
     * @return void
     */
    public function mergeWheres($wheres, $bindings)
    {
        // Non implémenté pour CSV
        throw new \BadMethodCallException('La méthode mergeWheres() n\'est pas supportée pour les CSV');
    }

    /**
     * Ajoute une clause where exists.
     *
     * @param \Closure $callback
     * @param string $boolean
     * @param bool $not
     *
     * @return $this
     */
    public function whereExists($callback, $boolean = 'and', $not = false)
    {
        // Non implémenté pour CSV
        throw new \BadMethodCallException('La méthode whereExists() n\'est pas supportée pour les CSV');
    }

    /**
     * Ajoute une clause or where exists.
     *
     * @param \Closure $callback
     * @param bool $not
     *
     * @return $this
     */
    public function orWhereExists($callback, $not = false)
    {
        return $this->whereExists($callback, 'or', $not);
    }

    /**
     * Ajoute une clause where not exists.
     *
     * @param \Closure $callback
     * @param string $boolean
     *
     * @return $this
     */
    public function whereNotExists($callback, $boolean = 'and')
    {
        return $this->whereExists($callback, $boolean, true);
    }

    /**
     * Ajoute une clause or where not exists.
     *
     * @param \Closure $callback
     *
     * @return $this
     */
    public function orWhereNotExists($callback)
    {
        return $this->orWhereExists($callback, true);
    }
}
