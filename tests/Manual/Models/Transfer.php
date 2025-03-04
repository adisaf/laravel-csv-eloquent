<?php

namespace Adisaf\CsvEloquent\Tests\Manual\Models;

use Adisaf\CsvEloquent\Models\ModelCSV;
use Adisaf\CsvEloquent\Traits\HasCsvSchema;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transfer extends ModelCSV
{
    use HasCsvSchema;
    use SoftDeletes;

    /**
     * Le nom du fichier CSV associé au modèle.
     *
     * @var string
     */
    protected $csvFile = 'transfers.csv';  // Maintenant avec l'extension .csv

    /**
     * Les attributs qui doivent être convertis.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'sender_id' => 'integer',
        'receiver_id' => 'integer',
        'fee_id' => 'integer',
        'amount' => 'float',
        'fee_amount' => 'float',
        'merchant_amount' => 'float',
        'api_status' => 'integer',
        'pay_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'creation_date' => 'datetime',
    ];

    /**
     * Le mappage des colonnes CSV.
     *
     * @var array
     */
    protected $columnMapping = [
        // Si vos noms de colonnes diffèrent entre l'API et le modèle, définissez-les ici
    ];

    /**
     * Les attributs qui doivent être cachés pour les sérialisations.
     *
     * @var array
     */
    protected $hidden = [
        'notify_token',
    ];

    /**
     * Simule la récupération du paiement associé à ce transfert.
     *
     * @return ModelCSV|null
     */
    public function getPayment()
    {
        if (!$this->merchant_transaction_id) {
            return null;
        }

        return Payment::where('merchant_transaction_id', $this->merchant_transaction_id)->first();
    }
}
