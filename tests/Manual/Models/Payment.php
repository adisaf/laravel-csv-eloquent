<?php

namespace Paymetrust\CsvEloquent\Tests\Manual\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Paymetrust\CsvEloquent\Models\ModelCSV;
use Paymetrust\CsvEloquent\Traits\HasCsvSchema;

class Payment extends ModelCSV
{
    use HasCsvSchema, SoftDeletes;

    /**
     * Le nom du fichier CSV associé au modèle.
     *
     * @var string
     */
    protected $csvFile = 'paiements.csv';

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
        // Pas besoin de mappage car les noms sont identiques
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
     * Ceci est une méthode personnalisée qui simule une relation, puisque
     * les relations directes ne sont pas prises en charge par l'API CSV.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getTransfers()
    {
        if (!$this->transaction_id) {
            return new Collection();
        }

        return Transfer::where('merchant_transaction_id', $this->merchant_transaction_id)->get();
    }

    /**
     * Scope des paiements validés.
     *
     * @param \Paymetrust\CsvEloquent\Builder $query
     * @return \Paymetrust\CsvEloquent\Builder
     */
    public function scopeValidated($query)
    {
        return $query->where('status', 'Y');
    }

    /**
     * Scope des paiements échoués.
     *
     * @param \Paymetrust\CsvEloquent\Builder $query
     * @return \Paymetrust\CsvEloquent\Builder
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'N');
    }

    /**
     * Scope des paiements avec un montant supérieur à la valeur donnée.
     *
     * @param \Paymetrust\CsvEloquent\Builder $query
     * @param float $amount
     * @return \Paymetrust\CsvEloquent\Builder
     */
    public function scopeMinAmount($query, $amount)
    {
        return $query->where('amount', '>=', $amount);
    }

    /**
     * Scope des paiements pour un pays donné.
     *
     * @param \Paymetrust\CsvEloquent\Builder $query
     * @param string $countryCode
     * @return \Paymetrust\CsvEloquent\Builder
     */
    public function scopeForCountry($query, $countryCode)
    {
        return $query->where('country', $countryCode);
    }

    /**
     * Scope des paiements pour un opérateur donné.
     *
     * @param \Paymetrust\CsvEloquent\Builder $query
     * @param string $carrier
     * @return \Paymetrust\CsvEloquent\Builder
     */
    public function scopeForCarrier($query, $carrier)
    {
        return $query->where('carrier_name', $carrier);
    }
}
