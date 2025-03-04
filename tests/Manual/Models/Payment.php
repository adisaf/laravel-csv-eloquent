<?php

namespace Adisaf\CsvEloquent\Tests\Manual\Models;

use Adisaf\CsvEloquent\Models\ModelCSV;
use Adisaf\CsvEloquent\Traits\HasCsvSchema;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Payment extends ModelCSV
{
    use HasCsvSchema;
    use SoftDeletes;

    /**
     * Le nom du fichier CSV associé au modèle.
     *
     * @var string
     */
    protected $csvFile = 'payments.csv';  // Maintenant avec l'extension .csv

    /**
     * Les attributs qui doivent être convertis.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'merchant_id' => 'integer',
        'customer_id' => 'integer',
        'fee_id' => 'integer',
        'amount' => 'float',
        'fee_amount' => 'float',
        'merchant_amount' => 'float',
        'api_status' => 'integer',
        'pay_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'value_date' => 'datetime',
        'completed_at' => 'datetime',
        'refund_at' => 'datetime',
        'otp_expired_at' => 'datetime',
        'number_of_status_check_attempts' => 'integer',
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
        'otp_code',
        'payment_token',
        'notify_token',
    ];

    /**
     * Simule la récupération des transferts associés à ce paiement.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getTransfers()
    {
        if (! $this->transaction_id) {
            return new Collection;
        }

        return Transfer::where('merchant_transaction_id', $this->merchant_transaction_id)->get();
    }
}
