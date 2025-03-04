<?php

namespace Paymetrust\CsvEloquent\Traits;

trait HasCsvSchema
{
    /**
     * Le schéma CSV mis en cache.
     *
     * @var array|null
     */
    protected static $csvSchema = null;

    /**
     * Obtient le schéma du fichier CSV associé au modèle.
     *
     * @return array
     */
    public function getCsvSchema()
    {
        if (static::$csvSchema === null) {
            static::$csvSchema = $this->getSchema();
        }

        return static::$csvSchema;
    }

    /**
     * Détermine si une colonne existe dans le schéma CSV.
     *
     * @param string $column
     *
     * @return bool
     */
    public function hasCsvColumn($column)
    {
        $schema = $this->getCsvSchema();
        $field = $this->mapColumnToField($column);

        return isset($schema[$field]);
    }

    /**
     * Obtient le type de données d'une colonne CSV.
     *
     * @param string $column
     *
     * @return string|null
     */
    public function getCsvColumnType($column)
    {
        $schema = $this->getCsvSchema();
        $field = $this->mapColumnToField($column);

        return $schema[$field]['type'] ?? null;
    }

    /**
     * Détermine si une colonne CSV peut contenir des valeurs nulles.
     *
     * @param string $column
     *
     * @return bool
     */
    public function csvColumnHasNulls($column)
    {
        $schema = $this->getCsvSchema();
        $field = $this->mapColumnToField($column);

        return ($schema[$field]['has_nulls'] ?? false) === true;
    }

    /**
     * Obtient des exemples de valeurs pour une colonne CSV.
     *
     * @param string $column
     *
     * @return array|null
     */
    public function getCsvColumnSamples($column)
    {
        $schema = $this->getCsvSchema();
        $field = $this->mapColumnToField($column);

        return $schema[$field]['sample_values'] ?? null;
    }

    /**
     * Analyse le schéma CSV pour définir automatiquement les casts d'attributs.
     *
     * @return array
     */
    public function getAutoCasts()
    {
        $schema = $this->getCsvSchema();
        $casts = [];

        foreach ($schema as $field => $info) {
            $column = $this->mapFieldToColumn($field);

            switch ($info['type'] ?? null) {
                case 'BIGINT':
                case 'INTEGER':
                    $casts[$column] = 'integer';

                    break;
                case 'DOUBLE':
                case 'FLOAT':
                    $casts[$column] = 'float';

                    break;
                case 'BOOLEAN':
                    $casts[$column] = 'boolean';

                    break;
                case 'DATE':
                    $casts[$column] = 'date';

                    break;
                case 'TIMESTAMP':
                    $casts[$column] = 'datetime';

                    break;
                case 'JSON':
                    $casts[$column] = 'array';

                    break;
            }
        }

        return $casts;
    }

    /**
     * Vide le cache de schéma CSV.
     *
     * @return void
     */
    public static function clearSchemaCache()
    {
        static::$csvSchema = null;
    }
}
