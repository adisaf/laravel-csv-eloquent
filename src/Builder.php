<?php

namespace Adisaf\CsvEloquent;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Adisaf\CsvEloquent\Models\ModelCSV;

class Builder
{
    /**
     * Le modèle interrogé.
     * @var ModelCSV
     */
    protected $model;

    /**
     * L'instance du client API CSV.
     *
     * @var \App\Models\Csv\CsvClient
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
     * @param \App\Models\Csv\CsvClient $csvClient
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
     * @return \App\Models\ModelCSV
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

    /**
     * Ajoute un tableau de clauses where à la requête.
     *
     * @param array $wheres
     * @param string $boolean
     *
     * @return $this
     */
    protected function addArrayOfWheres($wheres, $boolean = 'and')
    {
        foreach ($wheres as $column => $value) {
            $this->where($column, '=', $value, $boolean);
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
     * @param string $column
     * @param mixed $values
     * @param string $boolean
     *
     * @return $this
     */
    public function whereIn($column, $values, $boolean = 'and')
    {
        $this->wheres[] = [
            'column' => $this->mapColumnToField($column),
            'operator' => '$in',
            'value' => is_array($values) ? $values : [$values],
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Ajoute une clause "or where in" à la requête.
     *
     * @param string $column
     * @param mixed $values
     *
     * @return $this
     */
    public function orWhereIn($column, $values)
    {
        return $this->whereIn($column, $values, 'or');
    }

    /**
     * Ajoute une clause "where not in" à la requête.
     *
     * @param string $column
     * @param mixed $values
     * @param string $boolean
     *
     * @return $this
     */
    public function whereNotIn($column, $values, $boolean = 'and')
    {
        $this->wheres[] = [
            'column' => $this->mapColumnToField($column),
            'operator' => '$notIn',
            'value' => is_array($values) ? $values : [$values],
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Ajoute une clause "or where not in" à la requête.
     *
     * @param string $column
     * @param mixed $values
     *
     * @return $this
     */
    public function orWhereNotIn($column, $values)
    {
        return $this->whereNotIn($column, $values, 'or');
    }

    /**
     * Ajoute une clause "where between" à la requête.
     *
     * @param string $column
     * @param string $boolean
     *
     * @return $this
     */
    public function whereBetween($column, array $values, $boolean = 'and')
    {
        $this->wheres[] = [
            'column' => $this->mapColumnToField($column),
            'operator' => '$between',
            'value' => $values,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Ajoute une clause "or where between" à la requête.
     *
     * @param string $column
     *
     * @return $this
     */
    public function orWhereBetween($column, array $values)
    {
        return $this->whereBetween($column, $values, 'or');
    }

    /**
     * Ajoute une clause "where not between" à la requête.
     *
     * @param string $column
     * @param string $boolean
     *
     * @return $this
     */
    public function whereNotBetween($column, array $values, $boolean = 'and')
    {
        $this->wheres[] = [
            'column' => $this->mapColumnToField($column),
            'operator' => '$not',
            'value' => [
                'operator' => '$between',
                'value' => $values,
            ],
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Ajoute une clause "or where not between" à la requête.
     *
     * @param string $column
     *
     * @return $this
     */
    public function orWhereNotBetween($column, array $values)
    {
        return $this->whereNotBetween($column, $values, 'or');
    }

    /**
     * Ajoute une clause "where null" à la requête.
     *
     * @param string $column
     * @param string $boolean
     *
     * @return $this
     */
    public function whereNull($column, $boolean = 'and')
    {
        $this->wheres[] = [
            'column' => $this->mapColumnToField($column),
            'operator' => 'is null',
            'value' => null,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Ajoute une clause "or where null" à la requête.
     *
     * @param string $column
     *
     * @return $this
     */
    public function orWhereNull($column)
    {
        return $this->whereNull($column, 'or');
    }

    /**
     * Ajoute une clause "where not null" à la requête.
     *
     * @param string $column
     * @param string $boolean
     *
     * @return $this
     */
    public function whereNotNull($column, $boolean = 'and')
    {
        $this->wheres[] = [
            'column' => $this->mapColumnToField($column),
            'operator' => 'is not null',
            'value' => null,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Ajoute une clause "or where not null" à la requête.
     *
     * @param string $column
     *
     * @return $this
     */
    public function orWhereNotNull($column)
    {
        return $this->whereNotNull($column, 'or');
    }

    /**
     * Ajoute une clause where pour JSON contains à la requête.
     *
     * @param string $column
     * @param mixed $value
     * @param string $boolean
     *
     * @return $this
     */
    public function whereJsonContains($column, $value, $boolean = 'and')
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
     * @param string $key
     * @param string $boolean
     *
     * @return $this
     */
    public function whereJsonContainsKey($column, $key, $boolean = 'and')
    {
        // Pour l'API CSV, nous utiliserons une approche similaire en utilisant l'opérateur contains
        $this->wheres[] = [
            'column' => $this->mapColumnToField($column),
            'operator' => '$contains',
            'value' => $key,
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
    public function groupBy($groups)
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
        $result = $this->get($columns);

        return $result->count();
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
        if (!empty($this->wheres)) {
            $params['filters'] = $this->buildFilters($this->wheres);
        }

        // Gère l'ordre
        if (!empty($this->orders)) {
            $sortParts = [];
            foreach ($this->orders as $order) {
                $sortParts[] = $order['column'] . ':' . $order['direction'];
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
            } elseif (!$this->withTrashed) {
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
                        if (!isset($filters['$or'])) {
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
     * @return \App\Models\ModelCSV|null
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
     * @return \App\Models\ModelCSV
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function firstOrFail($columns = ['*'])
    {
        $result = $this->first($columns);

        if (!$result) {
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
     * @return \App\Models\ModelCSV|null
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
     * @return \Illuminate\Support\Collection
     */
    public function get($columns = ['*'])
    {
        try {
            $csvFile = $this->model->getCsvFile();
            $params = $this->buildApiParameters();

            $response = $this->csvClient->getData($csvFile, $params);

            $records = $response['data'] ?? [];

            return $this->processRecords($records, $columns);
        } catch (\Exception $e) {
            Log::error('Échec de la récupération des données depuis l\'API CSV', [
                'exception' => $e->getMessage(),
                'file' => $this->model->getCsvFile(),
            ]);

            return new Collection;
        }
    }

    /**
     * Traite la réponse API et la convertit en une collection de modèles.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function processRecords(array $records, array $columns = ['*'])
    {
        $models = [];

        foreach ($records as $record) {
            $model = $this->model->newInstance([], true);

            // Convertit les champs API en attributs de modèle
            foreach ($record as $field => $value) {
                $attribute = $this->model->mapFieldToColumn($field);
                $model->setAttribute($attribute, $value);
            }

            $models[] = $model;
        }

        // Crée une collection de modèles
        $collection = $this->model->newCollection($models);

        // Applique les clauses having si nécessaire
        if (!empty($this->havings)) {
            $collection = $this->applyHavingClauses($collection);
        }

        return $collection;
    }

    /**
     * Applique les clauses having à la collection.
     *
     * @return \Illuminate\Support\Collection
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
     * @param int $perPage
     * @param array $columns
     * @param string $pageName
     * @param int|null $page
     *
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function paginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null)
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
     * @return \Illuminate\Pagination\CursorPaginator
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
}
