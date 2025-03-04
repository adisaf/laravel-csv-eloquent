<?php

namespace Adisaf\CsvEloquent\Tests\Manual\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Adisaf\CsvEloquent\Models\ModelCSV;
use Adisaf\CsvEloquent\Traits\HasCsvSchema;

class Transfer extends ModelCSV
{
    use HasCsvSchema;
    use SoftDeletes;

    /**
     * Le nom du fichier CSV associé au modèle.
     *
     * @var string
     */
    protected $csvFile = 'transferts.csv';

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
        // Pas besoin de mappage car les noms sont identiques
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
     * Ceci est une méthode personnalisée qui simule une relation, puisque
     * les relations directes ne sont pas prises en charge par l'API CSV.
     *
     * @return \Adisaf\CsvEloquent\Models\ModelCSV|null
     */
    public function getPayment()
    {
        if (! $this->merchant_transaction_id) {
            return null;
        }

        return Payment::where('merchant_transaction_id', $this->merchant_transaction_id)->first();
    }

    /**
     * Scope des transferts terminés (succès).
     *
     * @param \Adisaf\CsvEloquent\Builder $query
     *
     * @return \Adisaf\CsvEloquent\Builder
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'Y');
    }

    /**
     * Scope des transferts échoués.
     *
     * @param \Adisaf\CsvEloquent\Builder $query
     *
     * @return \Adisaf\CsvEloquent\Builder
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'N');
    }

    /**
     * Scope des transferts pour un pays spécifique.
     *
     * @param \Adisaf\CsvEloquent\Builder $query
     * @param string $countryCode
     *
     * @return \Adisaf\CsvEloquent\Builder
     */
    public function scopeForCountry($query, $countryCode)
    {
        return $query->where('country', $countryCode);
    }

    /**
     * Scope des transferts pour un opérateur spécifique.
     *
     * @param \Adisaf\CsvEloquent\Builder $query
     * @param string $carrier
     *
     * @return \Adisaf\CsvEloquent\Builder
     */
    public function scopeForCarrier($query, $carrier)
    {
        return $query->where('carrier_name', $carrier);
    }

    /**
     * Scope des transferts entre des dates spécifiques.
     *
     * @param \Adisaf\CsvEloquent\Builder $query
     * @param string $startDate
     * @param string $endDate
     *
     * @return \Adisaf\CsvEloquent\Builder
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}
