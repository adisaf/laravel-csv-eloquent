<?php

namespace Adisaf\CsvEloquent\Tests\Fixtures;

use Illuminate\Database\Eloquent\SoftDeletes;
use Adisaf\CsvEloquent\Models\ModelCSV;
use Adisaf\CsvEloquent\Traits\HasCsvSchema;

class TestModel extends ModelCSV
{
    use HasCsvSchema;
    use SoftDeletes;

    /**
     * Le nom du fichier CSV associé au modèle.
     *
     * @var string
     */
    protected $csvFile = 'tests.csv';

    /**
     * Les attributs qui doivent être convertis.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'age' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Le mappage des colonnes CSV.
     *
     * @var array
     */
    protected $columnMapping = [
        // Définissez ici vos mappages de colonnes pour les tests
    ];
}
