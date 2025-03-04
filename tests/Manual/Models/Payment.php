<?php

namespace Adisaf\CsvEloquent\Tests\Manual\Models;

use Adisaf\CsvEloquent\Models\ModelCSV;
use Adisaf\CsvEloquent\Traits\HasCsvSchema;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class Payment extends ModelCSV
{
    use HasCsvSchema;
    use SoftDeletes;

    /**
     * Le nom du fichier CSV associé au modèle.
     *
     * @var string
     */
    protected $csvFile = 'payments';  // Assurez-vous que ce nom correspond à celui utilisé dans l'API

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
        // 'amount' => 'montant',
        // 'status' => 'statut',
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
        if (!$this->transaction_id) {
            return new Collection;
        }

        return Transfer::where('merchant_transaction_id', $this->merchant_transaction_id)->get();
    }

    /**
     * Surcharge pour le débogage
     *
     * @param array $attributes
     * @param bool $exists
     * @return static
     */
    public function newInstance($attributes = [], $exists = false)
    {
        if (config('csv-eloquent.debug', false) && app()->bound('log')) {
            Log::debug('Payment::newInstance called', [
                'attributesCount' => count($attributes),
                'exists' => $exists,
                'csvFile' => $this->getCsvFile()
            ]);
        }

        return parent::newInstance($attributes, $exists);
    }
}
